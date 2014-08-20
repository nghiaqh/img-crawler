<?php
require_once dirname(__FILE__) . '/../crawler.php';
use Crawler\Crawler;

class Crawler_Test extends PHPUnit_Framework_TestCase {

    private $crawler;

    public function setUp() {
        $this->crawler = new Crawler();
    }

    public function testSendHttpGet() {
        $this->crawler->sendHttpGet('http://google.com.au');
    }

    public function testGetCookieByHttpGet() {
        $url = 'http://vnexpress.net';
        $this->crawler->getCookie($url);
    }

/*    public function testGetCookieByHttpPost() {
        $url_login = 'https://forums.e-hentai.org/index.php?act=Login&CODE=01';
        $params = array(
            'CookieDate'=>1,
            'UserName'=>'meaning', 
            'PassWord'=>'iamtheone', 
            'ipb_login_submit'=>'Login!'
            );
        $cookie = $this->crawler->getCookie($url_login, false, $params);
        $loggedin = $this->crawler->sendHttpGet('http://exhentai.org', 1, $cookie);
    }

    public function testCannotSendHttpGetWithInvalidURL() {
        $this->setExpectedException('PHPUnit_Framework_Error_Warning');
        $this->crawler->sendHttpGet();
    }*/
}