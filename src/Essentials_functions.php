<?php
/**
* This file is executed in the global scope and defines global functions.
*/

$functions = $functions ?? [];
$all = $functions["_ALL_"] ?? false;

if($all || in_array("getRequest", $functions)){

    function getRequest($content_type = null)
    {
        $possible_content_types = ["json", "urlencoded"];
        if(empty($data_type) && function_exists("getallheaders")){
            $req_headers = getallheaders();
            $content_type = $req_headers['Content-Type'] ?? "";
        }
        $content_types = array_filter($possible_content_types, function($ct) use ($content_type){
            return strpos($content_type, $ct) !== false;
        });
        if(count($content_types) > 1){
            throw new \Exception("This was a weird content-type: " . $content_type);
        }
        if(empty($content_types)){
            return $_REQUEST;
        }
        $defined_CT = array_shift($content_types);
            
        if($defined_CT == "json"){
            $string = file_get_contents("php://input");
            json_decode($string);
            $is_valid = json_last_error() == JSON_ERROR_NONE;
            if(strlen($string) > 0 && $is_valid){
                return json_decode($string, true);
            } else {
                return null;
            }
        } elseif($defined_CT == "urlencoded"){
            return $_REQUEST;
        }
    }

}
/*
 EXAMPLE:

if($all || in_array("someOtherFunction", $functions)){

    function someFunction()
    {

    }
}
*/

unset($functions, $all);
