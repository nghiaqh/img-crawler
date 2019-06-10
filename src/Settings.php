<?php
namespace Crawler;

class Settings {

  public static $destination = "/mnt/c/Users/Nathan Quach/Hentai/";

  public static function normaliseFolderName($foldername) {
    preg_match('#, by ((?!\|).)*#', $foldername, $matches);

    if (count($matches)) {
      $foldername = str_replace($matches[0], '', $foldername);
      $artist = trim(explode('by ', $matches[0])[1]);
      $foldername = $artist . ', ' . $foldername;
    } else {
      // $foldername = preg_replace('#[\[\]]#', ',', $foldername, 2);
    }

    $foldername = preg_replace('#[":;\.<>\|\?\\*]\##i', '', $foldername);
    $foldername = preg_replace('#"#', '', $foldername);
    // $foldername = preg_replace('#[\/]#', ' ', $foldername);
    $foldername = preg_replace('#[\|]#i', '-', $foldername);
    $foldername = preg_replace('#(,)+#', ',', $foldername);
    $foldername = str_replace([
      '- Hentairules.net Image Galleries',
      '- ExHentaiorg',
      '- E-Hentai Galleries',
      '» nhentai hentai doujinshi and manga',
      '- ExHentai.org'
    ], '', $foldername);
    $foldername = trim($foldername, " \t\n\r\0\x0B,.-");

    return $foldername;
  }

  private static function getCookie($url, $params) {
    $result = self::httpPost($url,$params);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
    $cookie = '';
    foreach($m[1] as $value) {
      $cookie .= $value . ';';
    }
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
  public static function getOriginSizeImageUrl($url) {
    $url = str_replace('/_data/i/', '/', $url);
    $url = str_replace('-xx.', '.', $url);
    return $url;
  }

  // Excluding unwanted image links
  public static function isValidImageLink($url) {
    if (
      strstr($url, "newreply.php")
      || strstr($url, "index.php?showuser=")
      || strstr($url, "ads-iframe-display.php")
      || strstr($url, "img162.imagetwist.com/th/")
      || strstr($url, "imagetwist.com/imgs")
      || strstr($url, "sstatic1.histats.com")
    ) {
      return false;
    }

    return true;
  }

  public static function defineCrawlerParameters($page, $message) {
    $thumbnailContainerId = null;
    $imageContainerId = null;
    $preprocess = null;
    $cookie = '';
    $excludeLinksFunc = 'Crawler\Settings::isValidImageLink';

    if (strpos($page, 'hentairules.net') !== false) {
      $thumbnailContainerId = 'thumbnails';
      $imageContainerId = 'theImage';
      $preprocess = 'Crawler\Settings::getOriginSizeImageUrl';
    }
    else if (strpos($page, 'e-hentai.org') !== false || strpos($message, 'exhentai.org') !== false) {
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

      if (strpos($message, 'exhentai.org') !== false) {
        $cookie = self::getCookie($url, $params) . '; nw=1; uconfig=dm_t;';
      } else {
        $cookie = 'nw=1; uconfig=dm_t;';
      }
    }
    else if (strpos($page, 'nhentai.net') !== false) {
      $thumbnailContainerId = 'thumbnail-container';
      $imageContainerId = 'image-container';
    }
    else if (strpos($page, 'hentai2read.com') !== false) {
      $thumbnailContainerId = 'thumbnails';
      $imageContainerId = 'arf-reader';
    }

    return [
      $thumbnailContainerId,
      $imageContainerId,
      $preprocess,
      $cookie,
      self::$destination,
      'Crawler\Settings::normaliseFolderName',
      $excludeLinksFunc
    ];
  }
}

// Example: https://hentai2read.com/thumbnails/M2438/C35059/2/|ShindoL, TSF MONOGATARI APPEND 2|[17];
// url|folder name|array or a number
// url is the only required portion
// an array will specify pages to download & skip the rest e.g [11, 14, 15]
// a number will set a starting page to download from & skip the prior ones e.g 11
// range(1, 10) will tell apps to crawl image 1 to 10