<?php

namespace Fridde;

use Yosymfony\Toml\Toml;
use Symfony\Component\Yaml\Yaml;

class Settings
{
    public static function setSettings(array $files = ['settings', 'nav'], string $folder = '/config', $ext = 'yaml')
    {
        $settings = [];
        foreach ($files as $file) {
            $path = empty($folder) ? '' : $folder . '/';
            $path .= $file;
            $path .= empty($ext) ? '' : '.' . $ext;
            $settings = $settings + self::getArrayFromFile($path);
        }

        if(defined('SETTINGS')){
            throw new \Exception("Can't redefine constant SETTINGS");
        }
        define('SETTINGS', $settings);
        $GLOBALS["SETTINGS"] = $settings; // for backwards-compatibility

        return $settings;
    }

    private static function getPossibleLocations()
    {
        $loc[] = defined('APP_URL') ? APP_URL : null;
        $loc[] = defined('BASE_DIR') ? BASE_DIR : null;
        $loc[] = $_SERVER['DOCUMENT_ROOT'];
        $loc[] = getcwd();

        return array_filter($loc);
    }

    public static function getArrayFromFile($file)
    {
        $possible_locations = self::getPossibleLocations();
        foreach ($possible_locations as $dir) {
            $pot_path = realpath($dir.$file);
            if (is_readable($pot_path)) {
                $path = $pot_path;
                break;
            }
        }
        if (empty($path)) {
            throw new \Exception("The file $path is not readable or doesn't exist.");
        }

        return self::getArray($path);
    }

    private static function getArray($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($ext) {
            case "toml":
                $method = "Toml";
                break;
            case "yml":
            case "yaml":
                $method = "Yaml";
                break;
            case "json":
                $method = "Json";
                break;
            case "ini":
                $method = "Ini";
                break;
        }
        $full_method_name = "getArrayFrom".$method."File";

        return self::$full_method_name($path);
    }

    private function getArrayFromTomlFile($path)
    {
        return Toml::Parse($path);
    }

    private function getArrayFromYamlFile($path)
    {
        return Yaml::parse(file_get_contents($path));
    }

    private function getArrayFromJsonFile($path)
    {
        $json_string = file_get_contents($path);
        if (!$json_string) {
            throw new \Exception("The file $path could not be read or found!");
        }

        return json_decode($json_string, true);
    }

    private function getArrayFromIniFile($path)
    {
        return parse_ini_file($path, true);
    }

}