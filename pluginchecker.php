<?php

/*
Plugin Name: WP Plugin checker
Plugin URI: http://www.deaannemers.nl
Description: This plugin checks installed plugins for abnormalities as vulnerabilities for infection and hackability. It also checks for plugin updates. It sends out an e-mail if an abnormality or update is detected. 
Author: Daniel Gelling
Author e-mail: daniel@deaannemers.nl
Author URI: http://www.deaannemers.nl
Version: 1.0
*/

require 'vendor/autoload.php';

if(file_exists(dirname(__FILE__) . '/../../../wp-load.php'))
    require_once dirname(__FILE__) . '/../../../wp-load.php';
else
    die('wp-load not found');

use Carbon\Carbon;
use DanielGelling\PluginChecker;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Load the javascript every hour after a check has finished.
 */

$lastCheck = Carbon::createFromTimestamp(strtotime(get_option('last_plugins_check', Carbon::now('Europe/Amsterdam'))));

if($lastCheck->diffInMinutes() >= 60)
{
    add_action('wp_enqueue_scripts', 'pluginCheckerInitJs');
    function pluginCheckerInitJs() {
        wp_enqueue_script('pluginChecker', plugin_dir_url(__FILE__) . 'src/pluginChecker.js');
    }
}

if($_POST['pluginCheck'] == 'true')
{
    /**
    * Set up Eloquent database connection.
    */
    $capsule = new Capsule();

    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => DB_HOST,
        'database'  => DB_NAME,
        'username'  => DB_USER,
        'password'  => DB_PASSWORD,
        'charset'   => DB_CHARSET,
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
    ]);

    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $pluginChecker = new PluginChecker();
    $pluginChecker->run();
}
