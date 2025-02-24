<?php


namespace App\Common\API;


use App\Common\Exception\BadRequest;
use App\Common\Exception\Unauthorized;
use App\Common\Connection\Connection;
use App\Common\Exception\UnprocessableEntity;
use App\Common\ExceptionHandler;
use App\Common\Log;
use App\Common\Output;
use App\Common\SQL\Factory;
use App\Common\SQL\mySQL\mySQL;
use App\Common\str;

/**
 * Class Call
 *
 * Manages incoming API calls.
 *
 * @package App\Common
 */
class Call {

	/**
	 * @var Log
	 */
	private Log $log;

	/**
	 * @var mySQL
	 */
	private mySQL $sql;

	/**
	 * @var Output
	 */
	private Output $output;

	/**
	 * Contains the current API call connection ID.
	 *
	 * @var string
	 */
	private string $connection_id;

	public function __construct()
	{
		# Required headers to handle API calls
		header("Access-Control-Allow-Origin: *");
		header('Access-Control-Allow-Credentials', 'true');
		header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		header("Content-Type: application/json; charset=UTF-8");
		header("Access-Control-Max-Age: 60");

		# If this is a preflight request, stop right here, all it needs are the above headers
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
			exit(0);
		}

		try {
			# Fix up the $_REQUEST array
			$this->alignRequestArray();
		}

		# If the data sent was in the wrong format
		catch(BadRequest $e) {
			# The error code is the HTTP response code
			http_response_code($e->getCode());

			# The error message can be made public
			$output = [
				"status" => "Bad request",
				"error" => $e->getMessage(),
			];

			# Echo the output to the user
			echo json_encode($output);
			exit(0);
		}

		# Load the ancillary tables
		$this->log = Log::getInstance();
		$this->sql = Factory::getInstance("mySQL", true);
		$this->output = Output::getInstance();
	}

	/**
	 * For reference (from Stripe):
	 * 200 - OK    Everything worked as expected.
	 * 400 - Bad Request    The request was unacceptable, often due to missing a required parameter.
	 * 401 - Unauthorized    No valid API key provided.
	 * 402 - Request Failed    The parameters were valid but the request failed.
	 * 403 - Forbidden    The API key doesn't have permissions to perform the request.
	 * 404 - Not Found    The requested resource doesn't exist.
	 * 409 - Conflict    The request conflicts with another request (perhaps due to using the same idempotent key).
	 * 429 - Too Many Requests    Too many requests hit the API too quickly. We recommend an exponential backoff of
	 * your requests.
	 * 500, 502, 503, 504 - Server Errors
	 *
	 * @param array $a
	 */
	public function handler(array $a): void
	{
		/**
		 * The method is placed in a try/catch
		 * to catch any exceptions thrown by system errors.
		 * This way, we don't need to place any try/catch
		 * structures anywhere in the code as everything will
		 * ultimately be caught here.
		 *
		 * Custom API errors are treated together.
		 */

		try {
			# Ensure the API key exists, is valid and has been converted to $user_id/$role global variables
			$this->alignAccess();

			# Make the request
			$result = $this->request($a);

			# Get the response
			$output = $this->response($result);

			# Set the response code to OK
			http_response_code(200);
		}

			/**
			 * Unauthorized exceptions are treated separately
			 * because we will need to log the connection
			 * that wasn't captured
			 * (even though we don't have a user_id).
			 */
		catch(Unauthorized $e) {
			$this->connection_id = Connection::open();

			# The error code is the HTTP response code
			http_response_code($e->getCode());

			# The error message can be made public
			$output = [
				"status" => "Unauthorised",
				"error" => $e->getMessage(),
			];
		}

		catch(UnprocessableEntity $e){
			# The error code is the HTTP response code
			http_response_code($e->getCode());

			# The error message is already formatted as a JSON
			$output = json_decode($e->getMessage(), true);
		}

		# All other exceptions are caught here
		catch(\Exception $e) {
			# Throw a 500 Internal Server Error
			http_response_code($e->getCode());

			# Log the error for closer examination
			ExceptionHandler::logException("API", $e);

			switch($e->getCode()){
			case 400:
				$status = "Bad request";
				break;
			case 404:
				$status = "Not found";
				break;
			case 410:
				$status = "Gone";
				break;
			case 500:
				$status = "Internal server error";
				break;
			default:
				$status = "Error";
				break;
			}

			# DEV or non-500 error: Send a detailed message to the API requester
			if(str::isDev() || $e->getCode() != 500){
				$output = [
					"status" => $status,
					"error" => $e->getMessage(),
				];
			}

			# PROD: Send a generic message to the API requester
			else {
				$output = [
					"status" => $status,
					"message" => "An unexpected error has occurred. Our engineers have been notified. Please try again shortly.",
				];
			}
		}

		# Echo the output to the user
		echo json_encode($output);

		# Log the connection close, and record the response code
		Connection::close($this->connection_id, [
			"response_code" => http_response_code(),
		]);

		# Close the database connection
		$this->sql->disconnect();
	}

	/**
	 * Make the API call request.
	 * Will return a boolean TRUE or FALSE,
	 * resulting from the method run.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws BadRequest
	 */
	private function request(array $a): bool
	{
		# Extract the vars
		extract($a);

		# If a blank request is sent, assume an error
		if(!$rel_table
			&& !$rel_id
			&& !$action
			&& !$vars){
			throw new BadRequest("No request sent.");
		}

		if(!$classPath = str::findClass($rel_table)){
			//if a class doesn't exist
			throw new BadRequest("Invalid request class sent.");
		}

		# Create a new instance of the class
		$classInstance = new $classPath();

		# Set the method (view is the default)
		$method = str::getMethodCase($action) ?: "view";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			throw new BadRequest("Invalid request method sent.");
		}

		return (bool)$classInstance->$method($a);
	}

	private function response($success): array
	{
		$output = $this->output->get();

		if($output['status']){
			//if the status has already been set
		} else if($success === true){
			$output['status'] = "Success";
		} else if($this->log->hasFailures()){
			//if there are any errors
			$this->log->logFailures();
			//alert them for posterity
			$output['status'] = "Error";
		} else if($success === false){
			$output['status'] = "Error";
		}

		return $output;
	}

	/**
	 * Ensure API key is valid and active.
	 * Set the global subscription ID.
	 *
	 * @throws \App\Common\Exception\Unauthorized
	 */
	private function alignAccess(): void
	{
		# Ensure the Authorization header has been sent
		if(!$this->getAuthorizationHeader()){
			throw new Unauthorized("No Authorization header sent.");
		}

		# Ensure a valid Bearer token has been sent
		if(!$api_key = $this->getBearerToken()){
			throw new Unauthorized("No Bearer token sent.");
		}

		# Ensure the API key is valid
		if(!$subscription = $this->sql->select([
			"table" => "subscription",
			"where" => [
				"api_key" => $api_key,
			],
			"limit" => 1,
		])){
			throw new Unauthorized("The API key supplied is not valid.", "Invalid API key [{$api_key}] supplied");
		}

		# Ensure the subscription is active
		if(!in_array($subscription['status'], ["trial", "active", "closing", "closing"])){
			throw new Unauthorized(
			"The subscription is {$subscription['status']} and the API is no longer accessible.",
			"Subscription ID [{$subscription['subscription_id']}] API still active, subscription is {$subscription['status']}.");
		}

		# Make the subscription ID itself global
		global $subscription_id;
		$subscription_id = $subscription['subscription_id'];

		# Log the API call connection
		$this->connection_id = Connection::open();
	}

	/**
	 * Checks to see if the authorization header exists,
	 * returns the value if it does.
	 *
	 * @return string|null
	 */
	private function getAuthorizationHeader(): ?string
	{
		if(isset($_SERVER['Authorization'])){
			return trim($_SERVER["Authorization"]);
		}

		# Nginx or fast CGI
		if(isset($_SERVER['HTTP_AUTHORIZATION'])){
			return trim($_SERVER["HTTP_AUTHORIZATION"]);
		}

		# Apache
		if(function_exists('apache_request_headers')){
			$headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
			if(isset($headers['authorization'])){
				return trim($headers['authorization']);
			}
		}

		return NULL;
	}

	/**
	 * Get bearer token if it exists.
	 *
	 * @return string|null
	 * @throws \App\Common\Exception\Unauthorized
	 */
	function getBearerToken(): ?string
	{
		# Ensure a header was sent
		if(!$token = $this->getAuthorizationHeader()){
			return NULL;
		}

		if(preg_match('/Bearer\s(\S+)/', $token, $matches)){
			// If the string is prefixed with "Bearer ", remove it
			$token = $matches[1];
		}

		# Ensure the token is not just "Bearer"
		if(strpos($token, "Bearer") !== FALSE){
			return NULL;
		}

		# Ensure the token is a UUID
		if(!str::isUuid($token)){
			throw new Unauthorized("Unrecognisable API token.", "API key [{$this->getAuthorizationHeader()}] attempted sent.");
		}

		return $token;
	}

	/**
	 * Aligns the $_REQUEST array from the API request
	 * to mimic that of an AJAX request.
	 */
	private function alignRequestArray(): void
	{
		if($_GET){
			throw new BadRequest("Send requests as multipart/form-data via POST, instead of as GET query parameters.");
		}

		# Explode the URL
		$hash = array_filter(explode("/", $_SERVER['REDIRECT_URL']));

		# Reset the $_REQUEST array
		unset($_REQUEST);

		# The first section is always the rel_table
		$_REQUEST['rel_table'] = $hash[1];

		# Depending on whether the second section is a UUID or not, it's either a rel_id (UUID) or action (non-UUID)
		if(\App\Common\str::isUuid($hash[2])){
			//We have to check for this because API calls without rel_ids are problematic
			$_REQUEST['rel_id'] = $hash[2];
			# If there is a rel_id, check to see if there is a third variable
			if($hash[3]){
				//In which case that's the action
				$_REQUEST['action'] = $hash[3];
			}
		} else {
			//If the second variable is NOT a UUID, then it must be an action
			$_REQUEST['action'] = $hash[2];
		}

		# All posted key-value pairs and all files are joined together as vars
		$_REQUEST['vars'] = array_merge($_POST, $_FILES);
	}
}