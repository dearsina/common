<?php


namespace App\Common;

use App\Common\SQL\Factory;
use App\Common\SQL\mySQL;

class Request {

	/**
	 * Where title, body, footer for the modal are stored.
	 * @var array
	 */
	protected $modal;

	/**
	 * Classes.
	 *
	 * @var log
	 */
	protected $log;

	/**
	 * @var hash
	 */
	protected $hash;

	/**
	 * @var output
	 */
	protected $output;

	/**
	 * @var sql
	 */
	protected $sql;

	function __construct () {
		$this->loadSessionVars();
		$this->log = Log::getInstance();
		$this->hash = Hash::getInstance();
		$this->output = Output::getInstance();
		$this->sql = Factory::getInstance();
	}

	/**
	 * Every time a new request is called, make sure
	 * the $_SESSION variables are translated to local variables.
	 *
	 * $user_id
	 * $role
	 *
	 * The $_SESSION variable is never sent to the client.
	 * It is stored exclusively on the server.
	 *
	 * @return bool
	 */
	private function loadSessionVars(){
		if(!is_array($_SESSION)){
			return true;
		}

		foreach($_SESSION as $key => $val){
			global $$key;
			$$key = $val;
		}

		return true;
	}

	/**
	 * Handle error being sent as part of the URI,
	 * for example from oAuth.php.
	 *
	 * @param $a
	 *
	 * @return bool
	 */
	private function handleErrors($a){
		extract($a);

		if(!is_array($vars)){
			return true;
		}

		if(!$vars['error']){
			return true;
		}

		$this->log->error([
			"title" => str::title(urldecode($vars['error'])),
			"message" => urldecode($vars['error_description'])
		]);

		unset($a['vars']['error']);
		unset($a['vars']['error_description']);

		$this->hash->set($a);

		return false;
	}


	/**
	 * AJAX gatekeeper
	 *
	 * The method called every time an AJAX call is received from the browser.
	 * Looks for a suitable (public or protected, NOT PRIVATE) method and runs it.
	 * The order of priority is as follows:
	 * 1. action_rel_table
	 * 2. action
	 * 3. rel_table
	 *
	 * If no method is found, an error is generated.
	 *
	 * @param $a array
	 *
	 * @return bool
	 */
	public function handler($a){
//		var_dump($_SERVER);exit;
		/**
		 * The method is placed in a try/catch
		 * to catch any exceptions thrown by system errors.
		 *
		 * System errors are different from user errors,
		 * as they're not the fault of the user unless
		 * they're involved in foul play.
		 */
		try{
			$this->preventCSRF($a);
			$success = $this->input($a);
		}
		catch(\mysqli_sql_exception $e){
			$this->log->error([
				"icon" => "database",
				"title" => "mySQL error",
				"message" => $e->getMessage()
			]);
			$this->log->info([
				"icon" => "code",
				"title" => "Query",
				"message" => $_SESSION['query']
			]);
		}
		catch(\TypeError $e){
			$this->log->error([
				"icon" => "code",
				"title" => "Type error",
				"message" => $e->getMessage()
			]);
		}
		catch(\Exception $e){
			$this->log->error([
				"icon" => "ethernet",
				"title" => "System error",
				"message" => $e->getMessage()
			]);
		}
		return $this->output($success);
	}

	/**
	 * Ensures CSRF token is valid.
	 * Prevents CSRF.
	 *
	 * @link https://markitzeroday.com/x-requested-with/cors/2017/06/29/csrf-mitigation-for-ajax-requests.html
	 * @param $a
	 * @return bool TRUE on token is valid, FALSE on token is missing or invalid.
	 * @throws \Exception
	 */
	private function preventCSRF($a){
		# Ensure the request was sent from our domain
		if(substr($_SERVER["HTTP_ORIGIN"],strlen($_ENV['domain']) * -1) != $_ENV['domain']){
			//if this request wasn't done from our own domain
			throw new \Exception("A cross domain request was attempted.");
		}

		# Ensure the request was sent from our domain via AJAX
		if($_SERVER["HTTP_X_REQUESTED_WITH"] != "XMLHttpRequest"){
			//if this request wasn't done via AJAX on our own domain
			throw new \Exception("A cross domain XHR request was attempted.");
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
			throw new \Exception("No CSRF token supplied.");
		}

		# Ensure token exists
		if(!$connection = $this->sql->select([
			"table" => "connection",
			"id" => $_SERVER['HTTP_CSRF_TOKEN']
		])){
			throw new \Exception("Invalid CSRF token supplied.");
		}

		# Ensure token is still valid
		if($connection['closed']){
			throw new \Exception("Expired CSRF token supplied.");
		}

		# Ensure token belongs to this IP address
		if($connection['ip_address'] != $_SERVER['REMOTE_ADDR']){
			throw new \Exception("IP address does not match CSRF token supplied.");
		}

		return true;
	}

	/**
	 * Handles an incoming AJAX request.
	 *
	 * @param $a
	 *
	 * @return bool
	 */
	private function input($a){
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

		# Is the returning data meant to be set in a modal?
		if(is_array($vars) && $vars['modal']){
			$this->modal = $vars['modal'];
			if($this->modal != 'close'){
				$this->output->is_modal();
				$this->hash->silent();
			}
			//by default, modals don't affect the URL
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
			throw new \Exception("No matching class for <code>".str::generate_uri($a)."</code> can be found.");
		}

		# Create a new instance of the class
		$classInstance = new $classPath($this);

		# Set the method (view is the default)
		$method = str::getMethodCase($action) ?: "view";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			throw new \Exception("The <code>".str::generate_uri($a)."</code> method doesn't exist or is not public.");
		}

		if(!$classInstance->$method([
			"action" => $method,
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"vars" => $vars
		])){
			return false;
		}

		return true;
	}

	/**
	 * Returns the output as a json-encoded array.
	 * @return string
	 */
	private function output($success) {
		if($_SESSION['database_calls']){
//			$this->log->info("{$_SESSION['database_calls']} database calls.");
//			print_r($_SESSION['queries']);exit;
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

		# Silent is the same as hash = -1, but without refreshing the screen
//		if(!$output['silent'] = $this->hash->getSilent()){
		//only if there is no need to be silent will a hash be sent
		$output['hash'] = $this->hash->get();
//		}
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
		} else if($this->log->hasFailures()){
			//if there are any errors
			$this->log->logFailures();
			//log them for posterity
			$output['success'] = false;
		} else if($success === false) {
			$output['success'] = false;
		} else {
			//otherwise, everything is fantastic
			$output['success'] = true;
		}

		$this->sql->disconnect();

		return json_encode($output);
	}
}