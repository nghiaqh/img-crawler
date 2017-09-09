<?php
namespace Crawler;

class Settings {

  public static $destination = "~/";

  public static function normaliseFolderName($foldername) {

    return $foldername;
  }

  public static function defineCrawlerParameters($page, $message) {
    $thumbnailContainerId = null;
    $imageContainerId = null;
    $preprocess = null;
    $cookie = '';

    return [$thumbnailContainerId, $imageContainerId, $preprocess, $cookie, self::$destination, 'Crawler\Settings::normaliseFolderName'];
  }
}
