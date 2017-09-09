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

        // Generate parameters for crawler
        $params = Settings::defineCrawlerParameters($page, $message);
        $thumbnailContainerId = $params[0];
        $imageContainerId = $params[1];
        $this->preprocess = $params[2];
        $this->cookie = $params[3];
        $this->destination = $params[4];
        $this->normaliseFunc = $params[5];

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
      $this->crawler->downloadImage($image, $this->destination, $this->normaliseFunc, $title, $this->cookie, array($this, 'progress'));
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
}
