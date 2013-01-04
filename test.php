<?php
require_once('./Session.class.php');
require_once('./shim.php');
require_once(dirname(__FILE__) . '/simpletest/autorun.php');

$db = 'test';
$host = 'localhost';
$user = 'root';
$pass = '';
$links = Array();

class TestOfMySQLSession extends UnitTestCase {
    
    function testLogIntoMySQL() {
        global $db, $host, $user, $pass, $links;
        $links['mysql'] = mysql_connect($host, $user, $pass, TRUE);
        if(!mysql_select_db($db, $links['mysql'])){
            throw(new Exception('database \''.$db.'\' could not be selected!'));
        }
        $this->assertNotNull($links['mysql']);
    }
    
    function testSavePHPModeMySQLSession(){
        global $links;
        Session::$hook = 'php';
        Session::$source = 'mysql';
        Session::$link = $links['mysql'];
        $session = new Session($links['mysql']);
        $session->set('foo', 'bar');
        $this->assertEqual($_SESSION['foo'], 'bar');
        $session->shutdown();
        $this->assertNotEqual($_SESSION['foo'], 'bar');
        $session = new Session($links['mysql']);
        $this->assertEqual($_SESSION['foo'], 'bar');
        $session->shutdown();
    }
    
    function testSaveCustomModeMySQLSession(){
        global $links;
        Session::$hook = 'custom';
        Session::$source = 'mysql';
        Session::$link = $links['mysql'];
        $session = new Session($links['mysql']);
        $session->set('foo', 'bar');
        $this->assertEqual($session->get('foo'), 'bar');
        $session->shutdown();
        $this->assertNotEqual($session->get('foo'), 'bar');
        $session = new Session($links['mysql']);
        $this->assertEqual($session->get('foo'), 'bar');
        $session->shutdown();
    }
}


class TestOfMongoSession extends UnitTestCase {
    
    function testLogIntoMongoDB() {
        global $db, $host, $user, $pass, $links;
        $connection = new Mongo($host);
        $links['mongo'] = $connection->$db;
        $this->assertNotNull($links['mongo']);
    }
    
    function testSavePHPModeMongoSession(){
        global $links;
        Session::$hook = 'php';
        Session::$source = 'mongo';
        Session::$link = $links['mongo'];
        $session = new Session($links['mongo']);
        $session->set('foo', 'bar');
        $this->assertEqual($_SESSION['foo'], 'bar');
        $session->shutdown();
        $this->assertNotEqual($_SESSION['foo'], 'bar');
        $session = new Session($links['mongo']);
        $this->assertEqual($_SESSION['foo'], 'bar');
        $session->shutdown();
    }
    
    function testSaveCustomModeMongoSession(){
        global $links;
        Session::$hook = 'custom';
        Session::$source = 'mongo';
        Session::$link = $links['mongo'];
        $session = new Session($links['mongo']);
        $session->set('foo', 'bar');
        $this->assertEqual($session->get('foo'), 'bar');
        $session->shutdown();
        $this->assertNotEqual($session->get('foo'), 'bar');
        $session = new Session($links['mongo']);
        $this->assertEqual($session->get('foo'), 'bar');
        $session->shutdown();
    }
}