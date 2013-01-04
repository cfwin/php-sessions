<?php
class WebApplication{
    public static function getCookie($key){
        global $_COOKIES;
        return $_COOKIES[$key];
    }
    
    public static function setCookie($key, $value){
        global $_COOKIES;
        $_COOKIES[$key] = $value;
    }
}