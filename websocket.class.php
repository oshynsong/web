<?php
/**
 *@project    Websocket (server endpoint by php implements)
 *@author     Oshyn Song <dualyangsong@gmail.com>
 *@version    1.0beta
 *@time       2014-3
 *@usage      (new WebSocket($address, $port))->run()
 */

class WebSocket
{
	var $master;
	var $sockets = array();
	var $users = array();
	var $debug = true;
	
	protected $address = 'localhost';
	protected $port = 8080;

	/**
	 *@name   constructor
	 *@access public
	 */
	public function __construct($a, $p)
	{
		if ($a == 'localhost')
			$this->address = $a;
		else if (preg_match('/^[\d\.]*$/is', $a))
			$this->address = long2ip(ip2long($a));
		else
			$this->address = $p;
		
		if (is_numeric($p) && intval($p) > 1024 && intval($p) < 65536)
			$this->port = $p;
		else
			die ("Not valid port:" . $p);
		
		$this->createSocket();
		array_push($this->sockets, $this->master);
	}
	
	/**
	 *@name    run
	 *@desc    wait for the client to connect and process
	 */
	public function run()
	{
		while(true)
		{
			$socketArr = $this->sockets;
			$write = NULL;
			$except = NULL;
			socket_select($socketArr, $write, $except, NULL);  //select the socket with message automaticly
			
			//if handshake choose the master
			foreach ($socketArr as $socket)
			{
				if ($socket == $this->master)
				{
					$client = socket_accept($this->master);
					if ($client < 0)
					{
						$this->log("socket_accept() failed");
						continue;
					}
					else
					{
						$this->connect($client);
					}
				}
				else
				{
					$this->log("----------New Frame Start-------");
					$bytes = @socket_recv($socket,$buffer,2048,0);
					if ($bytes == 0)
					{
						$this->disconnect($socket);
					}
					else
					{
						$user = $this->getUserBySocket($socket);
						if (!$user->handshake)
						{
							$this->doHandShake($user, $buffer);
						}
						else
						{
							$this->process($user, $buffer); 
						}
					}
				}
			}
		}
	}
	
	/**
	 *@name    process
	 *@desc    Simple process method just return the info from the client,
	 *         this can be override in real application by extends this class
	 */
	protected function process($user, $msg)
	{
		$msg = $this->unwrap($user->socket, $msg);
		$this->say('< ' . $msg);
		$this->send($user->socket, $msg);
	}	
	
	public function send($client, $msg)
	{
		$this->say("> Resource id:" . substr('' . $client, 13) . ' ' . $msg);
		$msg = $this->wrap($msg);
		@socket_write($client, $msg, strlen($msg));
		$this->log("! " . strlen($msg));
	}
		
	/**
	 *@name   connect
	 *@access public
	 *@desc   connect to the client and push the client socket into array
	 */
	public function connect($clientSocket)
	{
		$user = new User();
		$user->id = uniqid();
		$user->socket = $clientSocket;
		array_push($this->users,$user);
		array_push($this->sockets,$clientSocket);
		$this->log($user->socket . " CONNECTED!" . date("Y-m-d H-i-s"));
	}
	
	/**
	 *@name     disconnect
	 *@access   public 
	 *@desc     disconnect to the specific client socket
	 */
	public function disconnect($clientSocket)
	{
		$found = null;
		$n = count($this->users);
		for($i = 0; $i<$n; $i++)
		{
			if($this->users[$i]->socket == $clientSocket)
			{ 
				$found = $i;
				break;
			}
		}
		$index = array_search($clientSocket,$this->sockets);
		
		if(!is_null($found))
		{ 
			array_splice($this->users, $found, 1);
			array_splice($this->sockets, $index, 1); 
			
			socket_close($clientSocket);
			$this->say($clientSocket." DISCONNECTED!");
		}
	}
	
	/**
	 *@name     getHeaders
	 *@access   public 
	 *@param    string req
	 *@return   array  ($resource,$host,$origin,$key)
	 */
	public function getHeaders($req)
	{
		$r = $h = $o = null;
		if(preg_match("/GET (.*) HTTP/"   , $req, $match))
			$r = $match[1];
		if(preg_match("/Host: (.*)\r\n/"  , $req, $match))
			$h = $match[1];
		if(preg_match("/Origin: (.*)\r\n/", $req, $match))
			$o = $match[1];
		if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match))
			$key = $match[1];
			
		return array($r, $h, $o, $key);
	}

	protected function unwrap($clientSocket, $msg="")
	{ 
		$opcode = ord(substr($msg, 0, 1)) & 0x0F;
		$payloadlen = ord(substr($msg, 1, 1)) & 0x7F;
		$ismask = (ord(substr($msg, 1, 1)) & 0x80) >> 7;
		$maskkey = null;
		$oridata = null;
		$decodedata = null;
		
		//close socket
		if ($ismask != 1 || $opcode == 0x8)
		{
			$this->disconnect($clientSocket);
			return null;
		}
		
		//get the masking key and masked data
		if ($payloadlen <= 125 && $payloadlen >= 0)
		{
			$maskkey = substr($msg, 2, 4);
			$oridata = substr($msg, 6);
		}
		else if ($payloadlen == 126)
		{
			$maskkey = substr($msg, 4, 4);
			$oridata = substr($msg, 8);
		}
		else if ($payloadlen == 127)
		{
			$maskkey = substr($msg, 10, 4);
			$oridata = substr($msg, 14);
		}
		$len = strlen($oridata);
		for($i = 0; $i < $len; $i++)   //decode the masked data
		{
			$decodedata .= $oridata[$i] ^ $maskkey[$i % 4];
		}		
		return $decodedata; 
	}

	protected function wrap($msg="", $opcode = 0x1)
	{
		//control bit, default is 0x1(text data)
		$firstByte = 0x80 | $opcode;
		$encodedata = null;
		$len = strlen($msg);
		
		if (0 <= $len && $len <= 125)
			$encodedata = chr(0x81) . chr($len) . $msg;
		else if (126 <= $len && $len <= 0xFFFF)
		{
			$low = $len & 0x00FF;
			$high = ($len & 0xFF00) >> 8;
			$encodedata = chr($firstByte) . chr(0x7E) . chr($high) . chr($low) . $msg;
		}
		
		return $encodedata;			
	}
	
	private function createSocket()
	{
		$this->master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)
			or die("socket_create() failed:".socket_strerror(socket_last_error()));
			
		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)
			or die("socket_option() failed".socket_strerror(socket_last_error()));
			
		socket_bind($this->master, $this->address, $this->port)
			or die("socket_bind() failed".socket_strerror(socket_last_error()));
			
		socket_listen($this->master,20)
			or die("socket_listen() failed".socket_strerror(socket_last_error()));
		
		$this->say("Server Started : ".date('Y-m-d H:i:s'));
		$this->say("Master socket  : ".$this->master);
		$this->say("Listening on   : ".$this->address." port ".$this->port."\n");
		
	}
	
	private function doHandShake($user, $buffer)
	{
		$this->log("\nRequesting handshake...");
		$this->log($buffer);
		list($resource, $host, $origin, $key) = $this->getHeaders($buffer);
		
		//websocket version 13
		$acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
		
		$this->log("Handshaking...");
		$upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
					"Upgrade: websocket\r\n" .
					"Connection: Upgrade\r\n" .
					"Sec-WebSocket-Accept: " . $acceptKey . "\r\n\r\n";  //必须以两个回车结尾
		$this->log($upgrade);
		$sent = socket_write($user->socket, $upgrade, strlen($upgrade));
		$user->handshake=true;
		$this->log("Done handshaking...");
		return true;
	}
	
	private function getUserBySocket($socket)
	{
		$found=null;
		foreach($this->users as $user)
		{
			if ($user->socket == $socket)
			{
				$found = $user;
				break;
			}
		}
		return $found;
	}
	
	public function say($msg = "")
	{
		echo $msg . "\n";
	}
	
	public function log($msg = "")
	{
		if ($this->debug)
		{
			echo $msg . "\n";
		} 
	}
}

class User
{
	var $id;
	var $socket;
	var $handshake;
}
?>
