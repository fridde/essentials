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
    public const ENV_DEV = 'dev';
    public const ENV_TEST = 'test';
    public const ENV_PROD = 'prod';

    /**
     * Defines $_SERVER['HTTP_HOST'] plus an optional subdirectory as the global constant APP_URL
     *
     * @param string $dir An optional subdirectory below HTTP_HOST
     * @return void
     */
    public static function setAppUrl(string $dir = null): void
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

        $is_cli = empty($_SERVER['HTTP_HOST']);

        $APP_URL = $is_cli ? '' : 'http';
        $APP_URL .= $is_cli || empty($_SERVER['HTTPS']) ? '' : 's'; //http or https
        $APP_URL .= $is_cli ? '' : ':';
        $APP_URL .= '//';
        $APP_URL .= $is_cli ? '' : $_SERVER['HTTP_HOST'];

        $start_from = 0 === strpos($base_dir, $doc_root) ? strlen($doc_root) : 0;
        $APP_URL .= substr($base_dir, $start_from); // remove the common strings

        $APP_URL .= '/';

        define('APP_URL', $APP_URL);
    }

    /**
     * Sets a given directory as the global constant BASE_DIR. Use this function in
     * your index file and give it __DIR__ as argument.
     *
     * @param string $dir The directory to set as BASE_DIR. Should always be __DIR__,
     *                        called from the index file
     * @return void
     */
    public static function setBaseDir(string $dir): void
    {
        if (defined('BASE_DIR')) {
            return;
        }

        $dir = rtrim($dir, '/\\');
        define('BASE_DIR', $dir);
    }

    public static function setEnvironment(): void
    {
        $pattern = BASE_DIR . '/.env_*';
        $files = glob($pattern);

        if(!empty($files)){
            $env = substr($files[0], strlen($pattern) - 1);
        } elseif(false !== strpos($_SERVER['HTTP_HOST'], 'localhost')){
            $env = self::ENV_DEV;
        } else {
            $env = self::ENV_DEV;
        }
        define('ENVIRONMENT', $env);
    }

    /**
     * @param string $file
     * @return array
     * @throws \Exception
     */
    public static function getRoutes(string $file = 'config/routes.yml'): array
    {
        $routes = Settings::getArrayFromFile($file);

        return array_filter($routes['routes']);
    }

    /**
     * @param array $options
     * @throws \Exception
     */
    public static function activateDebugIfNecessary(array $options = []): void
    {
        $environments = [self::ENV_DEV, self::ENV_TEST, self::ENV_PROD];

        if(defined('ENVIRONMENT')){
            if(!in_array(ENVIRONMENT, $environments, true)){
                throw new \Exception('The environment variable '. ENVIRONMENT . ' could not be recognized.');
            }
            if(ENVIRONMENT === self::ENV_PROD){
                define('DEBUG', false);
                return;
            }
        }

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

    /**
     * @param string $logger_name
     * @return Logger
     * @throws \Exception
     */
    public static function getLogger(string $logger_name = 'Logger'): Logger
    {
        $container = $GLOBALS['CONTAINER'] ?? null;

        if (!empty($container) && $container->has($logger_name)) {
            return $container->get($logger_name);
        }
        $logger = new Logger($logger_name);
        $file_name = 'log_'.date('Y-m-d').'.log';
        $stream = new StreamHandler(BASE_DIR.'/log/'.$file_name, Logger::DEBUG);
        $stream->setFormatter(new LineFormatter());
        $logger->pushHandler($stream);
        $logger->pushHandler(new ChromePHPHandler());
        ErrorHandler::register($logger);

        return $logger;
    }

    public static function registerSharedServices(...$services): Container
    {
        if (count($services) === 1 && array_filter($services[0], 'is_array') === $services[0]) {
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
                    ->addArguments($const_args);
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
    public static function registerDBLogger($entity_manager, Logger $logger): void
    {
        $pdo = $entity_manager->getConnection()->getWrappedConnection();
        $mySQLHandler = new MySQLHandler($pdo, 'log', ['source', 'datetime'], Logger::DEBUG);
        $logger->pushHandler($mySQLHandler);
    }

    public static function toUnixPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function prePrint($var): void
    {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }

}
