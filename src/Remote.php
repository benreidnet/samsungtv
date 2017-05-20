<?php

namespace SamsungTV;

use React\EventLoop\Factory as ReactFactory;
use React\EventLoop\LoopInterface;
use Ratchet\Client\Connector;
use Psr\Log\LoggerInterface;

/**
 * Remote control class for Samsung 2016+ TVs using the websocket interface
 */
class Remote
{
	/**
	 * @var LoggerInterface
	 * Logger for debugging
	 */
	private $logger;

	/**
	 * @var string
	 * Host to connect to
	 */
	private $sHost;

	/**
	 * @var integer
	 * Port to connect to
	 */
	private $iPort = 8001;

	/**
	 * @var string
	 * Application name
	 */
	private $sAppName = "PHP Remote";

	/**
	 * @var array
	 * Queue of keypresses
	 */
	private $aKeyQueue;


	/**
	 * @var array
	 * Array of valid keys that can be sent. 
	 * This list is taken from https://github.com/Bntdumas/SamsungIPRemote/blob/master/samsungKeyCodes.txt
	 */
	private $aValidKeys = array(
		"0","1","2","3","4","5","6","7","8","9","11","12","4_3","16_9","3SPEED","AD","ADDDEL","ALT_MHP","ANGLE","ANTENA","ANYNET","ANYVIEW","APP_LIST","ASPECT",
		"AUTO_ARC_ANTENNA_AIR","AUTO_ARC_ANTENNA_CABLE","AUTO_ARC_ANTENNA_SATELLITE","AUTO_ARC_ANYNET_AUTO_START","AUTO_ARC_ANYNET_MODE_OK","AUTO_ARC_AUTOCOLOR_FAIL",
		"AUTO_ARC_AUTOCOLOR_SUCCESS","AUTO_ARC_CAPTION_ENG","AUTO_ARC_CAPTION_KOR","AUTO_ARC_CAPTION_OFF","AUTO_ARC_CAPTION_ON","AUTO_ARC_C_FORCE_AGING",
		"AUTO_ARC_JACK_IDENT","AUTO_ARC_LNA_OFF","AUTO_ARC_LNA_ON","AUTO_ARC_PIP_CH_CHANGE","AUTO_ARC_PIP_DOUBLE","AUTO_ARC_PIP_LARGE","AUTO_ARC_PIP_LEFT_BOTTOM",
		"AUTO_ARC_PIP_LEFT_TOP","AUTO_ARC_PIP_RIGHT_BOTTOM","AUTO_ARC_PIP_RIGHT_TOP","AUTO_ARC_PIP_SMALL","AUTO_ARC_PIP_SOURCE_CHANGE","AUTO_ARC_PIP_WIDE","AUTO_ARC_RESET",
		"AUTO_ARC_USBJACK_INSPECT","AUTO_FORMAT","AUTO_PROGRAM","AV1","AV2","AV3","BACK_MHP","BOOKMARK","CALLER_ID","CAPTION","CATV_MODE","CHDOWN","CH_LIST","CHUP","CLEAR",
		"CLOCK_DISPLAY","COMPONENT1","COMPONENT2","CONTENTS","CONVERGENCE","CONVERT_AUDIO_MAINSUB","CUSTOM","CYAN","DEVICE_CONNECT","DISC_MENU","DMA","DNET","DNIe","DNSe",
		"DOOR","DOWN","DSS_MODE","DTV","DTV_LINK","DTV_SIGNAL","DVD_MODE","DVI","DVR","DVR_MENU","DYNAMIC","ENTERTAINMENT","ESAVING","EXT1","EXT10","EXT11","EXT12","EXT13",
		"EXT14","EXT15","EXT16","EXT17","EXT18","EXT19","EXT2","EXT20","EXT21","EXT22","EXT23","EXT24","EXT25","EXT26","EXT27","EXT28","EXT29","EXT3","EXT30","EXT31","EXT32",
		"EXT33","EXT34","EXT35","EXT36","EXT37","EXT38","EXT39","EXT4","EXT40","EXT41","EXT5","EXT6","EXT7","EXT8","EXT9","FACTORY","FAVCH","FF","FF_","FM_RADIO","GAME","GREEN",
		"GUIDE","HDMI","HDMI1","HDMI2","HDMI3","HDMI4","HELP","HOME","ID_INPUT","ID_SETUP","INFO","INSTANT_REPLAY","LEFT","LINK","LIVE","MAGIC_BRIGHT","MAGIC_CHANNEL","MDC",
		"MENU","MIC","MORE","MOVIE1","MS","MTS","MUTE","NINE_SEPERATE","OPEN","PANNEL_CHDOWN","PANNEL_CHUP","PANNEL_ENTER","PANNEL_MENU","PANNEL_POWER","PANNEL_SOURCE",
		"PANNEL_VOLDOW","PANNEL_VOLUP","PANORAMA","PAUSE","PCMODE","PERPECT_FOCUS","PICTURE_SIZE","PIP_CHDOWN","PIP_CHUP","PIP_ONOFF","PIP_SCAN","PIP_SIZE","PIP_SWAP","PLAY",
		"PLUS100","PMODE","POWER","POWEROFF","POWERON","PRECH","PRINT","PROGRAM","QUICK_REPLAY","REC","RED","REPEAT","RESERVED1","RETURN","REWIND","REWIND_","RIGHT","RSS",
		"RSURF","SCALE","SEFFECT","SETUP_CLOCK_TIMER","SLEEP","SOURCE","SRS","STANDARD","STB_MODE","STILL_PICTURE","STOP","SUB_TITLE","SVIDEO1","SVIDEO2","SVIDEO3","TOOLS",
		"TOPMENU","TTX_MIX","TTX_SUBFACE","TURBO","TV","TV_MODE","UP","VCHIP","VCR_MODE","VOLDOWN","VOLUP","WHEEL_LEFT","WHEEL_RIGHT","W_LINK","YELLOW","ZOOM1","ZOOM2",
		"ZOOM_IN","ZOOM_MOVE","ZOOM_OUT"
	);

	/**
	 * Constructor takes a logger for debugging
	 * @param LoggerInterface $logger Logger (eg. monolog)
	 */
	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * Set the host to connect to 
	 * Should probably be an IP as the libraries use global DNS rather than your local resolver
	 * @param string $sHost Hostname or IP address
	 * @return Remote Return $this to allow for fluid interface
	 */
	public function setHost($sHost)
	{
		$this->sHost = $sHost;
		return $this;
	}

	/**
	 * Set the port to connect to (defaults to 8001)
	 * @param integer $iPort Port to use
	 * @return Remote Return $this to allow fluid interface
	 */
	public function setPort($iPort)
	{
		$this->iPort = $iPort;
		return $this;
	}

	/**
	 * Set the application name to identify this App to the TV as
	 * (You may need to authorised this application throguh the TV interface)
	 * @param string $sAppName
	 * @return Remote Return $this to allow fluid interface
	 */
	public function setAppName($sAppName)
	{
		$this->sAppName = $sAppName;
		return $this;
	}

	/**
	 * Validate a key against the list of valid keys
	 * @param string $sKey
	 * @returns boolean True if valid else false
	 */
	private function validateKey($sKey)
	{
		if (substr($sKey,0,4) == "KEY_")
			$sKey = substr($sKey,5);
		return in_array($sKey,$this->aValidKeys);
	}

	/**
	 * Create the JSON message to send in the websocket request
	 */
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


	/**
	 * Add a keypress to the queue
	 * @param string $sKey Key to add
	 * @param float $fDelay Delay after keypress before next key
	 */
	public function queueKey($sKey,$fDelay = 1.0)
	{
		if (!$this->validateKey($sKey))
			throw new \UnexpectedValueException("Invalid key: $sKey");

		$this->aQueue[] = array("key"=>$sKey,"delay"=>$iDelay);
	}

	/**
	 * Wrapper function to send an individual key to the TV
	 * @param string $sKey Key to send
	 */
	public function sendKey($sKey)
	{
		$this->queueKey($sKey);
		$this->send();
	}

	/**
	 * Send queued keypresses to TV
	 */
	public function sendKeys()
	{
		if (count($this->aQueue) == 0)
		{
			$this->logger->warn("No keys to send");
			return;
		}

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

					// queue up the keys in advance with a delay between each
					$iTimer = 0;

					while(($aKeyDef = array_pop($this->aQueue)) !== NULL)
					{
						$sKey = $aKeyDef['key'];
						$loop->addTimer($iTimer,function () use ($conn,$sKey) {
							$jsonMessage = $this->getKeypressMessage($sKey);
							$this->logger->debug("Sending $sKey...");
							$conn->send($jsonMessage);
						});

						$iTimer = $iTimer += $aKeyDef['delay'];
					}

					// once all keys have been sent, disconnect the socket
					$loop->addTimer($iTimer,function () use ($conn) {
						$this->logger->debug("Closing websocket");		
						$conn->close();
					});
				}
				else
				{
					$this->logger->error("Unknown message: $msg");
					throw new RemoteException("Unknown message received: $msg");
				}
			});
		

		}, function ($e) {
			$this->logger->error("Could not connect: {$e->getMessage()}");
			throw new RemoteException("Could not connect: ".$e->getMessage(),NULL,$e);
		});

		$loop->run();

	}
}
