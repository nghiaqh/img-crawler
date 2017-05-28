<?php
namespace Crawler;

use Crawler\ImageCrawler;

require_once(__DIR__ . "/../include/php-websockets/websockets.php");

/**
 * $server = new CrawlerServer('0.0.0.0', '9001');
 * try {
 *   $server->run();
 * } catch (Exception $e) {
 *	$server->stdout($e->getMessage());
 * }
 */
class CrawlerServer extends \WebsocketServer {
  private $crawler;

  public function __construct($addr, $port = 9001, $bufferLength = 2048) {
    // generate cookie for some site
    $this->cookie = $this->getCookie();
    $this->crawler = new ImageCrawler;
    $this->sizelimit = 100000;
    $this->preprocess = null;

    // call WebsocketServer constructor
    parent::__construct($addr, $port, $bufferLength);
  }

  /**
   * Main logic: parsing input pages array from message and crawl images
   * @param  [type] $user    [description]
   * @param  [String] $message [description]
   * @return [type]          [description]
   */
  protected function process ($user, $message) {
    if ($message) {
      $pages = explode(',', $message);
      if (empty($pages)) {
        $reply = json_encode(array(
          'type' => 'error',
          'message' => 'Empty input!'
        ));
        $this->send($user, $reply);
        return 0;
      }

      foreach ($pages as $pid=>$page) {
        $page = trim($page);
        $thumbnailContainerId = null;
        $imageContainerId = null;
        $this->preprocess = null;

        // Generate parameters for crawler
        if (strpos($page, 'hentairules.net') !== false) {
          $thumbnailContainerId = 'thumbnails';
          $imageContainerId = 'theImage';
          $this->preprocess = 'getOriginSizeImageUrl';
        } else if (strpos($page, 'e-hentai.org') !== false || strpos($message, 'exhentai.org') !== false) {
          $thumbnailContainerId = 'gdt';
          $imageContainerId = 'img';
          $this->cookie = $this->cookie . '; nw=1; uconfig=dm_t;';
        } else if (strpos($page, 'nhentai.net') !== false) {
          $thumbnailContainerId = 'thumbnail-container';
          $imageContainerId = 'image-container';
        }

        $this->stdout('Crawling ' . $page); // Terminal log

        $array = $this->crawler->scanForImageLinks($page, $thumbnailContainerId, $this->cookie);
        $urls = $array[1];
        $title = substr($array[2], 0, 164); //cut part of title longer than 300 characters

        // Send to client currently-being-processed page info.
        $reply = json_encode(array(
          'type' => 'page',
          'id' => $pid,
          'url' => $page,
          'title' => $title,
          'images' => $urls,
          'progress' => 0
        ));
        $this->send($user, $reply);

        // Scan image pages for actual image
        foreach ($urls as $i=>$u) {
          if (substr($u, -4) === '.jpg' || substr($u, -4) === '.png') {
            $this->downloadImage($u, $title, $i, $user);
          } else {
            foreach ($this->crawler->scanForImages($u, $imageContainerId, $this->cookie) as $image) {
              $this->downloadImage($image, $title, $i, $user);
            }
          }
        }
      }
    }

    $this->send($user, 'Completed crawling!');
    $this->stdout('Completed crawling!');
  }

  protected function downloadImage($image, $title, $i, $user) {
    if ($this->preprocess) {
      $image = call_user_func($this->preprocess, $image);
    }

    // download images have size > size limit
    if ($this->crawler->curlGetFileSize($image, $this->cookie) > $this->sizelimit) {
      $reply = json_encode(array(
        'type' => 'image',
        'id' => $i,
        'url' => $image,
        'progress' => 0
      ));
      $this->send($user, $reply);
      $this->crawler->downloadImage($image, $title, $this->cookie, array($this, 'progress'));
    }
  }

  protected function connected ($user) {
    $this->send($user, 'Connection established to 127.0.0.1:9001');
    $this->user = $user;
  }

  protected function closed ($user) {
    // Do nothing as we have no cleanup to do.
  }

  // report download progress
  public function progress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
    if ($download_size > 0) {
      $reply = json_encode(array(
        'type' => 'image progress',
        'progress' => $downloaded / $download_size  * 100
      ));
      $this->send($this->user, $reply);
    }
  }

  protected function getCookie() {
    $params = array(
    "UserName" => "meaning",
    "PassWord" => "iamtheone",
    "bt" => '',
    "b" => "d",
    "CookieDate" => 1,
    "ipb_login_submit" => "Login!"
    );

    $result = $this->httpPost("https://forums.e-hentai.org/index.php?act=Login&CODE=01",$params);
    // get cookie from e-hentai.org
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
    $cookie = '';
    foreach($m[1] as $value) {
      $cookie .= $value . ';';
    }
    echo $cookie . "\r\n";
    return $cookie;
  }

  // Send POST request
  protected function httpPost($url, $params) {
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
}
