<?php

use App\Common\WebSocketServer\WebSocketServer;

# Ensure the appropriate level of error reporting
error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));

define("ROOT_FOLDER",strstr(__DIR__, "vendor/", true));
//This script assumes it's found within a vendor/ subfolder.

# Autoload all vendor scripts
require_once ROOT_FOLDER.'vendor/autoload.php';

# Load global environmental variables
$dotenv = \Dotenv\Dotenv::create(ROOT_FOLDER);
$dotenv->load();

$server = new WebSocketServer();
$server->start();