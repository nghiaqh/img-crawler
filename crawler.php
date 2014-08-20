<?php
namespace Crawler;

/**
 * Crawler class
 * Defines web crawler using cURL library to fetch content from URL
 *
 * PHP 5.5
 *
 * @author  Nathan Quach <nathanquach8x@gmail.com>
 * @version 0.0.1
 * @license The MIT License
 */
class Crawler {

    public $userAgent;

    function _construct($userAgent=null) {
        $this->userAgent = $userAgent? $userAgent : 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36';
    }

    private function execute($options) {
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        
        $content = curl_exec($ch);
        $err     = curl_errno($ch);
        $errmsg  = curl_error($ch);
        $info    = curl_getinfo($ch);

        curl_close($ch);

        return array('content'=>$content, 
                     'errcode'=>$err, 
                     'errmsg'=>$errmsg,
                     'info'=>$info
                     );
    } 

    private function setOptions($url, $args) {

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Please enter a valid URL');
        }

        $base = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_PROGRESSFUNCTION => $this->progress,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_COOKIE => ''
                );

        if(is_array($args) && array_diff_key($args,array_keys(array_keys($args)))) {
            return array_merge($base, $args);
        }

        return $base;
    }

    public function progress($ch, $totalbytes, $downloaded, $upload, $uploaded) {
        return 0;
    }

    public function sendHttpGet($url, $args=null) {
        $options = $this->setOptions($url, $args);
        return $this->execute($options);
    }

    public function sendHttpPost($url, $params, $args=null) {
        $base = $this->setOptions($url, $args);

        $postData = '';
        if(is_array($params) && array_diff_key($params,array_keys(array_keys($params)))) {
            foreach ($params as $key => $value) {
                $postData .= $key.'='.$value.'&';    
            }
            rtrim($postData, '&');
        }

        $post = array(
            CURLOPT_POST => count($postData),
            CURLOPT_POSTFIELDS => $postData,
            );

        $options = array_merge($base, $post);
        return $this->execute($options);
    }


    public function getCookie($url, $get=true, $params=null) {
        $cookie = '';
        if($get === true) {
            $response = $this->sendHttpGet($url, 1);
        }
        else {
            $response = $this->sendHttpPost($url, $params, 1);
        }
        
        $fa = fopen('dump.txt','w'); 
        fwrite($fa, json_encode($response['info'])); 
        fclose($fa);
        
        preg_match_all('/^Set-Cookie: (.*?);/m', $response['content'], $m);  
        foreach($m[1] as $value) {
            $cookie .= $value.';';
        }      
        return $cookie;
    }
}