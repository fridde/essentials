<?php
/**
* This file is executed in the global scope and defines global functions.
*/

$functions = $functions ?? [];
$all = $functions["_ALL_"] ?? false;

if($all || in_array("getJsonRequest", $functions)){

    function getRequest($data_type = null)
    {
        if(empty($data_type)){
            return $_REQUEST;
        } elseif($data_type == "json"){
            $string = file_get_contents("php://input");
            json_decode($string);
            $is_valid = json_last_error() == JSON_ERROR_NONE;
            if(strlen($string) > 0 && $is_valid){
                return json_decode($string, true);
            } else {
                return null;
            }
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
