php-sessions
==============
A class for PHP session handling in Mongo, MySQL and Memcache using the php built-in or a custom hook and storing flat data as JSON.

This class has been in use by me for many years, but it would alter slightly each revision causing regressions. A friend was using an old method which was acting a little janky and I decided to write a few tests to make sure it stays functional on all the different modes it supports.

Usage
-----
First assign `Session::$source` to the type of datasource you are using ('mongo', 'mysql' or 'memcache'). Then you'll need to decide whether you are using the built-in hook `session_start()` or whether you want a custom hook by setting `Session::$hook` to 'custom' or 'php'.


In order to remain testable the we use an interface to handle cookies, which can be swapped out in situations where the apache function is not callable or useable. This interface looks like this:

    class WebApplication{
        public static function getCookie($key){
            //return the cookie
        }
    
        public static function setCookie($key, $value){
            //assign the cookie
        }
    }

Once you've done that all you need is a link to the datasource you are going to use and pass it into the session. Assuming it's available in `$link`:
    
    $session = new Session($link);
    
Then to access:
    
    $session->get(<name>);
    
and to set:
    
    $session->set(<name>, <value>);
    
Of course with the 'php' hook, variables can be interacted with through `$_SESSION`

and then if you want to prematurely end a session at runtime:

    $session->shutdown();

Testing
-------

Run the SimpleTest unit tests at the project root with:

    php test.php

Enjoy,

-Abbey Hawk Sparrow