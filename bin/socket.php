<?php
require __DIR__ . '/../vendor/autoload.php';
require_once(__DIR__.'/../src/CrawlerServer.php');

$port = $argv[1] ? $argv[1] : '9001';
$server = new CrawlerServer('0.0.0.0', $port);

try {
	$server->run();
} catch (Exception $e) {
	$server->stdout($e->getMessage());
}
