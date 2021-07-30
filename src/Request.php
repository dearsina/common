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

		foreach($_SESSION as $key => $val){
			$key = $key == 'PHPSESSID' ? 'session_id' : $key;
			global $$key;
			$$key = $val;
		}
	}

	/**
	 * When a request comes thru via Process (async),
	 * and is about to be executed (via CLI),
	 * it will inherit the ownership of it's requester.
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
				"title" => "mySQL error",
				"message" => $e->getMessage(),
			]);
			$this->log->info([
				"icon" => "code",
				"title" => "Query",
				"message" => $_SESSION['query'],
			]);
		}
		catch(\TypeError $e) {
			$this->log->error([
				"icon" => "code",
				"title" => "Type error",
				"message" => $e->getMessage(),
			]);
		}
		catch(BadRequest $e) {
			$this->log->error([
				"silent" => true, // this error is logged elsewhere already
				"icon" => "times-octagon",
				"title" => "Invalid or incomplete request",
				"message" => $e->getMessage(),
			]);
		}
		catch(Unauthorized $e) {
			$this->log->error([
				"silent" => true, // this error is logged elsewhere already
				"icon" => "lock-alt",
				"title" => "Authorisation issue",
				"message" => $e->getMessage(),
			]);
		}
		catch(\Exception $e) {
			$this->log->error([
				"icon" => "ethernet",
				"title" => "System error",
				"message" => $e->getMessage(),
			]);
		}
		return $this->output($success);
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

		# Ensure the request was sent from our domain
		if(substr($_SERVER["HTTP_ORIGIN"], strlen($_ENV['domain']) * -1) != $_ENV['domain']){
			//if this request wasn't done from our own domain
			throw new Unauthorized("A cross domain request was attempted. {$_SERVER["HTTP_ORIGIN"]} != {$_ENV['domain']}");
		}

		# Ensure the request was sent from our domain via AJAX
		if($_SERVER["HTTP_X_REQUESTED_WITH"] != "XMLHttpRequest"){
			//if this request wasn't done via AJAX on our own domain
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
			"id" => $_SERVER['HTTP_CSRF_TOKEN'],
		])){
			throw new Unauthorized("Invalid CSRF token supplied.");
		}

		# Ensure token is still valid
		if($connection['closed']){
			$this->hash->set("reload");
			$this->log->warning([
				"title" => "Expired connection",
				"message" => "Your connection has expired. It will now be refreshed.",
			]);
			return false;
			//			throw new Unauthorized("Expired CSRF token supplied.");
		}

		# Ensure token belongs to this IP address
		if($connection['ip'] != $_SERVER['REMOTE_ADDR']){
			$this->hash->set("reload");
			$this->log->warning([
				"title" => "Expired connection",
				"message" => "Your connection has expired. It will now be refreshed.",
			]);
			return false;
			//			throw new \Exception("IP address does not match CSRF token supplied.");
		}

		return true;
	}

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
	 * Returns the output as a json-encoded array.
	 *
	 * @param $success
	 *
	 * @return string
	 */
	private function output($success): ?string
	{
		if($_SESSION['database_calls']){
//						$this->log->info("{$_SESSION['database_calls']} database calls.");
//						var_dump(Info::getInstance()->info);
//						print_r($_SESSION['queries']);
//						exit;
		}

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

		$output['silent'] = $this->hash->getSilent();
		# TEMP I think we can send a hash always
		# No, otherwise the silent flag means nothing

		# Alerts
		$output['alerts'] = $this->log->getAlerts();
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

		if(str::runFromCLI()){
			//If this is a CLI request

			$pa = PA::getInstance();
			$pa->asyncSpeak($output);
			/**
			 * This way, CLI requests are treated as normal
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