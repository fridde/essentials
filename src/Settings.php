<?php

namespace Fridde;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\Yaml\Yaml;

class Settings
{

    public static function setSettings(array $options = [])
    {
        $files = $options['files'] ?? ['settings_default', 'nav'];
        $folder = $options['folder'] ?? 'config';
        $ext = $options['ext'] ?? 'yml';
        /* @var Cache $cache */
        $cache = $options['cache'] ?? null;

        $settings = [];

        if (defined('ENVIRONMENT')) {
            $files[] = 'settings_'.ENVIRONMENT;
        }

        if (!empty($cache) && $cache->contains('settings')) {
            $settings = $cache->fetch('settings');
        } else {
            foreach ($files as $file) {
                $path = empty($folder) ? '' : $folder.'/';
                $path .= $file;
                $path .= empty($ext) ? '' : '.'.$ext;
                $settings[] = self::getArrayFromFile($path);
            }
            $settings = array_replace_recursive(...$settings);
            if (!empty($cache)) {
                $cache->save('settings', $settings);
            }
        }

        if (defined('SETTINGS')) {
            throw new \Exception('Can\'t redefine constant SETTINGS');
        }
        define('SETTINGS', $settings);
        $GLOBALS['SETTINGS'] = $settings; // for backwards-compatibility

        return $settings;
    }


    private static function getPossibleLocations()
    {
        $loc[] = defined('BASE_DIR') ? BASE_DIR : null;
        $loc[] = $_SERVER['DOCUMENT_ROOT'];
        $loc[] = getcwd();

        return array_filter($loc);
    }

    public static function getArrayFromFile($file)
    {
        $possible_locations = self::getPossibleLocations();
        foreach ($possible_locations as $dir) {
            $pot_path = $dir.'/'.$file;
            if (is_readable($pot_path)) {
                $path = $pot_path;
                break;
            }
        }
        if (empty($path)) {
            throw new \Exception("The file $file is not readable or doesn't exist.");
        }

        return self::getArray($path);
    }

    private static function getArray($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'yml':
            case 'yaml':
                $method = 'Yaml';
                break;
            case 'json':
                $method = 'Json';
                break;
            case 'ini':
                $method = 'Ini';
                break;
            default:
                $method = '';
        }
        $full_method_name = 'getArrayFrom'.$method.'File';

        return self::$full_method_name($path);
    }

    private static function getArrayFromYamlFile($path)
    {
        return Yaml::parseFile($path);
    }

    private static function getArrayFromJsonFile($path)
    {
        $json_string = file_get_contents($path);
        if (!$json_string) {
            throw new \Exception("The file $path could not be read or found!");
        }

        return json_decode($json_string, true);
    }

    private static function getArrayFromIniFile($path)
    {
        return parse_ini_file($path, true);
    }

}
