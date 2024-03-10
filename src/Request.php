<?php


namespace App\Common;

use App\Common\Exception\BadRequest;
use App\Common\Exception\Unauthorized;
use App\Common\SQL\Factory;
use App\Common\SQL\mySQL\mySQL;

/**
 * Class Request
 * @package App\Common
 */
class Request {

	/**
	 * Where title, body, footer for the modal are stored.
	 * @var array
	 */
	public $modal;

	/**
	 * Classes.
	 * @var log
	 */
	public $log;

	/**
	 * @var hash
	 */
	public $hash;

	/**
	 * @var output
	 */
	public $output;

	/**
	 * @var mySQL
	 */
	public $sql;

	/**
	 * Request constructor.
	 *
	 * The requester can send their credentials
	 * (user ID, role, session ID) as an array
	 * when requesting via Process (async).
	 *
	 * @param array|null $requester
	 * @param bool|null  $api If this is an API call, set to TRUE
	 */
	function __construct(?array $requester = NULL, ?bool $api = NULL)
	{
		$this->loadSessionVars();
		$this->loadRequesterVars($requester);
		$this->log = Log::getInstance();
		$this->sql = Factory::getInstance("mySQL", $api);
		$this->hash = Hash::getInstance();
		$this->output = Output::getInstance();
		$this->pa = PA::getInstance();
	}

	/**
	 * Every time a new request is called, make sure
	 * the $_SESSION variables are translated to local variables.
	 * $user_id
	 * $role
	 * The $_SESSION variable is never sent to the client.
	 * It is stored exclusively on the server.
	 */
	private function loadSessionVars(): void
	{
		if(!is_array($_SESSION)){
			return;
		}

		# For logging purposes
		global $request_start_time;
		$request_start_time = microtime(true);

		foreach($_SESSION as $key => $val){
			$key = $key == 'PHPSESSID' ? 'session_id' : $key;
			global $$key;
			$$key = $val;
		}
	}

	/**
	 * When a request comes through via Process (async),
	 * and is about to be executed (via CLI),
	 * it will inherit the ownership of its requester.
	 *
	 * This is because otherwise the CLI processed request,
	 * will be owner-less. Which is a problem when you're
	 * managing permissions, updating databases and
	 * sending alerts.
	 *
	 * With ownership passed on, the methods can be written
	 * with permissions, and any outputs forwarded to the
	 * requester seamlessly.
	 *
	 * @param array|null $requester
	 */
	private function loadRequesterVars(?array $requester): void
	{
		if(!is_array($requester)){
			return;
		}

		foreach($requester as $key => $val){
			global $$key;
			$$key = $val;
		}
	}

	/**
	 * Handle error being sent as part of the URI,
	 * for example from oAuth.php.
	 *
	 * @param $a
	 *
	 * @return bool
	 */
	private function handleErrors($a)
	{
		extract($a);

		if(!is_array($vars)){
			return true;
		}

		if(!$vars['error']){
			return true;
		}

		$this->log->error([
			"title" => str::title(urldecode($vars['error'])),
			"message" => urldecode($vars['error_description']),
		]);

		unset($a['vars']['error']);
		unset($a['vars']['error_description']);

		$this->hash->set($a);

		return false;
	}

	/**
	 * AJAX gatekeeper
	 * The method called every time an AJAX call is received from the browser.
	 * Looks for a suitable (public or protected, NOT PRIVATE) method and runs it.
	 * The order of priority is as follows:
	 * 1. action_rel_table
	 * 2. action
	 * 3. rel_table
	 * If no method is found, an error is generated.
	 *
	 * @param $a array
	 *
	 * @return bool
	 */
	public function handler($a)
	{
		/**
		 * The method is placed in a try/catch
		 * to catch any exceptions thrown by system errors.
		 * This way, we don't need to place any try/catch
		 * structures anywhere in the code as everything will
		 * ultimately be caught here.
		 *
		 * Errors that are due to abuse, hacking, code errors,
		 * and anything else that isn't business as usual,
		 * should be reported as a system error.
		 *
		 * System errors are different from user errors,
		 * as they're not the fault of the user unless
		 * they're involved in foul play.
		 */
		try {
			if($this->preventCSRF($a)){
				$success = $this->input($a);
			}
		}
		catch(\mysqli_sql_exception $e) {
			$this->log->error([
				"icon" => "database",
				"title" => str::isDev() ? "mySQL error" : "Connection error",
				"message" => $e->getMessage(),
				"trace" => $this->getExceptionTraceAsString($e),
			]);

			# Only show the query itself in the dev environment
			if(str::isDev()){
				$this->log->info([
					"icon" => "code",
					"title" => "Query",
					"message" => $_SESSION['query'],
				]);
			}
		}
		catch(\TypeError $e) {
			$this->log->error([
				"icon" => "code",
				"title" => "Type error",
				"message" => $e->getMessage(),
				"trace" => $this->getExceptionTraceAsString($e),
			]);
		}
		catch(BadRequest $e) {
			$this->log->error([
				"log" => false, // this error is logged elsewhere already
				"icon" => "times-octagon",
				"title" => "Invalid or incomplete request",
				"message" => $e->getMessage(),
				"trace" => $this->getExceptionTraceAsString($e),
			]);
		}
		catch(Unauthorized $e) {
			$this->log->error([
				"log" => false, // this error is logged elsewhere already
				"icon" => "lock-alt",
				"title" => "Authorisation issue",
				"message" => $e->getMessage(),
				"trace" => $this->getExceptionTraceAsString($e),
			]);
		}
		catch(\Exception $e) {
			$this->log->error([
				"icon" => "ethernet",
				"title" => "System error",
				"message" => $e->getMessage(),
				"trace" => $this->getExceptionTraceAsString($e),
			]);
		}
		return $this->output($success);
	}

	/**
	 * Format the exception trace as a string.
	 *
	 * @param \Exception $exception
	 *
	 * @return string
	 * @link https://gist.github.com/abtris/1437966
	 */
	private function getExceptionTraceAsString($exception)
	{
		$rtn = "";
		$count = 0;
		foreach($exception->getTrace() as $frame){
			$args = "";
			if(isset($frame['args'])){
				$args = [];
				foreach($frame['args'] as $arg){
					if(is_string($arg)){
						$args[] = "'" . $arg . "'";
					}
					else if(is_array($arg)) {
						$args[] = "Array";
					}
					else if(is_null($arg)) {
						$args[] = 'NULL';
					}
					else if(is_bool($arg)) {
						$args[] = ($arg) ? "true" : "false";
					}
					else if(is_object($arg)) {
						$args[] = get_class($arg);
					}
					else if(is_resource($arg)) {
						$args[] = get_resource_type($arg);
					}
					else {
						$args[] = $arg;
					}
				}
				$args = join(", ", $args);
			}
			$rtn .= sprintf("#%s %s(%s): %s(%s)\n",
				$count,
				isset($frame['file']) ? $frame['file'] : 'unknown file',
				isset($frame['line']) ? $frame['line'] : 'unknown line',
				(isset($frame['class'])) ? $frame['class'] . $frame['type'] . $frame['function'] : $frame['function'],
				$args);
			$count++;
		}
		return $rtn;
	}

	/**
	 * Ensures CSRF token is valid.
	 * Prevents CSRF.
	 * @link https://markitzeroday.com/x-requested-with/cors/2017/06/29/csrf-mitigation-for-ajax-requests.html
	 *
	 * @param $a
	 *
	 * @return bool TRUE on token is valid, FALSE on token is missing or invalid.
	 * @throws \Exception
	 */
	private function preventCSRF($a): bool
	{
		# CLI commands are exempt from CSRF checks
		if(str::runFromCLI()){
			return true;
		}

		# OAuth2 redirects are exempt from CSRF checks
		if($_SERVER['REDIRECT_URL'] == "/oauth2.php"){
			return true;
		}

		# If we have been given the HTTP origin, grab the domain from there
		if($_SERVER['HTTP_ORIGIN']){
			// HTTP_ORIGIN: "https://subdomain.example.com"
			$domain = $_SERVER["HTTP_ORIGIN"];
		}

		# Or if we've been given the HTTP referer
		else if($_SERVER['HTTP_REFERER']){
			// HTTP_REFERER: "https://subdomain.example.com/folder"
			$domain = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
		}

		# HTTP_ORIGIN or HTTP_REFERER must be present
		else {
			//if we don't have the HTTP_ORIGIN or HTTP_REFERER

			# Reload to get either
			$this->hash->set("reload");

			# Return false, but don't raise an exception
			return false;
		}

		# Remove the subdomain
		$domain = substr($domain, strlen($_ENV['domain']) * -1);

		# Ensure the request was sent from our domain
		if($domain != $_ENV['domain']){
			//if this request wasn't done from our own domain
			$this->logErrorInternally("A cross domain request was attempted. {$domain} != {$_ENV['domain']}");
			throw new Unauthorized("A cross domain request was attempted.");
		}

		if(!key_exists("HTTP_X_REQUESTED_WITH", $_SERVER)){
			//if this request wasn't done via AJAX
			$this->logErrorInternally("A non XHR request was potentially attempted. Forced reload.");
			# Reload to get the XHR header
			return false;
		}

		# Ensure the request was sent from our domain via AJAX
		if($_SERVER["HTTP_X_REQUESTED_WITH"] != "XMLHttpRequest"){
			//if this request wasn't done via AJAX on our own domain
			$this->logErrorInternally("A cross domain XHR request was attempted.");
			throw new Unauthorized("A cross domain XHR request was attempted.");
		}

		if($a['action'] == "getSessionToken"){
			/**
			 * If the user is getting the session token, there is
			 * no need to check for the CSRF token, because it has
			 * yet to be generated.
			 */
			return true;
		}

		# Ensure token has been supplied
		if(!$_SERVER['HTTP_CSRF_TOKEN']){
			//if no token has been provided
			throw new Unauthorized("No CSRF token supplied.");
		}

		# Ensure token exists
		if(!$connection = $this->sql->select([
			"table" => "connection",
			"join" => [
				"table" => "geolocation",
				"on" => "ip",
			],
			"flat" => true, // To ensure a speedy response, as the join is only needed in edge cases
			"id" => $_SERVER['HTTP_CSRF_TOKEN'],
		])){
			//if the token doesn't exist
			throw new Unauthorized("Invalid CSRF token supplied.");
		}

		# Ensure token is still valid
		if($connection['closed']){
			# Refresh the connection
			$this->hash->set("reload");
			$this->log->warning([
				"title" => "Closed connection",
				"message" => "Your connection was closed. It will now be reopened.",
			]);
			return false;
		}

		# Ensure token belongs to this IP address
		if($_SERVER['REMOTE_ADDR'] && $connection['ip'] != $_SERVER['REMOTE_ADDR']){
			// If the token IP and the connecting IP are not the same
			$this->log->error([
				"display" => false,
				"log" => true,
				"title" => "Expired connection",
				"message" => "Your connection has expired ({$connection['ip']} != {$_SERVER['REMOTE_ADDR']}).
				It will now be refreshed. [The connection was not closed.]",
			]);

			/**
			 * Because this caused so much grief for people that were on connections that kept
			 * changing IP addresses, I've decided to just log the error and let the request through.
			 */

//			if(!in_array($connection['geolocation.asn.domain'], self::WHITELISTED_ASN_DOMAINS)
//				&& !in_array($connection['geolocation.asn.name'], self::WHITELISTED_ASN_NAMES)
//				&& !in_array($connection['ip'], self::WHITELISTED_IPS)){
//				//If the IP address doesn't belong to any of the whitelisted ASN domains
//				//and the IP address doesn't belong to any of the whitelisted ASN names
//				//and the IP address doesn't belong to any of the whitelisted IPs
//
//				# Refresh the connection
//				$this->hash->set("reload");
//				return false;
//			}
		}

		return true;
	}

	private function logErrorInternally(string $message): void
	{
		# Remove the ENV keys before logging the error
		foreach($_SERVER as $key => $val){
			if($_ENV[$key]){
				continue;
			}
			$server_array[$key] = $val;
		}

		# Log the error internally
		$this->log->error([
			"display" => false,
			"title" => "CSRF",
			"message" => $message
				. "\r\n\$_REQUEST " . print_r($_REQUEST, true)
				. "\$_SERVER " . print_r($server_array, true)
			,
		]);
	}

	/**
	 * Domains belonging to ASNs that cycle IP addresses.
	 * @link https://mybroadband.co.za/forum/threads/telkom-lte-constantly-changing-public-ip-addresses.952265/
	 */
	const WHITELISTED_ASN_DOMAINS = [
		"telkom.co.za",
		"afrihost.com",
		"three.co.uk",
	];

	const WHITELISTED_ASN_NAMES = [
		"Liquid Telecommunications South Africa",
	];

	const WHITELISTED_IPS = [
		# F-Wise
		"196.201.106.33",
		"41.160.141.66",
	];

	/**
	 * Handles an incoming AJAX request.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \Exception
	 */
	private function input($a)
	{
		# Handle var arrays encoded as base64 strings
		if(is_string($a['vars'])){
			$decoded_vars = str::base64_decode_url($a['vars']);
			if(is_array($decoded_vars)){
				$a['vars'] = str::base64_decode_url($a['vars']);
			}
			else {
				$a['vars'] = [];
			}
		}
		//If the string isn't a base64 encoded array, it will be completely ignored

		# Convert "-" to NULL
		$a['rel_id'] = $a['rel_id'] === "-" ? NULL : $a['rel_id'];
		$a['action'] = $a['action'] === "-" ? NULL : $a['action'];

		# Extract the vars
		extract($a);

		# Handle any errors that may be embedded into the URI
		if(!$this->handleErrors($a)){
			return false;
		}

		# Create and set the callback based on the hash
		$this->hash->setCallback($a['vars']['callback']);
		//Only explicitly set callbacks are used

		# If directions for the output have been sent with the vars
		if(is_array($vars) && $vars['div'] && $vars['div_id']){
			$this->output->set_direction($vars);
		}

		if(is_array($vars) && $vars['_uri']){
			//If the ajax call is due to a hash change
			$this->output->uri();
		}

		# If a blank request is sent, assume it's a call to go home
		if(!$rel_table
			&& !$rel_id
			&& !$action
			&& !$vars){
			$rel_table = "home";
		}

		# If *all* vars are undefined, stop the request
		if($rel_table == "undefined"
			&& $rel_id == "undefined"
			&& $action == "undefined"){
			return true;
		}

		# Undefined vars are set to NULL
		foreach(["rel_table", "rel_id,action", "vars"] as $var){
			if($$var == "undefined"){
				$$var = NULL;
			}
		}

		if(!$classPath = str::findClass($rel_table)){
			//if a class doesn't exist
			unset($a['vars']);
			throw new \Exception("No matching class for <code>" . str::generate_uri($a) . "</code> can be found.");
		}

		# Create a new instance of the class
		$classInstance = new $classPath();

		# Set the method (view is the default)
		$method = str::getMethodCase($action) ?: "view";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			unset($a['vars']);
			throw new \Exception("The <code>" . str::generate_uri($a) . "</code> method doesn't exist or is not public.");
		}

		# Make the subdomain variable global
		if($subdomain != "app"){
			$sd = $subdomain;
			global $subdomain;
			$subdomain = $sd;
		}

		if(!$classInstance->$method([
			"subdomain" => $subdomain,
			"action" => $method,
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"vars" => $vars,
		])){
			return false;
		}

		return true;
	}

	/**
	 * Print the queries that have been run.
	 * This is useful for debugging.
	 *
	 * @param bool|null   $keep_backtrace Keep the backtrace key that lists the methods that called the query
	 * @param int|null    $top            How many of the top queries to print
	 * @param string|null $order_by       What to order the queries by
	 * @param bool|null   $output_to_file If the output should be written to a file
	 *
	 * @return void
	 */
	private function printQueries(?bool $keep_backtrace = true, ?int $top = 10, ?string $order_by = "time", ?bool $output_to_file = NULL): void
	{
		foreach($_SESSION['queries'] as &$query){
			if(!$keep_backtrace){
				unset($query['backtrace']);
			}
			if($queries[$query['query_md5']]){
				$queries[$query['query_md5']]['count']++;
				$queries[$query['query_md5']]['time'] += $query['time'];
				continue;
			}
			$queries[$query['query_md5']] = $query;
			$queries[$query['query_md5']]['count'] = 1;
		}

		str::multidimensionalOrderBy($queries, [
			$order_by => "DESC",
		]);

		if($output_to_file){
			ob_start();
			print_r($_SESSION['queries']);
			file_put_contents($_ENV['tmp_dir'] . "process.log", ob_get_contents(), FILE_APPEND);
			ob_clean();
			exit;
		}

		if($top){
			$queries = array_slice($queries, 0, $top);
		}
		print_r(array_slice($queries, 0, 10));
		print_r($queries);
		exit;
	}

	/**
	 * Returns the output as a json-encoded array.
	 *
	 * @param bool|null $success
	 *
	 * @return string
	 */
	private function output(?bool $success): ?string
	{
		if($_SESSION['database_calls']){
			if($_SESSION['database_calls'] > 50){
				$this->log->info("{$_SESSION['database_calls']} database calls.");
			}
		}

		# Enable to print queries
//		$this->printQueries();

		$output = $this->output->get();

		# Make sure close requests are captured
		if($this->modal == 'close'){
			//if a modal is to be closed
			if(!$output['modal']){
				$output['modal'] = $this->modal;
			}
		}

		if($this->modal && !$output['modal']){
			//if a modal operation has been requested
			//and a modal request isn't already going
			$output['modal'] = $output['html'];
			unset($output['html']);
		}

		if($this->hash->get()){
			$output['hash'] = $this->hash->get();
		}

		# If the silent flag is to be raised
		if($silent = $this->hash->getSilent()){
			$output['silent'] = $silent;
		}

		# Alerts
		if($alerts = $this->log->getAlerts()){
			$output['alerts'] = array_merge($output['alert'] ?: [], $alerts);
		}
		/**
		 * Alerts are fed into the $output array,
		 * but have otherwise no relation to the
		 * output class.
		 */

		if($success === true){
			//not sure if this will have unintended consequences
			$output['success'] = true;
		}

		else if($this->log->hasFailures()){
			//if there are any errors
			$this->log->logFailures();
			//alert them for posterity
			$output['success'] = false;
		}

		else if($success === false){
			$output['success'] = false;
		}

		else {
			//otherwise, everything is fantastic
			$output['success'] = true;
		}

		if(str::runFromCLI() || $_SERVER['REDIRECT_URL'] == "/oauth2.php"){
			//If this is a CLI request or part of the OAuth2 runaround

			$pa = PA::getInstance();
			$pa->asyncSpeak($output);
			/**
			 * This way, CLI/OAuth2 requests are treated as normal
			 * requests and the user gets the output just
			 * as they would if this was a synchronous request.
			 */
		}
		else {
			//If this is NOT a CLI request

			# Close the database connection
			$this->sql->disconnect();

			/**
			 * CLI commands (cron jobs, etc) are exempt,
			 * as they keep using the connection, after
			 * the request has been handled.
			 */
		}

		return json_encode($output, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	}
}