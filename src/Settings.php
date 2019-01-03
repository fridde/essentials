<?php

namespace Fridde;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\Yaml\Yaml;

class Settings
{

    public static function setSettings(array $options = []): ?array
    {
        $file_args = [
            $options['files'] ?? ['settings_default', 'nav'],
            $options['dir'] ?? 'config',
            $options['ext'] ?? 'yml'
        ];
        if (defined('ENVIRONMENT')) {
            $file_args[0][] = 'settings_'.ENVIRONMENT;
        }
        $cache = $options['cache'] ?? null;

        $settings = self::getSettingsFromCache($cache);
        $settings = $settings ?? self::getSettingsFromFiles(...$file_args);

        self::saveToCache($cache, ['settings' => $settings]);

        if (defined('SETTINGS')) {
            throw new \Exception('Can\'t redefine constant SETTINGS');
        }
        define('SETTINGS', $settings);
        $GLOBALS['SETTINGS'] = $settings; // for backwards-compatibility

        return $settings;
    }

    private static function getSettingsFromCache(Cache $cache = null): ?array
    {
        if(! ($cache instanceof Cache)){
            return null;
        }
        if(!$cache->contains('settings')){
            return null;
        }
        return $cache->fetch('settings');
    }

    private static function getSettingsFromFiles(array $files = [], string $dir = null, string $ext): ?array
    {
        $settings = [];
        foreach ($files as $file) {
            $path = empty($dir) ? '' : $dir.'/';
            $path .= $file;
            $path .= empty($ext) ? '' : '.'.$ext;
            $settings[] = self::getArrayFromFile($path);
        }

        return array_replace_recursive(...$settings);
    }

    private static function saveToCache(Cache $cache = null, array $items = []): void
    {
        if(!($cache instanceof Cache)){
            return;
        }
        foreach($items as $id => $data){
            $cache->save($id, $data);
        }
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
