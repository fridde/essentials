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

    public static function setAppUrl($sub_dir = null)
    {
        $base_dir = $GLOBALS["BASE_DIR"] ?? null;
        if(!empty($base_dir) && empty($sub_dir)){
            $sub_dir = basename($base_dir);
        }
        $sub_dir = $sub_dir ?? "";
        if(substr($sub_dir, -1 , 1) !== "/"){
            $sub_dir .= "/";
        }
        $GLOBALS["APP_URL"] = $_SERVER['HTTP_HOST'] . "/" . $sub_dir;
    }

    /**
     * [setBaseUrl description]
     * @param [type] $dir     Should always be __DIR__, called from the index file
     * @param string $app_dir [description]
     */
    public static function setBaseDir($dir)
    {
        if(substr($dir, -1 , 1) !== "/"){
            $dir .= '\\';
        }
        $GLOBALS["BASE_DIR"] = $dir;
    }

    public static function getSettings($file = 'config/settings.toml', $prefix = "", $globalize = true)
    {
        $possible_locations[] = $GLOBALS["APP_URL"] ?? null;
        $possible_locations[] = $GLOBALS["BASE_DIR"] ?? null;
        $possible_locations[] = $_SERVER['DOCUMENT_ROOT'];
        $possible_locations[] = getcwd();

        $settings = false;
        foreach($possible_locations as $dir){
            $path = realpath($dir . "/" . $file);
            if(is_readable($path)){
                break;
            }
        }
        if (empty($path) || !is_readable($path)) {
            throw new \Exception('The file '.$path." is not readable or doesn't exist.");
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext == 'toml') {
            $settings = Toml::Parse($path);

        } elseif ($ext == 'json') {
            $json_string = file_get_contents($path);
            if (!$json_string) {
                throw new \Exception("The file $path could not be read or found!");
            }
            $settings = json_decode($json_string, true);
        } elseif ($ext == 'ini') {
            $settings = parse_ini_file($path, true);
        } else {
            throw new \Exception('The function getSettings has no implementation for the file extension <'.$ext.'>');
        }

        if($settings && $globalize){
            $GLOBALS[$prefix . "SETTINGS"] = $settings;
        }

        return $settings;
    }

    public static function getRoutes($file = 'config/routes.toml')
    {
        $routes = self::getSettings($file, "", false);
        $routes = array_filter($routes["routes"]);
        return $routes;
    }

    public static function activateDebug()
    {
        $GLOBALS['debug'] = true;
        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        Debugger::enable();
        Debugger::$strictMode = TRUE;
        Debugger::$logSeverity = E_NOTICE | E_WARNING;
        Debugger::$maxDepth = 8;
    }

    public static function prePrint($var)
    {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }
}
