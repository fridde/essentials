<?php
namespace Fridde;

use \Yosymfony\Toml\Toml;
use \Tracy\Debugger;

/**
* This class contains the functions needed to start some applications and is
* TODO:
*/

class Essentials
{

    public static function getSettings($globalize = true, $file = 'settings.toml', $prefix = "")
    {
        $settings = false;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!is_readable($file)) {
            throw new \Exception('The file '.$file." is not readable or doesn't exist.");
        }
        if ($ext == 'toml') {
            $settings = Toml::Parse($file);

        } elseif ($ext == 'json') {
            $json_string = file_get_contents($file);
            if (!$json_string) {
                throw new \Exception("The file $file could not be read or found!");
            }
            $settings = json_decode($json_string, true);
        } elseif ($ext == 'ini') {
            $settings = parse_ini_file($file, true);
        } else {
            throw new \Exception('The function getSettings has no implementation for the file extension <'.$ext.'>');
        }

        if($settings && $globalize){
            $GLOBALS[$prefix . "SETTINGS"] = $settings;
        }

        return $settings;
    }

    public static function activateDebug()
    {
        $GLOBALS['debug'] = true;
        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        $log_dir = $_SERVER["HTTP_HOST"] . dirname($_SERVER["SCRIPT_NAME"]) . '/logs';
        if(!is_dir($log_dir)){
            mkdir($log_dir);
        }

        Debugger::enable(Debugger::DETECT, $log_dir);
        Debugger::$strictMode = TRUE;
        Debugger::$logSeverity = E_NOTICE | E_WARNING;

        /*
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
        */
    }

    public static function prePrint($var)
    {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }
}
