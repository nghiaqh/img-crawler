<?php
// require_once dirname(__FILE__) . '/../crawler.php';
require __DIR__ . '/../vendor/autoload.php';

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
        $loginUrl = 'https://someforum.org/login';
        $params = array(
            'CookieDate'=>1,
            'UserName'=>'username',
            'PassWord'=>'password',
            'ipb_login_submit'=>'Login!'
            );
        $cookie = $this->crawler->getCookie($loginUrl, 'POST', $params);
        $response = $this->crawler->sendHttpGet('https://someforum.org', array(CURLOPT_COOKIE => $cookie));
    }

}
