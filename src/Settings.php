<?php
namespace Crawler;

class Settings {

  public $destination = "/media/nathan/D/";

  public static function normaliseFolderName($foldername) {
    $foldername = preg_replace('#\]#', ',', $foldername);
    $foldername = preg_replace('#- E-Hentai Galleries#i', '', $foldername);
    $foldername = preg_replace('#- ExHentaiorg#i', '', $foldername);
    $foldername = preg_replace('#Hentairulesnet#i', '', $foldername);
    return $foldername;
  }

  private static function getCookie($url, $params) {
    $result = self::httpPost($url,$params);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
    $cookie = '';
    foreach($m[1] as $value) {
      $cookie .= $value . ';';
    }
    echo $cookie . "\r\n";
    return $cookie;
  }

  // Send POST request
  private static function httpPost($url, $params) {
    $postData = '';
    //create name value pairs seperated by &
    foreach($params as $k => $v) {
      $postData .= $k . '='.$v.'&';
    }

    rtrim($postData, '&');

    $user_agent =
      'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.71 Safari/537.36';

    $ch = curl_init();

    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HEADER, 1);
    curl_setopt($ch,CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_POST, count($postData));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $output=curl_exec($ch);

    curl_close($ch);
    return $output;
  }

  // Get url of full size image of hentairules.net
  private static function getOriginSizeImageUrl($url) {
    $url = str_replace('/_data/i/', '/', $url);
    $url = str_replace('-xx.', '.', $url);
    return $url;
  }

  public static function defineCrawlerParameters($page, $message) {
    $thumbnailContainerId = null;
    $imageContainerId = null;
    $preprocess = null;
    $cookie = '';

    if (strpos($page, 'hentairules.net') !== false) {
      $thumbnailContainerId = 'thumbnails';
      $imageContainerId = 'theImage';
      $preprocess = array(self, 'getOriginSizeImageUrl');
    } else if (strpos($page, 'e-hentai.org') !== false || strpos($message, 'exhentai.org') !== false) {
      $thumbnailContainerId = 'gdt';
      $imageContainerId = 'img';
      $url = "https://forums.e-hentai.org/index.php?act=Login&CODE=01";
      $params = array(
        "UserName" => "meaning",
        "PassWord" => "iamtheone",
        "bt" => '',
        "b" => "d",
        "CookieDate" => 1,
        "ipb_login_submit" => "Login!"
      );
      $this->cookie = self::getCookie($url, $params) . '; nw=1; uconfig=dm_t;';
    }

    return [$thumbnailContainerId, $imageContainerId, $preprocess, $cookie];
  }
}
