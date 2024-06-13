<?php


namespace App\Common\WebSocketServer;

use App\Common\Prototype;
use App\Common\Log;
use App\Common\SQL\Factory;
use App\Common\str;

/**
 * Class WebSocketServer
 * Run as a service to provide WebSocket support.
 * Is based on Swoole.
 *
 * Needs to be executed from the command line.
 *
 * Requires the following $_ENV variables to be set:
 *
 * local_cert="/etc/letsencrypt/live/DOMAIN/cert.pem"     # Local SSL Certificate path
 * local_pk="/etc/letsencrypt/live/DOMAIN/privkey.pem"    # Local SSL Private Key path
 * websocket_external_ip="0.0.0.0"                        # Should always be set to 0.0.0.0
 * websocket_external_port="8080"                         # The port used for WSS, can be anything, but must match in JS
 * websocket_internal_ip="127.0.0.1"                      # Should always be set to 127.0.0.1
 * websocket_internal_port="8080"                         # The internal port, can be anything
 */
class WebSocketServer extends Prototype {
	/**
	 * Holds the actual server object.
	 *
	 * @var \Swoole\WebSocket\Server
	 */
	private $external_server;

	/**
	 * This is the port that only internal connections can use.
	 *
	 * @var \Swoole\WebSocket\Server
	 */
	private $internal_server;

	/**
	 * A DateTime string acting as server ID.
	 * Used to identify settings
	 * @var false|string
	 */
	private $server_id;

	public function __construct()
	{
		# Ensure this class is only accessed from the command line
		if(!str::runFromCLI()){
			//If it's not accessed from the command line, abort.
			die("You cannot access the WebSocket Server from the browser.");
		}
		return parent::__construct();
	}

	/**
	 * Write the alert to the log file.
	 *
	 * @param $msg
	 */
	private function alert($msg)
	{
		$line = "\r\n" . date("Y-m-d H:i:s") . " | " . $msg;

		if(!$log_file = $_ENV['websocket_log_file']){
			// If for some reason the $_ENV variables haven't been loaded
			$log_file = "/var/log/swoole.alert";
			// Have a backup
		}

		$f = fopen($log_file, "a");
		fwrite($f, $line);
		fclose($f);
	}

	/**
	 * Run this method to kickstart the webserver.
	 * It is running as a daemon, so the script will complete,
	 * even if the server continues.
	 *
	 * You can set the package_max_length option to increase the
	 * maximum length limit. But this takes more memory, because
	 * Swoole-HTTP-Server keeps all requested data in memory,
	 * not disk.
	 *
	 * @return void
	 * @link https://github.com/swoole/swoole-src/issues/3125
	 */
	public function start(): void
	{
		# Ensure the server isn't already running
		if($this->serverAlreadyRunning()){
			$this->log->success("The server is already running.");
			return;
		}

		# Set up the server
		$this->external_server = new \Swoole\WebSocket\Server($_ENV['websocket_external_ip'], $_ENV['websocket_external_port'], SWOOLE_BASE, SWOOLE_SOCK_TCP | SWOOLE_SSL);

		# Set the SSL keys
		$this->external_server->set([
			# SSL keys
			'ssl_cert_file' => $_ENV['local_cert'],
			'ssl_key_file' => $_ENV['local_pk'],

			# Log file
			"log_file" => $_ENV['websocket_log_file'],

			# Run as daemon
			"daemonize" => true,

			# Raise the message size limit from the default 2mb to 32mb
			"package_max_length" => 32 * 1024 * 1024,
		]);

		# Generate the server ID
		$this->server_id = date("YmdHis");

		# The internal port
		if(!$this->internal_server = $this->external_server->listen($_ENV['websocket_internal_ip'], $_ENV['websocket_internal_port'], SWOOLE_SOCK_TCP)){
			//if we're unable to create a listener on localhost:808
			$this->alert("Unable to create a WebSocket listener on {$_ENV['websocket_internal_ip']}:{$_ENV['websocket_internal_port']}. Ensure the port is open.");
			return;
		}

		# Server start
		$this->external_server->on("start", [$this, 'onStart']);

		# Internal connections
		$this->internal_server->on("connect", [$this, 'onInternalConnect']);
		$this->internal_server->on("open", [$this, 'onInternalOpen']);
		$this->internal_server->on("message", [$this, 'onInternalMessage']);
		$this->internal_server->on("close", [$this, 'onInternalClose']);

		# External connections
		$this->external_server->on("connect", [$this, 'onExternalConnect']);
		$this->external_server->on("open", [$this, 'onExternalOpen']);
		$this->external_server->on("message", [$this, 'onExternalMessage']);
		$this->external_server->on("close", [$this, 'onExternalClose']);

		# Start the server
		$this->external_server->start();
	}

	/**
	 * Checks to see whether a given socket is occupied or not.
	 *
	 * @return bool
	 */
	private function serverAlreadyRunning(): bool
	{
		@stream_socket_client("tcp://{$_ENV['websocket_external_ip']}:{$_ENV['websocket_external_port']}", $errno, $errstr, 5);
		return !$errstr;
	}

	/**
	 * On Server start.
	 *
	 * @param \Swoole\WebSocket\Server $server
	 */
	public function onStart(\Swoole\WebSocket\Server $server)
	{
		$message = "Swoole WebSocket Server [{$this->server_id}] has started at wss://{$_ENV['websocket_external_ip']}:{$_ENV['websocket_external_port']}";

		# Log it to the admins
		$this->alert($message);

		# Log it internally. Set to info so that it triggers a log entry.
		$this->log->info([
			"icon" => "server",
			"title" => "Swoole WebSocket Server",
			"message" => $message,
		]);
	}

	/**
	 * When an internal script attempt to connect to the WebSocket Server.
	 *
	 * @param \Swoole\WebSocket\Server $server
	 * @param int                      $fd
	 */
	public function onInternalConnect(\Swoole\WebSocket\Server $server, int $fd)
	{
		$this->alert("Internal connection [{$fd}] attempt.");
	}

	/**
	 * When an internal script connection has opened.
	 *
	 * @param \Swoole\WebSocket\Server $server
	 * @param \Swoole\Http\Request     $request
	 */
	public function onInternalOpen(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request)
	{
		$this->alert("Internal connection [{$request->fd}] opened.");
	}

	/**
	 * When an internal script sends a message.
	 * Expects the data being sent ($frame->data) to be
	 * in the following format:
	 * <code>
	 * [fd] => array containing one or more connections to send the message to
	 * [data] => array containing data to send to the connection(s)
	 * </code>
	 *
	 * @param \Swoole\WebSocket\Server $server
	 * @param \Swoole\WebSocket\Frame  $frame
	 *
	 * @return bool
	 * @return bool
	 */
	public function onInternalMessage(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame): bool
	{
		$this->alert("Internal message from connection [{$frame->fd}]: {$frame->data}");

		# Break open the data string into an array
		$data_array = json_decode($frame->data, true);

		if(!$data_array['fd']){
			$this->alert("No recipients identified.");
			return true;
		}

		# For each recipient (fd), send the (data) message
		foreach($data_array['fd'] as $id => $fd){
			if($server->isEstablished($fd)){
				$server->push($fd, json_encode($data_array['data']));
			}
			else {
				echo "The [{$fd}] connection is no longer established, thus the following message was not sent to them:\r\n" . json_encode($data_array['data']);
			}

		}

		return true;
	}

	/**
	 * When an internal connection closes.
	 *
	 * @param \Swoole\WebSocket\Server $server
	 * @param int                      $fd
	 */
	public function onInternalClose(\Swoole\WebSocket\Server $server, int $fd)
	{
		$this->alert("Internal connection [{$fd}] closed.");
	}

	/**
	 * When an external client is connecting.
	 *
	 * @param \Swoole\WebSocket\Server $server
	 * @param int                      $fd
	 */
	public function onExternalConnect(\Swoole\WebSocket\Server $server, int $fd)
	{
		$this->alert("External connection [{$fd}] attempt.");
	}

	/**
	 * When an external connection has opened.
	 *
	 * $request->server contains the following:
	 * <code>
	 * [
	 *    'request_method' => 'GET',
	 *    'request_uri' => '/9832d6dc-9093-4233-af47-ebb878a48ba6',
	 *    'path_info' => '/9832d6dc-9093-4233-af47-ebb878a48ba6',
	 *    'request_time' => 1673443811,
	 *    'request_time_float' => 1673443811.507008,
	 *    'server_protocol' => 'HTTP/1.1',
	 *    'server_port' => 8080,
	 *    'remote_port' => 51574,
	 *    'remote_addr' => '169.0.137.120', // Will always be an IPv4 address
	 *    'master_time' => 1673443811,
	 * ]
	 * </code>
	 *
	 * @param \Swoole\WebSocket\Server $server
	 * @param \Swoole\Http\Request     $request
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \Exception
	 */
	public function onExternalOpen(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request): bool
	{
		# The browser will send along the connection ID
		$connection_id = substr($request->server['request_uri'], 1);
		//The request_uri includes a prefixed "/"

		# Make sure the connection ID exists, is valid and is for the right IP
		if(!$this->sql->update([
			"table" => "connection",
			"set" => [
				"server_id" => $this->server_id,
				"fd" => $request->fd,
				"opened" => "NOW()",
			],
			"where" => [
				"closed" => NULL,
				//				"ip" => $request->server['remote_addr'],
				/**
				 * For now, we cannot use the IP address because
				 * it is always an IPv4 address, while the connection
				 * table does at times use an IPv6 address.
				 */
			],
			"id" => $connection_id,
			"reconnect" => true,
			"user_id" => false,
		])){
			//If the connection is not valid
			var_dump($this->log->getAlerts());
			$this->log->clearAlerts();
			return false;
		}

		$this->alert("External connection [{$request->fd}] opened with IP [{$request->server['remote_addr']}], connection_id [{$connection_id}]");

		return true;
	}

	/**
	 * When an external connection sends a message to the server,
	 * it is caught here. Nothing is done with it at the moment.
	 * Jury still out on whether AJAX or WebSockets should be used
	 * to send messages from a client browser to the server.
	 *
	 * @param \Swoole\WebSocket\Server $server
	 * @param \Swoole\WebSocket\Frame  $frame
	 */
	public function onExternalMessage(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame): void
	{
		$this->alert("External message from connection [{$frame->fd}]: {$frame->data}");
	}

	/**
	 * @param \Swoole\WebSocket\Server $server
	 * @param int                      $fd
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function onExternalClose(\Swoole\WebSocket\Server $server, int $fd): bool
	{

		# Mark the connection as closed on the database
		if(!$this->sql->update([
			"table" => "connection",
			"set" => [
				"closed" => "NOW()",
			],
			"where" => [
				"server_id" => $this->server_id,
				"fd" => $fd,
			],
			"user_id" => false,
			"limit" => 1,
			"reconnect" => true,
		])){
			//The connection close was not saved
			var_dump($this->log->getAlerts());
			$this->log->clearAlerts();
			return false;
		}

		$this->alert("External connection [{$fd}] closed.");

		return true;
	}
}


/**
 * object(Swoole\Http\Request)#24 (8) {
 * ["fd"]=>
 * int(1)
 * ["header"]=>
 * array(12) {
 * ["host"]=>
 * string(28) "registerofmembers.co.uk:443"
 * ["connection"]=>
 * string(7) "Upgrade"
 * ["pragma"]=>
 * string(8) "no-cache"
 * ["cache-control"]=>
 * string(8) "no-cache"
 * ["user-agent"]=>
 * string(115) "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163
 * Safari/537.36"
 * ["upgrade"]=>
 * string(9) "websocket"
 * ["origin"]=>
 * string(35) "https://app.registerofmembers.co.uk"
 * ["sec-websocket-version"]=>
 * string(2) "13"
 * ["accept-encoding"]=>
 * string(17) "gzip, deflate, br"
 * ["accept-language"]=>
 * string(62) "en-GB,en;q=0.9,en-US;q=0.8,nb;q=0.7,sv;q=0.6,la;q=0.5,no;q=0.4"
 * ["sec-websocket-key"]=>
 * string(24) "mditpwx7MZazIDiVZHMylg=="
 * ["sec-websocket-extensions"]=>
 * string(42) "permessage-deflate; client_max_window_bits"
 * }
 * ["server"]=>
 * array(10) {
 * ["request_method"]=>
 * string(3) "GET"
 * ["request_uri"]=>
 * string(27) "/ui9d070pnpnhnq0togicn6sifc"
 * ["path_info"]=>
 * string(27) "/ui9d070pnpnhnq0togicn6sifc"
 * ["request_time"]=>
 * int(1587559801)
 * ["request_time_float"]=>
 * float(1587559801.4001)
 * ["server_protocol"]=>
 * string(8) "HTTP/1.1"
 * ["server_port"]=>
 * int(443)
 * ["remote_port"]=>
 * int(55358)
 * ["remote_addr"]=>
 * string(13) "105.242.32.54"
 * ["master_time"]=>
 * int(1587559800)
 * }
 * ["cookie"]=>
 * array(1) {
 * ["PHPSESSID"]=>
 * string(26) "5b3312js9eeellfeh5c9mr2nll"
 * }
 * ["get"]=>
 * NULL
 * ["files"]=>
 * NULL
 * ["post"]=>
 * NULL
 * ["tmpfiles"]=>
 * NULL
 * }
 */