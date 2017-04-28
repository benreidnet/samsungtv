<?php

namespace SamsungTV;

use React\EventLoop\Factory as ReactFactory;
use React\EventLoop\LoopInterface;
use Ratchet\Client\Connector;
use Psr\Log\LoggerInterface;

class Remote
{
	private $logger;
	private $sHost;
	private $iPort = 8001;
	private $sAppName = "PHP Remote";

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function setHost($sHost)
	{
		$this->sHost = $sHost;
		return $this;
	}

	public function setPort($iPort)
	{
		$this->iPort = $iPort;
		return $this;
	}

	public function setAppName($sAppName)
	{
		$this->sAppName = $sAppName;
		return $this;
	}

	private function getKeypressMessage($sKey)
	{
		$aMessage = array(
			"method" => "ms.remote.control",
			"params" => array(
				"Cmd" => "Click",
				"DataOfCmd" => $sKey,
				"Option" => false,
				"TypeOfRemote" => "SendRemoteKey"
			)
		);
		$jsonMessage = json_encode($aMessage,JSON_PRETTY_PRINT);
		return $jsonMessage;

	}

	public function sendKey($sKey)
	{
		$this->sendKeys(array($sKey));
	}

	public function sendKeys($aKeys)
	{
		$sAppName = utf8_encode(base64_encode($this->sAppName));
		$sURL = "ws://{$this->sHost}:{$this->iPort}/api/v2/channels/samsung.remote.control?name=$sAppName";

		$this->logger->debug("Connecting to $sURL");

		$loop = ReactFactory::create();
		$connector = new Connector($loop);
		$subProtocols = [];
		$headers = [];
		$connector($sURL,$subProtocols,$headers)->then(function(\Ratchet\Client\WebSocket $conn) use ($aKeys,$loop) {
			$conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn,$loop,$aKeys) {
				$oMsg = json_decode($msg);
				if ($oMsg->event == "ms.channel.connect")
				{
					$this->logger->debug("Connected");
					$iTimer = 0;
					foreach($aKeys AS $sKey)
					{
						$loop->addTimer($iTimer++,function () use ($conn,$sKey) {
							$jsonMessage = $this->getKeypressMessage($sKey);
							$this->logger->debug("Sending $sKey...");
							$conn->send($jsonMessage);
						});
					}
					$loop->addTimer($iTimer,function () use ($conn) {
						$this->logger->debug("Closing websocket");		
						$conn->close();
					});
				}
				else
				{
					$this->logger->error("Unknown message: $msg");
				}
			});
		

		}, function ($e) {
			$this->logger->error("Could not connect: {$e->getMessage()}");
		});

		$loop->run();

	}
}
