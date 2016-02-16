<?php

namespace DanielGelling;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;


if(file_exists(dirname(__FILE__) . '/../../../../wp-load.php'))
    require_once dirname(__FILE__) . '/../../../../wp-load.php';
else
    die('wp-load not found');

class PluginChecker
{
    protected $result = [];
    protected $occurrences = [];
    private $table;

    public function __construct()
    {
        $this->table = Capsule::table('PluginChecker');
    }

    public function run()
    {
        $start = microtime(true);
        $this->checkFiles('./');
        $this->checkForChanges();
        $this->result['duration'] = microtime(true) - $start;
    }

    public function checkFiles($path)
    {
        if(substr($path, -1) != '/')
            $path .= '/';

        $handle = opendir($path);

        while($file = readdir($handle)){
            if($file == '.' || $file == '..')
                continue;

            if(is_dir($path . $file))
            {
                $this->checkFiles($path . $file);
            }
            else
            {
                if(substr($file, -4) == ".php")
                {
                    $contents = file_get_contents($path . $file);
                    if(
                        preg_match("/.*(eval\().*/", $contents, $occurrences) ||
                        preg_match("/.*(base64_decode\().*/", $contents, $occurrences) ||
                        preg_match("/.*(gzdeflate\().*/", $contents, $occurrences) ||
                        preg_match("/.*(gzdecode\().*/", $contents, $occurrences)
                    )
                    {
                        $this->occurrences[] = [
                            'file' => $path . $file,
                            'data' => $occurrences[0],
                            'match' => $occurrences[1],
                        ];
                    }
                }
            }
        }
    }

    public function checkForChanges()
    {
        $lastCheck = $this->table
                          ->orderBy('scanned_at', "DESC")
                          ->first();
        
        if($lastCheck->occurrences != count($this->occurrences))
        {
            // NOW IT'S TIME TO PANIC
            $this->mailResult($lastCheck);
            $this->update();
            $this->result['panic'] = true;
        }
        else
            $this->result['panic'] = false;

        // dd($this->result);
    }

    public function update()
    {
        $this->table->insert([
            'occurrences' => count($this->occurrences),
            'data' => json_encode($this->occurrences),
            'scanned_at' => Carbon::now('Europe/Amsterdam')
        ]);
    }

    public function getResult()
    {
        return $this->result;
    }

    public function mailResult($lastCheck)
    {   
        $files = [];
        foreach(json_decode($lastCheck->data) as $occurrence)
        {
            $files[$occurrence->file] = $occurrence->data;
        }

        foreach($this->occurrences as $occurrence)
        {
            if(!array_key_exists($occurrence['file'], $files))
                $newOccurrences[] = [
                    'file' => $occurrence['file'],
                    'data' => $occurrence['data']
                ];
        }


        $occurrences = count($this->occurrences) - $lastCheck->occurrences;

        if($occurrences != count($newOccurrences));
            //Something went terribly wrong

        $occurrenceText = "";

        foreach($newOccurrences as $newOccurrence)
            $occurrenceText .= $newOccurrence['file'] . ": \n" . $newOccurrence['data'] . "\n\n";

        $message = 
        "Dear webmaster, \n\n " . $occurrences . 
        " new possible security vulnerabilit" . 
        ($occurrences == 1 ? "y was" : "ies have been" ) . 
        " found. This occurred in the following file" .
        ($occurrences == 1 ? "" : "s" ) . ":" . 
        "Please go check them immediately!\n\n
        --- \n WPPluginChecker Plugin";


        $recipient = "info@danielgelling.nl";

        \add_action('plugins_loaded', function() use ($message, $recipient) {
            \wp_mail($recipient, 'New security vulnerability found!', $message);
        });

        $this->result['emailSent'] = true;
    }

}
