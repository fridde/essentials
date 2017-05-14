<?php
namespace Fridde;

use Yosymfony\Toml\Toml;
use Tracy\Debugger;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ChromePHPHandler;
use League\Container\Container;
use League\Container\Argument\RawArgument;
use MySQLHandler\MySQLHandler;


class Essentials
{

    /**
    * Defines $_SERVER['HTTP_HOST'] plus an optional subdirectory as the global constant APP_URL
    *
    * @param string $sub_dir An optional subdirectory below HTTP_HOST
    * @return void
    */
    public static function setAppUrl($sub_dir = null)
    {
        $base_dir = defined('BASE_DIR') ? BASE_DIR : null;
        if(!empty($base_dir) && empty($sub_dir)){
            $sub_dir = basename($base_dir);
        }
        $sub_dir = $sub_dir ?? "";
        if(substr($sub_dir, -1 , 1) !== "/"){
            $sub_dir .= "/";
        }
        if(!defined('APP_URL')){
            define('APP_URL', $_SERVER['HTTP_HOST'] . "/" . $sub_dir);
        }
        $GLOBALS["APP_URL"] = APP_URL; // for backwards-compatibility
    }

    /**
    * Sets a given directory as the global constant BASE_DIR. Use this function in
    * your index file and give it __DIR__ as argument.
    *
    * @param string $dir     The directory to set as BASE_DIR. Should always be __DIR__,
    *                        called from the index file
    * @return void
    */
    public static function setBaseDir($dir)
    {
        if(substr($dir, -1 , 1) !== "/"){
            $dir .= '\\';
        }
        if(!defined('BASE_DIR')){
            define('BASE_DIR', $dir);
        }
        $GLOBALS["BASE_DIR"] = $dir; // for backwards-compatibility
    }

    public static function getSettings(string $file = 'config/settings.toml', bool $globalize = true)
    {
        $possible_locations[] = defined('APP_URL') ? APP_URL : null;
        $possible_locations[] = defined('BASE_DIR') ? BASE_DIR : null;
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

        if($settings && $globalize && !defined('SETTINGS')){
            define('SETTINGS', $settings);
            $GLOBALS["SETTINGS"] = $settings; // for backwards-compatibility
        }

        return $settings;
    }

    public static function getRoutes($file = 'config/routes.toml')
    {
        $routes = self::getSettings($file, "", false);
        $routes = array_filter($routes["routes"]);
        return $routes;
    }

    public static function activateDebug($options = [])
    {
        $GLOBALS['debug'] = true;

        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        if(!in_array("no_tracy", $options)){
            Debugger::enable();
            Debugger::$strictMode = TRUE;
            Debugger::$logSeverity = E_NOTICE | E_WARNING;
            Debugger::$maxDepth = 8;
        }
    }

    public static function getLogger(string $logger_type = "Info")
    {
        $container = $GLOBALS["CONTAINER"] ?? null;
        $logger_name = $logger_type . "Logger";

        if(!empty($container) && $container->has($logger_name)){
            return $container->get($logger_name);
        }
        $logger = new Logger($logger_name);
        if($logger_type == "Info"){
            $stream = new StreamHandler(BASE_DIR.'log/info.log', Logger::DEBUG);
            $stream->setFormatter(new LineFormatter());
            $logger->pushHandler($stream);
        } elseif($logger_type == "Error"){
            $stream = new StreamHandler(BASE_DIR.'log/errors.log', Logger::DEBUG);
            $stream->setFormatter(new LineFormatter());
            $logger->pushHandler($stream);
            $logger->pushHandler(new ChromePHPHandler());
            ErrorHandler::register($logger);
        } else {
            throw new \Exception("The logger type <" . $logger_type . "> is not defined.");
        }
        return $logger;
    }

    public static function registerSharedServices(...$services)
    {
        if(count($services) === 1 && array_filter($services[0], "is_array") == $services[0]){
            $services = $services[0];
        }

        $container = new Container();
        foreach($services as $service){
            $count = count($service);
            if($count === 2){
                $container->share($service[0], $service[1]);
            } elseif($count > 2){
                $const_args = array_slice($service, 2);
                array_walk($const_args, function(&$a){
                    $a = new RawArgument($a);
                });
                $container->share($service[0], $service[1])
                ->withArguments($const_args);
            }
        }
        $GLOBALS["CONTAINER"] = $container;
        return $container;
    }

    /**
     * [registerDBLogger description]
     * @param  \Doctrine\ORM\EntityManager $entity_manager The entity manager containing
     *                                                     a valid connection setup
     * @param  \Monolog\Logger $logger A logger instance
     * @return void
     */
    public static function registerDBLogger($entity_manager, $logger)
    {
        $pdo = $entity_manager->getConnection()->getWrappedConnection();
        $mySQLHandler = new MySQLHandler($pdo, "errors", [] , Logger::DEBUG);
        $logger->pushHandler($mySQLHandler);
    }

    public static function prePrint($var)
    {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }

    public static function activateGlobalFunctions(...$function_names)
    {
        $functions["_ALL_"] = empty($function_names) ? true : false;
        $functions += $function_names;
        include_once("Essentials_functions.php");
    }
}
