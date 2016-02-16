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
        $this->checkFiles(dirname(__FILE__) . '/../../../../');
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

        update_option('last_plugins_check', Carbon::now('Europe/Amsterdam'));
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
// dd('this->occ:', $this->occurrences, 'files:', $files, 'new occ:', $newOccurrences);
        $occurrences = count($this->occurrences) - $lastCheck->occurrences;

        if($occurrences != count($newOccurrences));
            //Something went terribly wrong

        $occurrenceText = "";

        foreach($newOccurrences as $key => $newOccurrence)
            $occurrenceText .= "<b>" . ($key + 1) . ". " . $newOccurrence['file'] . ":</b> <br /><br />" . $newOccurrence['data'] . "<br /><br /><br />";

        $message = 
        "Dear webmaster, <br /><br /> " . $occurrences . 
        " new possible security vulnerabilit" . 
        ($occurrences == 1 ? "y was" : "ies have been" ) . 
        " found in " . get_site_url() . ". This occurred in the following file" .
        ($occurrences == 1 ? "" : "s" ) . ":<br /><br />" . 
        "<b>" .$occurrenceText . "</b><br />" .
        "Please go check your source code immediately!<br /><br />" .
        "<br /><br /> WPPluginChecker Plugin<br /><br /><br />" .
        "DISCLAIMER:<br /><br />" .
        "This is an automatically generated message by the Wordpress plugin WPPluginChecker. <br /><br />In no event the authors of this plugin can he held liable for any indirect, incidential or consequential damages of any kind. This sofware is provided \"as is\" and without warranty of any kind.";

        $recipient = \get_option('admin_email', 'josse@deaannemers.nl');

        $recipient = "daniel@deaannemers.nl";

        // Hook not needed anymore?

        // \add_action('plugins_loaded', function() use ($message, $recipient) {
            \wp_mail($recipient, 'New security vulnerability found!', $message, "Content-type: text/html");
        // });

        $this->result['emailSent'] = true;
    }
}
