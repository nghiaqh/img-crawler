<?php

require_once(__DIR__.'/../socket-server.php');

$port = $argv[1] ? $argv[1] : '9001';
$server = new CrawlerServer('0.0.0.0', $port);

try {
	$server->run();
} catch (Exception $e) {
	$server->stdout($e->getMessage());
}
