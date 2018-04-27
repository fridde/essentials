<?php

namespace Fridde;


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
     * @param string $dir An optional subdirectory below HTTP_HOST
     * @return void
     */
    public static function setAppUrl($dir = null)
    {
        $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if (empty($doc_root) || defined('APP_URL')) {
            return;
        }

        if (empty($dir) && !defined('BASE_DIR')) {
            throw new \Exception('No base directory was specified.');
        }

        $base_dir = self::toUnixPath($dir ?? BASE_DIR);
        $doc_root = self::toUnixPath($doc_root);
        $app_dir = '';
        if (0 === strpos($base_dir, $doc_root)) {
            $app_dir = substr($base_dir, strlen($doc_root));
        }
        $server_host = $_SERVER['HTTP_HOST'] ?? '';  // the empty string is there to be able to run CLI-apps
        define('APP_URL', '//' . $server_host . $app_dir . '/');
    }

    /**
     * Sets a given directory as the global constant BASE_DIR. Use this function in
     * your index file and give it __DIR__ as argument.
     *
     * @param string $dir The directory to set as BASE_DIR. Should always be __DIR__,
     *                        called from the index file
     * @return void
     */
    public static function setBaseDir($dir)
    {
        if (defined('BASE_DIR')) {
            return;
        }

        $dir = rtrim($dir, '/\\').DIRECTORY_SEPARATOR;
        define('BASE_DIR', $dir);
    }

    public static function getRoutes(string $file = 'config/routes.yml')
    {
        $routes = Settings::getArrayFromFile($file);
        $routes = array_filter($routes['routes']);
        array_walk(
            $routes,
            function (&$v, $i) {
                $v[] = $i;
            }
        );

        return array_values($routes);
    }

    public static function activateDebug(array $options = [])
    {
        $GLOBALS['debug'] = true;
        define('DEBUG', true);

        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        if (!in_array('no_tracy', $options, true)) {
            Debugger::enable();
            Debugger::$strictMode = true;
            Debugger::$logSeverity = E_NOTICE | E_WARNING;
            Debugger::$maxDepth = 8;
        }
    }

    public static function getLogger(string $logger_name = 'Logger')
    {
        $container = $GLOBALS['CONTAINER'] ?? null;

        if (!empty($container) && $container->has($logger_name)) {
            return $container->get($logger_name);
        }
        $logger = new Logger($logger_name);
        $file_name = 'log_'.date('Y-m-d').'.log';
        $stream = new StreamHandler(BASE_DIR.'log/'.$file_name, Logger::DEBUG);
        $stream->setFormatter(new LineFormatter());
        $logger->pushHandler($stream);
        $logger->pushHandler(new ChromePHPHandler());
        ErrorHandler::register($logger);

        return $logger;
    }

    public static function registerSharedServices(...$services)
    {
        if (count($services) === 1 && array_filter($services[0], 'is_array') == $services[0]) {
            $services = $services[0];
        }

        $container = new Container();
        foreach ($services as $service) {
            $count = count($service);
            if ($count === 2) {
                $container->share($service[0], $service[1]);
            } elseif ($count > 2) {
                $const_args = array_slice($service, 2);
                array_walk(
                    $const_args,
                    function (&$a) {
                        $a = new RawArgument($a);
                    }
                );
                $container->share($service[0], $service[1])
                    ->withArguments($const_args);
            }
        }
        $GLOBALS['CONTAINER'] = $container;

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
        $mySQLHandler = new MySQLHandler($pdo, 'log', ['source'], Logger::DEBUG);
        $logger->pushHandler($mySQLHandler);
    }

    public static function toUnixPath(string $path)
    {
        return str_replace('\\', '/', $path);
    }

    public static function prePrint($var)
    {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }

}
