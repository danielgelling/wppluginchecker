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

use Carbon\Carbon;
use DanielGelling\PluginChecker;
use Illuminate\Database\Capsule\Manager as Capsule;

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

if($pluginChecker->getResult()['panic'] === true)
    \add_action('plugins_loaded', 'sendAnEmailOfzow');

function sendAnEmailOfzow()
{
    $recipient= 'daniel@deaannemers.nl';
    $message = 'jhomo!';
    dd(\wp_mail($recipient, 'New security vulnerability found!', $message));
}
