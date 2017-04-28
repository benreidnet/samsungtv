<?php

require __DIR__ . '/../vendor/autoload.php';

use SamsungTV\Remote;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;

$oLogger = new Logger("remote");
$oLogger->pushHandler(new ErrorLogHandler);

$oRemote = new Remote($oLogger);
$oRemote->setHost("192.168.10.36");

$app = new Silex\Application();

$app->post("/remote/key/{key}", function($key) use ($app,$oRemote) {
	$sKey = "KEY_".strtoupper($app->escape($key));
	$oRemote->sendKey($sKey);
	return "Sent $sKey";
});

$app->run();
