<?php
namespace Crawler;

use Crawler\ImageCrawler;
use Crawler\Settings;

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
    $this->crawler = new ImageCrawler;
    $this->sizelimit = 10000;
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
      $pages = explode(';', $message);
      if (empty($pages)) {
        $reply = json_encode(array(
          'type' => 'error',
          'message' => 'Empty input!'
        ));
        $this->send($user, $reply);
        return 0;
      }

      foreach ($pages as $pid=>$page) {
        $tmp = explode('|', $page);
        $page = trim($tmp[0]);
        $from = sizeof($tmp) > 2 ? $tmp[2] : 0;
        // Generate parameters for crawler
        $params = Settings::defineCrawlerParameters($page, $message);
        $thumbnailContainerId = $params[0];
        $imageContainerId = $params[1];
        $this->preprocess = $params[2];
        $this->cookie = $params[3];
        $this->destination = $params[4];
        $this->normaliseFunc = $params[5];
        $this->excludeLinksFunc = $params[6];

        $this->stdout('Crawling ' . $page); // Terminal log

        $array = $this->crawler->scanForImageLinks(
          $page,
          $thumbnailContainerId,
          $this->cookie,
          $this->excludeLinksFunc
        );
        $urls = $array[1];
        $title = sizeof($tmp) > 1 ? $tmp[1] : substr($array[2], 0, 256); //cut part of title longer than 256 characters

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

        if (strpos($from, '[') === 0) {
          $from = explode(',', substr($from, 1, -1));
        }

        // single number for starting from
        // range for list picking
        // array for cherry picking
        $selectedUrls = [];

        if (is_string($from)) {
          preg_match('/^range\(([^,]+), ([^,]+)\)/', $from, $match);
          print_r($match);
          if (sizeof($match)) {
            $selectedUrls = range($match[1], $match[2]);
          } else {
            $selectedUrls = range($from, sizeof($urls));
          }
        }

        if (is_array($from)) {
          $selectedUrls = $from;
        }
        // Scan image pages for actual image
        foreach ($urls as $i=>$u) {
          $isValid = $this->excludeLinksFunc !== null
            ? call_user_func($this->excludeLinksFunc, $u)
            : 200;

          if ($from && !in_array($i+1, $selectedUrls) || !$isValid) {
            continue;
          }

          if (substr($u, -4) === '.jpg' || substr($u, -4) === '.png') {
            $this->stdout('Downloading ' . $u);
            $this->downloadImage($u, $title, $i, $user);
          } else {
            foreach ($this->crawler->scanForImages($u, $imageContainerId, $this->cookie) as $image) {
              $isValid = $this->excludeLinksFunc !== null
              ? call_user_func($this->excludeLinksFunc, $image)
              : 200;
              if ($isValid) $this->downloadImage($image, $title, $i, $user);
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
    echo 'Downloading ' . $image . PHP_EOL;

    $imgSize = $this->crawler->curlGetFileSize($image, $this->cookie);

    // download images have size > size limit
    if ($imgSize === 'unknown' || $imgSize > $this->sizelimit) {
      $reply = json_encode(array(
        'type' => 'image',
        'id' => $i,
        'url' => $image,
        'progress' => 0
      ));
      $this->send($user, $reply);

      $name_prefix = $this->normaliseFunc !== null
        ? call_user_func($this->normaliseFunc, $title)
        : $title;

      $result = $this->crawler->downloadImage(
        $image,
        $this->destination . $name_prefix,
        300,
        $this->cookie,
        array($this, 'progress'));
      echo $result . PHP_EOL;

      if (!$result) {
        $reply = json_encode(array(
          'type' => 'curl error',
          'url' => $image,
          'error' => $result
        ));
        $this->send($user, $reply);
      } else {
        $reply = json_encode(array(
          'type' => 'image progress',
          'progress' => 100
        ));
        $this->send($user, $reply);
      }
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
        'progress' => $downloaded / $download_size * 100
      ));
      $this->send($this->user, $reply);
    }
  }
}
