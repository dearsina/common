<?php

/**
 * The following script is a WebSocket server.
 * It uses Swoole.
 */

# Ensure the appropriate level of error reporting
error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));

define("ROOT_FOLDER",strstr(__DIR__, "vendor/", true));
//This script assumes it's found within a vendor/ subfolder.

# Autoload all vendor scripts
require_once ROOT_FOLDER.'vendor/autoload.php';

# Load global environmental variables
$dotenv = Dotenv\Dotenv::create(ROOT_FOLDER);
$dotenv->load();

$server = new Swoole\WebSocket\Server($_ENV['websocket_ip'], $_ENV['websocket_port']);

$server->on("start", function (Swoole\WebSocket\Server $server) {
	echo "Swoole WebSocket Server is started at ws://{$_ENV['websocket_ip']}:{$_ENV['websocket_port']}\n";
});

$server->on('open', function(Swoole\WebSocket\Server $server, Swoole\Http\Request $request) {
	echo "connection open: {$request->fd}\n";
	var_dump($request);
//	$server->tick(1000, function() use ($server, $request) {
	$server->push($request->fd, json_encode(["hello", time()]));
//	});
});

$server->on('message', function(Swoole\WebSocket\Server $server, Swoole\WebSocket\Frame $frame) {
	echo "received message: {$frame->data}\n";
	var_dump($frame);
	$server->push($frame->fd, json_encode(["hello", time()]));
});

$server->on('close', function(Swoole\WebSocket\Server $server, int $fd) {
	echo "connection close: {$fd}\n";
});

$server->start();

/**
object(Swoole\WebSocket\Frame)#9 (5) {
["fd"]=>
int(4)
["data"]=>
string(43) "{"command":"update_data","user":"tester01"}"
["opcode"]=>
int(1)
["flags"]=>
int(33)
["finish"]=>
bool(true)
}
 */
