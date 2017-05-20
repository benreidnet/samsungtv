<?php

/**
 * Simple REST to websocket bridge to present the remote control interface as
 * service that can be called via POST from other home automation systems
 * without them needing to be websocket aware
 */

require __DIR__ . '/../vendor/autoload.php';

use SamsungTV\Remote;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Symfony\Component\Yaml\Yaml;


$oLogger = new Logger("remote");
$oLogger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM,logger::INFO));

$aConfig = parse_ini_file(dirname(__DIR__)."/remote.ini");

$oRemote = new Remote($oLogger);
$oRemote->setHost($aConfig['targetip']);



$app = new Silex\Application();

$app->post("/samsung/remote/key/{key}", function($key) use ($app,$oRemote) {
	$sKey = "KEY_".strtoupper($app->escape($key));
	$oRemote->sendKey($sKey);
	return "Sent $sKey\n";
});

$app->run();
