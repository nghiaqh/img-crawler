<?php
require_once dirname(__FILE__) . '/../crawler.php';
use Crawler\Crawler;

class Crawler_Test extends PHPUnit_Framework_TestCase {

    private $crawler;

    public function setUp() {
        $this->crawler = new Crawler();
    }

    public function testSendHttpGet() {
        $response = $this->crawler->sendHttpGet('http://php.net/manual/en/language.types.callable.php');

        $this->assertTrue(False !== $response['content']);
    }

    public function testCannotSendHttpGetWithoutURL() {
        $this->setExpectedException('PHPUnit_Framework_Error_Warning');
        $this->crawler->sendHttpGet();
    }

    public function testCannotSendHttpGetWithInvalidURL() {
        $this->setExpectedException('InvalidArgumentException', 'Please enter a valid URL');
        $this->crawler->sendHttpGet('abc@gmail.com');
    }

    public function testGetCookieByHttpGet() {
        $url = 'http://php.net/manual/en/language.types.callable.php';
        $cookie = $this->crawler->getCookie($url, 'GET');
        $this->assertTrue(strlen($cookie)>0);
        $this->assertTrue(False !== strpos($cookie, 'LAST_LANG=en;'));
    }

    public function testGetCookieByHttpPost() {
        $loginUrl = 'https://forums.e-hentai.org/index.php?act=Login&CODE=01';
        $params = array(
            'CookieDate'=>1,
            'UserName'=>'meaning', 
            'PassWord'=>'iamtheone', 
            'ipb_login_submit'=>'Login!'
            );
        $cookie = $this->crawler->getCookie($loginUrl, 'POST', $params);
        $response = $this->crawler->sendHttpGet('http://exhentai.org', array(CURLOPT_COOKIE => $cookie));
    }

}