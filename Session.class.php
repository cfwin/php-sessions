<?php
/*********************************************************************************//**
 *  Session
 *====================================================================================
 * @author Abbey Hawk Sparrow
 * Supports memcache, mongo, mysql and file through PHP or a custom hook
 *************************************************************************************/

class Session{
    public static $instance = null;
    public static $cleanRemoteIP = true;
    public static $lifetime = 2592000; //60 * 60 * 24 * 30 (1 mo)
    public static $sessionID = 'session_id';
    public static $sessionCookie = 'session_id';
    public static $sessionStore = 'session';
    public static $source = 'mysql';
    public static $domain = 'mysql';
    public static $hook = 'php'; //php or custom
    public static $link = null;
    public static $read = false;
    public static $internalValues = array();
    protected $session_id = '';
    
    public function __construct($dblink){
        //todo: detect link type
        if(Session::$instance == null){
            Session::$link = $dblink;
            Session::emit('log', Session::$hook.' '.Session::$source.' session handler started.');
            switch(Session::$hook){
                case 'php':
                    Session::$read = false;
                    Session::$sessionCookie = session_name();
                    session_set_save_handler(
                        array( &$this, 'open' ),
                        array( &$this, 'close' ),
                        array( &$this, 'read' ),
                        array( &$this, 'write' ),
                        array( &$this, 'destroy' ),
                        array( &$this, 'gc' )
                    );
                    register_shutdown_function( 'session_write_close' );
                    session_set_cookie_params( (time() + Session::$lifetime), '/', Session::$domain);
                    @session_start();
                    $this->session_id = session_id();
                    if(!Session::$read) throw('session_start FAILED!');
                    break;
                case 'custom':
                    $this->session_id = WebApplication::getCookie(Session::$sessionCookie);
                    $data = $this->read($this->session_id);
                    if(!empty($this->session_id) && $data !== true && is_array($data)){
                        $this->internalValues = $data;
                    }else{
                        $this->session_id = sprintf('%08x-%04x-%04x-%02x%02x-%02x%02x%02x%02x%02x%02x', 
                            mt_rand(0, 0xffff) + (mt_rand(0, 0xffff) << 16),
                            mt_rand(0, 0xffff),
                            (4 << 12) | (mt_rand(0, 0x1000)),
                            (1 << 7) | (mt_rand(0, 128)),
                            mt_rand(0, 255), mt_rand(0, 255), 
                            mt_rand(0, 255), mt_rand(0, 255), 
                            mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255) 
                        );
                        Session::emit('log', 'Initialized new Session ID['.$this->session_id.']');
                        WebApplication::setCookie(Session::$sessionCookie, $this->session_id);
                        $this->internalValues = Array();
                    }
                    register_shutdown_function( array($this, 'shutdown') );
                    break;
            }
            Session::$instance = $this;
            Session::set('last_access', date ("Y-m-d H:i:s", time()));
        }else{
            throw new Exception('Session already created, only one session instance at a time!');
        }
    }
    
    public static function erase(){
        switch(Session::$hook){
            case 'php':
                session_destroy();
                break;
            case 'custom':
                //todo: implement
        }
    }
    
    public static function emit($event, $payload){
        return;
    }
    
    public function shutdown(){
        if(Session::$hook == 'php'){
            //$this->write($this->session_id, serialize($_SESSION));
            session_write_close();
            $_SESSION = Array();
        }else{
            $this->write($this->session_id, $this->internalValues);
            $this->internalValues = Array();
        } 
        Session::emit('log', 'Saved Session['.$this->session_id.']:'.print_r($this->internalValues, true));
        Session::$instance = null;
        //Session::$link = null;
    }

    public function open( $path, $name ) {
        return true;
    }

    public function close( ) {
        return true;
    }

    public function set($name, $value){
        switch(Session::$hook){
            case 'php':
                if($value === null) unset($_SESSION[$name]);
                else $_SESSION[$name] = $value;
                break;
            case 'custom':
                if($value === null) unset($this->internalValues[$name]);
                else $this->internalValues[$name] = $value;
                break;
        }
    }
    
    public function get($name){
        switch(Session::$hook){
            case 'php':
                if(array_key_exists($name, $_SESSION)) return $_SESSION[$name];
                else return undefined;
                break;
            case 'custom':
                if(array_key_exists($name, $this->internalValues)) return $this->internalValues[$name];
                else return undefined;
                break;
        }
    }

    public function read($sid){
        Session::emit('log', 'Loading session['.Session::$source.']: '.$sid);
        Session::$read = true;
        $result = '';
        switch(Session::$source){
            case 'mysql':
                $sql = 'SELECT * FROM '.Session::$sessionStore.' WHERE session_id = \''.$sid.'\' AND session_time > ' . time( );
                $results = mysql_fetch_assoc(mysql_query($sql,  Session::$link));
                $result = json_decode($results['data'], true);
                break;
            case 'mysqli':
                $sql = 'SELECT * FROM '.Session::$sessionStore.' WHERE session_id = \''.$sid.'\' AND session_time > ' . time( );
                $results =  Session::$link->fetch_assoc(Session::$link->query($sql));
                $result = json_decode($results['data'], true);
                break;
            case 'mongo':
                $coll = Session::$sessionStore;
                $collection = Session::$link->$coll;
                $query = Array( Session::$sessionID => $sid );
                $results = iterator_to_array($collection->find($query), false);
                $result = $results[0]['data'];
                break;
            case 'memcache':
                $result = Session::$link->get($sid);
                break;
        }
        switch(Session::$hook){
            case 'php': //this means we need to deliver serialized code to the parent
                if(array_key_exists('_id', $result)) unset($result['_id']);
                $result = session_real_encode($result);
                break;
            case 'custom': // deliver an object
        }
        return $result;
    }

    public function write($sid, $data){
        Session::emit('log', 'Saving session['.$sid.']:'.json_encode($data));
        preg_match( '/[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}/', $_SERVER['REMOTE_ADDR'], $match );
        $ip = sizeof( $match ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        
        switch(Session::$hook){
            case 'php': //this means are delivered serialized code from the parent
                $data = session_real_decode($data);
                break;
            case 'custom':
        }
        
        $expiry = time( ) + Session::$lifetime ;
        switch(Session::$source){
            case 'mysql':
                if(is_array($data)) $data = json_encode($data);
                $data = mysql_real_escape_string($data,  Session::$link);
                $sql = "INSERT INTO ".Session::$sessionStore." (session_id, data, session_time, ip, creation_time, modification_time )
                        VALUES ('$sid', '".$data."', $expiry, '$ip', now(), now() ) 
                        ON DUPLICATE KEY UPDATE data='".$data."', session_time = '$expiry', modification_time = now()";
                //echo('|'.$sql.'|');
                $results = mysql_query($sql,  Session::$link);
                break;
            case 'mysqli':
                if(is_array($data)) $data = json_encode($data);
                $data =  Session::$link->real_escape_string($data);
                $sql = "INSERT INTO ".Session::$sessionStore." (session_id, data, session_time, ip, creation_time, modification_time )
                        VALUES ('$sid', '$data', $expiry, '$ip', now(), now() ) 
                        ON DUPLICATE KEY UPDATE data='$data', session_time = '$expiry', modification_time = now()";
                $results =  Session::$link->query($sql);
                break;
            case 'mongo':
                $update = Array('data' =>$data);
                unset($update['session_id']); //don't reset the key
                unset($update['_id']);
                $coll = Session::$sessionStore;
                $collection = Session::$link->$coll;
                $update['session_time'] = time();
                $res = $collection->update(
                    array('session_id' => $sid),
                    array('$set' => $update),
                    array('upsert' => true)
                );
                break;
            case 'memcache':
                if(is_array($data)) $data = json_encode($data);
                Session::$link->set($sid, $data, $expiry);
                break;
                
        }
        return true;
    }

    public function destroy($sid){
        switch(Session::$source){
            case 'mysql':
                $sql = "DELETE FROM ".Session::$sessionStore." WHERE id = '$sid'";
                $results = mysql_query($sql,  Session::$link);
                WebApplication::setCookie(Session::$sessionCookie, '', time() - 42000);
                break;
            case 'mysqli':
                $sql = "DELETE FROM ".Session::$sessionStore." WHERE id = '$sid'";
                Session::$link->query($sql);
                WebApplication::setCookie(Session::$sessionCookie, '', time() - 42000);
                break;
            case 'mongo':
                break;
            case 'memcache':
                Session::$link->delete($sid);
                break;
        }
    }

    public function gc(){
        switch(Session::$source){
            case 'mysql':
                $sql = 'DELETE FROM '.Session::$sessionStore.' WHERE session_time < \''.(time() - Session::$lifetime).'\'';
                $results = mysql_query($sql,  Session::$link);
                return;
                break;
            case 'mysqli':
                $sql = 'DELETE FROM '.Session::$sessionStore.' WHERE session_time < \''.(time() - Session::$lifetime).'\'';
                $results =  Session::$link->query($sql);
                return;
                break;
            case 'mongo':
                break;
            case 'memcache': //todo: implement me!
        }
    }
}
   
// credit to: bmorel@ssi.fr 
define('PS_DELIMITER', '|');
define('PS_UNDEF_MARKER', '!');
function session_real_decode($str){
    $str = (string)$str;
    $endptr = strlen($str);
    $p = 0;
    $serialized = '';
    $items = 0;
    $level = 0;
    while ($p < $endptr) {
        $q = $p;
        while ($str[$q] != PS_DELIMITER)
            if (++$q >= $endptr) break 2;
        if ($str[$p] == PS_UNDEF_MARKER) {
            $p++;
            $has_value = false;
        } else {
            $has_value = true;
        }
        $name = substr($str, $p, $q - $p);
        $q++;
        $serialized .= 's:' . strlen($name) . ':"' . $name . '";';
        if ($has_value) {
            for (;;) {
                $p = $q;
                switch ($str[$q]) {
                    case 'N': /* null */
                    case 'b': /* boolean */
                    case 'i': /* integer */
                    case 'd': /* decimal */
                        do $q++;
                        while ( ($q < $endptr) && ($str[$q] != ';') );
                        $q++;
                        $serialized .= substr($str, $p, $q - $p);
                        if ($level == 0) break 2;
                        break;
                    case 'R': /* reference  */
                        $q+= 2;
                        for ($id = ''; ($q < $endptr) && ($str[$q] != ';'); $q++) $id .= $str[$q];
                        $q++;
                        $serialized .= 'R:' . ($id + 1) . ';'; /* increment pointer because of outer array */
                        if ($level == 0) break 2;
                        break;
                    case 's': /* string */
                        $q+=2;
                        for ($length=''; ($q < $endptr) && ($str[$q] != ':'); $q++) $length .= $str[$q];
                        $q+=2;
                        $q+= (int)$length + 2;
                        $serialized .= substr($str, $p, $q - $p);
                        if ($level == 0) break 2;
                        break;
                    case 'a': /* array */
                    case 'O': /* object */
                        do $q++;
                        while ( ($q < $endptr) && ($str[$q] != '{') );
                        $q++;
                        $level++;
                        $serialized .= substr($str, $p, $q - $p);
                        break;
                    case '}': /* end of array|object */
                        $q++;
                        $serialized .= substr($str, $p, $q - $p);
                        if (--$level == 0) break 2;
                        break;
                    default:
                        return false;
                }
            }
        } else {
            $serialized .= 'N;';
            $q+= 2;
        }
        $items++;
        $p = $q;
    }
    return @unserialize( 'a:' . $items . ':{' . $serialized . '}' );
} 

//credit to: php@mikeboers.com
function session_real_encode( $array, $safe = true ) {
    // the session is passed as reference, even if you dont want it to
    if( $safe ) $array = unserialize(serialize( $array )) ;
    $raw = '' ;
    $line = 0 ;
    $keys = array_keys( $array ) ;
    foreach( $keys as $key ) {
        $value = $array[ $key ] ;
        $line ++ ;
        $raw .= $key .'|' ;
        if( is_array( $value ) && isset( $value['huge_recursion_blocker_we_hope'] )) {
            $raw .= 'R:'. $value['huge_recursion_blocker_we_hope'] . ';' ;
        } else {
            $raw .= serialize( $value ) ;
        }
        $array[$key] = Array( 'huge_recursion_blocker_we_hope' => $line ) ;
    }
    return $raw ;
}