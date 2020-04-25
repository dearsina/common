<?php


namespace App\Common\User;

use App\Common\Email\Email;
use App\Common\Hash;
use App\Common\Log;
use App\Common\Output;
use App\Common\PA;
use App\Common\SQL\mySQL;
use App\Common\str;
use App\UI\Page;

class User{
	/**
	 * The threshold where which a user's action is ignored.
	 * The recommended default is 0.5.
	 */
	const recaptchaThreshold = 0.5;

	/**
	 * @var mySQL
	 */
	private $sql;

	/**
	 * @var Log
	 */
	private $log;

	/**
	 * @var Hash
	 */
	private $hash;

	/**
	 * @var Output
	 */
	private $output;

	/**
	 * @var PA
	 */
	private $pa;

	/**
	 * The constructor is private so that the class can be run in static mode
	 */
//	private function __construct() {
	function __construct() {
		$this->sql = mySQL::getInstance();
		$this->log = Log::getInstance();
		$this->hash = Hash::getInstance();
		$this->output = Output::getInstance();
		$this->pa = PA::getInstance();
	}

//	private function __clone() {
//		// Stopping Clonning of Object
//	}
//
//	private function __wakeup() {
//		// Stopping unserialize of object
//	}

	/**
	 * @return Card
	 */
	private function card(){
		return new Card();
	}

//	/**
//	 * @return User
//	 */
//	public static function getInstance() {
//		// Check if instance is already exists
//		if(self::$instance == null) {
//			self::$instance = new User();
//		}
//		return self::$instance;
//	}

	/**
	 * Login form
	 * Presented to the user when wanting to log in, or when credentials have expired.
	 *
	 * @param null $a
	 *
	 * @return bool
	 */
	public function login($a = NULL){
		extract($a);

		$page = new Page();

		$page->setGrid([
			"class" => "justify-content-md-center",
			"html" => [
				"sm" => 4,
				"class" => "fixed-width-column",
				"html" => $this->card()->login($a)
			]
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Cleans out all variables and sets to hash to logout,
	 * which will trigger a clean up of the UI also.
	 *
	 * @param      $a
	 * @param bool $silent If TRUE, no message will be sent to the user
	 *
	 * @return bool
	 */
	public function logout($a, $silent = NULL){
		extract($a);

		# User is actively logged in
		global $user_id;

		# User is logged in via cookies only
		if(!$user_id
			&& $_COOKIE['session_id']
			&& $user = $this->sql->select([
				"table" => "user",
				"where" => [
					"session_id" => $_COOKIE['session_id'],
					"user_id" => $_COOKIE['user_id'],
				],
				"where_not" => [
					"session_id" => "NULL"
				],
				"limit" => 1
			])) {
			/**
			 * If the user has been logged out of sessions,
			 * but still has an active cookie, this is an
			 * alternative way of getting their user_id.
			 */
			$user_id = $user['user_id'];
		}

		$this->hash->set([
			"rel_table" => "user",
			"action" => "login"
		]);

		if(!$user_id){
			//if there is still no user id
			if(!$silent){
				$this->log->info([
					"icon" => "power-off",
					'title' => 'You are already logged off',
					'message' => "You are already logged off and don't need to do it again.",
				]);
			}
			return true;
		}

		# Ensure cookie related variables are cleared
		if (!$this->sql->update([
			"table" => "user",
			"set" => [
				"session_id" => NULL,
				"key" => NULL
			],
			"id" => $user_id
		])) {
			return false;
		}

		$remove = ['user_id','role','session_id'];
		foreach ($remove as $var) {
			global $$var;
			unset($GLOBALS[$var]); //If a globalized variable is unset() inside of a function, only the local variable is destroyed.
			unset($_SESSION[$var]);
			unset($_COOKIE[$var]);
			setcookie($var, '', time() - 3600, '/');
			unset($$var);
		}
		if(!$silent){
			$this->log->info([
				"icon" => "power-off",
				'title' => 'Good bye!',
				'message' => 'You have been logged out.',
			]);
		}
		return true;
	}

	/**
	 * Registering a new user.
	 *
	 * @param $a
	 *
	 * @return bool
	 */
	public function new($a){
		if(is_array($a))
			extract($a);

		$page = new Page([
//			"title" => "Register",
//			"subtitle" => "Fill in the details below to register.",
//			"icon" => "user-plus"
		]);

		$page->setGrid([
			"class" => "justify-content-md-center",
			"html" => [
				"sm" => 4,
				"class" => "fixed-width-column",
				"html" => $this->card()->new($a)
			]
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Checks to see if each one of the fields
	 * is found in the vars, or else raises an error,
	 * unless set to silent.
	 *
	 * @param array $fields Numerical array of variable key names
	 * @param array $vars Variable key-value pairs to look for fields in
	 * @param null  $silent If set to TRUE, will supress any errors on error.
	 *
	 * @return bool Returns TRUE if all fields are present, FALSE if otherwise
	 */
	private function formComplete(array $fields, array $vars, $silent = NULL){
		if(empty($fields)){
			return true;
		}
		foreach($fields as $field){
			if($vars[$field]){
				continue;
			}
			if(!$silent){
				$field = str::title($field);
				$this->log->error([
					"title" => "{$field} missing",
					"message" => "The {$field} value is missing."
				]);
				$missing_field = true;
			}
		}

		if($missing_field){
			return false;
		}

		return true;
	}

	/**
	 * Insert new user.
	 *
	 * @param $a
	 *
	 * @return bool|int|mixed
	 */
	public function insert($a){
		extract($a);

		$this->log->info($a['vars']['number']);
		return false;

		# Ensure form is complete
		if(!$this->formComplete(["first_name","last_name","email","phone"], $vars)){
			return false;
		}

		# Ensure the email address is valid
		if(!str::isValidEmail($vars['email'])){
			$this->log->error([
				"headline" => 'Invalid E-mail Address',
				"msg" => "This email address [<code>{$vars['email']}</code>] is invalid. Please ensure you've written the correct address."
			]);
			return false;
		}

		# Check to see if the user has already registered (based on email alone)
		if($user = $this->sql->select([
			"table" => "user",
			"where" => [
				"email" => $vars['email']
			],
			"limit" => 1
		])){
			//User has already registered

			# The user has a verified account
			if($user['verified']){
				$this->log->info([
					"title" => "You have already registered",
					"message" => "You already have an account with {$_ENV['title']}. No need to re-register, just log in."
				]);
				$this->hash->set([
					"rel_table" => $rel_table,
					"action" => "login",
					"variables" => [
						"email" => $vars['email']
					]
				]);
				return true;
			}

			# The user has an unverified account
			$this->log->warning([
				"title" => "Registered but not verified yet",
				"message" => "You have already reigstered with {$_ENV['title']}, but your email address has yet to be verified."
			]);
			$this->hash->set([
				"rel_table" => $rel_table,
				"rel_id" => $rel_id,
				"action" => "verificationEmailSent"
			]);
			return true;
		}




		if(!$insert_id = $this->sql->insert([
			"table" => $rel_table,
			"set" => $vars,
		])){
			return false;
		}




		extract($a['variables']);



		# Ensure the phone number is correct
		if($number){
			$phone = $number;
		}
		if(!$phone = $this->is_a_valid_phone_number($phone)){
			return false;
		}

		# Ensure the user has filled in both first and last names
		if(!$first_name || !$last_name){
			$this->log->error("Please ensure you have filled in both first and last name.");
			return false;
		}

		# Insert the new user, get the new user_id
		$user_id = $this->sql->insert([
			"table" => 'user',
			"set" => [
				"facebook_id" => $facebook_id,
				"first_name" => $first_name,
				"last_name" => $last_name,
				"email" => $email,
				"phone" => $phone
			]
		]);

		# Create the link between the user and the type
		$user_role_id = $this->sql->insert([
			"table" => "user_role",
			"set" => [
				"user_id" => $user_id,
				"rel_table" => "user",
				"rel_id" => $user_id
			]
		]);

		# Send verification email
		if(!$this->send_verification_email([
			"rel_table" => "user",
			"rel_id" => $user_id,
			"variables" => [
				"email" => $email
			]
		])){
			return false;
		}

		return $user_id;
	}

	/**
	 * Reset password form
	 * Presented to the user if they wish to reset their password.
	 *
	 * @param null $a
	 *
	 * @return bool
	 */
	public function resetPassword($a = NULL){
		if(is_array($a))
			extract($a);

		$page = new Page();

		$page->setGrid([
			"class" => "justify-content-md-center",
			"html" => [
				"sm" => 4,
				"class" => "fixed-width-column",
				"html" => $this->card()->resetPassword($a)
			]
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Logs the IP and prints the session ID.
	 */
	public function getWebSocketToken(){
		$this->getGeolocation();
		echo session_id(); exit;
	}

	/**
	 * Given an IP address,
	 * returns geolocation details.
	 *
	 * @param string $ip
	 *
	 * @return bool|mixed
	 */
	public function getGeolocation(string $ip = NULL){
		$ip = $ip ?: $_SERVER['REMOTE_ADDR'];
		//if an IP isn't explicitly given, use the requester's IP

		# Most of the time, the data already exists
		if($geolocation = $this->sql->select([
			"table" => "geolocation",
			"where" => [
				"ip" => $ip
			],
			"limit" => 1
		])){
			return $geolocation;
		}

		# If the IP data doesn't exist, get it from ipStack, this requires a key
		if(!$_ENV['ipstack_access_key']){
			//if a key hasn't been set, no data can be gathered
			return false;
		}

		$client = new \GuzzleHttp\Client([
			"base_uri" => "http://api.ipstack.com/"
		]);

		try {
			$response = $client->request("GET", $ip, [
				"query" => [
					"access_key" => $_ENV['ipstack_access_key'],
				]
			]);
		}
		catch (\Exception $e) {
			//Catch errors
			$this->log->error($e->getMessage());
			return false;
		}

		# Get the content as a flattened array to insert as a new row
		$array = json_decode($response->getBody()->getContents(), true);

		# Remove location as it adds complexity that we don't need right now
		unset($array['location']);

		# Flatten (don't really need this, as the data *should* be flat already)
		$set = str::flatten($array);

		if(!$this->sql->insert([
			"table" => "geolocation",
			"set" => $set,
			"grow" => true,
			"audit_trail" => false
		])){
			return false;
		}

		# Try again, this time the data will have been loaded
		return $this->getGeolocation($ip);
	}

	/**
	 * Confirms the reCAPTCHA resposne with Google,
	 * and returns a score between 0 and 1, or NULL
	 * if no score has been received.
	 *
	 * <code>
	 * array (
	 * 	'success' => false,
	 * 	'hostname' => 'app.registerofmembers.co.uk',
	 * 	'challenge_ts' => '2020-04-22T08:10:13Z',
	 * 	'apk_package_name' => NULL,
	 * 	'score' => 0.9,
	 * 	'action' => 'reset_password',
	 * 	'error-codes' => array (
	 * 		0 => 'timeout-or-duplicate',
	 * 		1 => 'hostname-mismatch',
	 * 		2 => 'action-mismatch',
	 *   ),
	 * )
	 * </code>
	 *
	 * @param string $response
	 * @param string $action
	 *
	 * @return float|bool
	 */
	private function getRecaptchaScore($response, $action){
		if(!$_ENV['recaptcha_key'] || !$_ENV['recaptcha_secret']){
			//if either recaptcha_key or secret has not been assigned, ignore by returing a perfect score
			return 1;
		}

		$recaptcha = new \ReCaptcha\ReCaptcha($_ENV['recaptcha_secret']);
		$resp = $recaptcha->setExpectedHostname($_SERVER['HTTP_HOST'])
			->setExpectedAction($action)
			->verify($response, $_SERVER['REMOTE_ADDR']);

		$resp_array = $resp->toArray();
//		$this->log->info(str::pre($resp_array));

		if ($resp_array['success']) {
			//If the recapcha was a success
			return $resp_array['score'];
		}

		return false;
	}

	public function sendResetPasswordEmail($a = NULL){
		if(is_array($a))
			extract($a);

		# Get the reCAPTCHA score
		$score = $this->getRecaptchaScore($vars['recaptcha_response'], "reset_password");

		if(!$score){
			//If a score was not received
			$this->hash->set([
				"rel_table" => $rel_table,
				"action" => "reset_password",
				"vars" => [
					"email" => $vars['email']
				]
			]);
			$this->log->warning([
				"message" => "Please try again."
			]);
			return true;
		}

		if($score < self::recaptchaThreshold){
			//if the score is below the reCAPTCHA threshold
			$this->log->error("Computer says no.");
			return false;
		}

		if(!$user = $this->sql->select([
			"table" => "user",
			"where" => [
				"email" => $email
			],
			"limit" => 1
		])){
			//If the given email address cannot be found.
			$this->pa->speak([
				"type" => "alert",
				"colour" => "error",
				"title" => "Email not found",
				"message" => "The given email address cannot be found."
			]);
			return false;
		}

		if(!$user['verified']){
			$this->pa->speak([
				"type" => "alert",
				"colour" => "warning",
				"title" => "Email not verified",
				"message" => "Verify your account first, you should have received a verification email when you signed up."
			]);
			return false;
		}

		# Create a random key
		$key = str::uuid();

		# Store the key for reference
		if(!$this->sql->update([
			"table" => "user",
			"id" => $user['user_id'],
			"set" => [
				"key" => $key
			],
			"user_id" => false
		])){
			return false;
		}
		
		$variables = [
			"user_id" => $user['user_id'],
			"email" => $user['email'],
			"key" => $key 
		];
		
		$email = new Email();
		try {
			$email->template("reset_password", $variables)
				->to([$user['email'] => $user['name']])
				->send();
		}
		catch (\Exception $e){
			$this->log->error($e->getMessage());
			return false;
		}

		$this->log->info([
			"container" => ".card-body",
			"type" => "alert",
			"colour" => "success",
			"title" => "Reset email sent",
			"message" => "An email has been sent with a link to reset the password. Please check your inbox. If you cannot find it, please check your spam/junk folder, as it may have ended up there."
		]);

		return true;
	}


	/**
	 * Checks to see if the user is logged in, or if there is a session user_id variable that can be used
	 *
	 * @return int|bool Returns the user_id of the user that's currently logged in or FALSE if user is not logged in
	 */
	public function isLoggedIn() {
		global $user_id;
		global $role;

		if ($user_id || $user_id = $_SESSION['user_id']) {
			//if the user logged in (is there a session user_id?
			if($role || $role = $_SESSION['role']){
				//if the user role has been set
				return $user_id;
			}
		}

		# If no local variables are stored (session expired), check to see if any cookies are stored
		if($this->LoadCookies()){
			return $this->isLoggedIn();
		}
		return false;
	}

	/**
	 * Checks to see if the user *is* a certain role (or one among a range of role).
	 *
	 * @param string|array $a One or many roles to check for
	 *
	 * @return bool|int TRUE if the current user's current role is one of the roles requested
	 */
	function is($a){
		if(!$this->isLoggedIn()){
			return false;
		}
		global $user_id;
		global $role;

		# Get the global values, failing that, the session values
		$user_id = $user_id ?: $_SESSION['user_id'];
		$role = $role ?: $_SESSION['role'];

		if (!$user_id || !$role) {
			//a user has to be logged in, a role has to be defined, and the user has to exist
			return false;
		}

		$roles_requested = is_array($a) ? $a : [$a];

		# If the current user's current role is in the roles requested
		if(in_array($role, $roles_requested)){
			return true;
		}

		# If the user is being controlled by an admin, they also have admin privileges
		if($admin_user_id = $this->isControlledByAdmin()){
			if(in_array("admin", $roles_requested)){
				return  true;
			}
		}

		return false;
	}

	function verifyCredentials($a){
		extract($a);

		# Check to see if the given email address can be found
		if(!$user = $this->sql->select([
			"table" => "user",
			"where" => [
				"email" => $vars['email']
			],
			"limit" => 1
		])){
			$this->log->error([
				"container" => ".card-body",
				"title" => 'Email not found',
				"message" => "The email address <code>{$vars['email']}</code> has not been registered with {$_ENV['title']}. Are you sure have registered?",
			]);
			return false;
		}
	}

	public function verificationEmailSent($a){

	}

	/**
	 * If no local (session) variables are stored,
	 * meaning, the session expired,
	 * check to see if there are any cookie variables
	 * stored, and if so, revive the session.
	 *
	 * @return bool
	 */
	private function LoadCookies(){
		global $user_id;
		global $role;
		global $seat_id;

		if(!$_COOKIE['session_id']){
			return false;
		}

		if(!$user = $this->sql->select([
			"table" => "user",
			"where" => [
				"session_id" => $_COOKIE['session_id'],
				"user_id" => $_COOKIE['user_id'],
			],
			"where_not" => [
				"session_id" => "NULL"
			],
			"limit" => 1
		])) {
			//if the session_id and user id from the db matches what's in the cookie
			return false;
		}

		# Message to the user returning
		$this->log->info([
			"message" => "Welcome back!"
		]);

		# Repopulate the session variables
		$_SESSION['user_id'] = $user['user_id'];
		$_SESSION['role'] = $user['last_role'];
		$_SESSION['seat_id'] = $user['last_seat_id'];

		# Repopulate the global variables
		$user_id = $user['user_id'];
		$role = $user['last_role'];
		$seat_id = $user['last_seat_id'];

		# Update the cookie
		$this->storeCookies();

		return true;
	}

	/**
	 * Stores information about the session to facilitate persistent login.
	 *
	 * Stores the session ID in a cookie, and in the user table.
	 * The next the user accesses the site, the cookie variable will be
	 * compared to the database variable, and if they match, the user will
	 * be given access again. Both the cookie and the database variable will
	 * then be refreshed.
	 *
	 * @return bool
	 */
	public function storeCookies(){
		if(!$this->isLoggedIn()){
			return $this->accessDenied();
		}

		global $user_id;
		global $role;
		global $seat_id;

		# Ensure the user is logged in
		if(!$user_id || !$role){
			return false;
		}

		if($this->isControlledByAdmin()){
			$this->log->info("Cookies not updated because this account is being controlled by an admin.");
			return true;
		}


		if (!$this->sql->update([
			"table" => "user",
			"set" => [
				"session_id" => session_id(),
				"last_role" => $role,
				"last_seat_id" => $seat_id,
			],
			"id" => $user_id
		])) {
			return false;
		}

		# Store the most recent session_id in a cookie
		setcookie('user_id', $user_id, strtotime('+30 days'));
		setcookie('session_id', session_id(), strtotime('+30 days'));

		return true;
	}


	/**
	 * Checks the current session ID with the user table, to see if
	 * it belongs to a user that is not the curren tuser, and that user
	 * is an admin.
	 *
	 * Assumes the admin has cookies stored.
	 * Cookies are not altered when an admin impersonates a user.
	 *
	 * @return int|bool Returns the user_id of the admin or FALSE if not controlled by an admin.
	 */
	public function isControlledByAdmin(){
		# Get the ID of the user (that may or may not be controlled by an admin)
		global $user_id;

		if($admin = $this->sql->select([
			"table" => "user",
			"join" => [[
				"table" => "user_role",
				"on" => "user_id",
				"where" => [
					"rel_table" => "admin"
				]
			]],
			"where" => [
				"session_id" => session_id()
			],
			"where_not" => [
				"user_id" => $user_id
			],
			"limit" => 1
		])){
			if($admin['user_id'] == $_COOKIE['user_id']
			&& $admin['session_id'] == $_COOKIE['session_id']){
				return $admin['user_id'];
			}
		}

		return false;
	}

	/**
	 * Checks to see whether the user is logged in or not.
	 * If not logged in, assumes they need to log in before accessing the resource.
	 * If logged in, assumes they do not have access to the resource.
	 *
	 * Should only be used as a last resort if the user is logged in.
	 *
	 * @return bool Returns FALSE to ensure the JS recipients understand what's going on.
	 */
	public function accessDenied(){
		global $user_id;

		if(!$user_id){
			//if a user is logged in, and is trying to perform an action they do not have access to
			$this->log->error([
				"title" => 'Access violation',
				"message" => "{$message} If you believe you should have access, please notify the administrators.",
			]);

			return false;
		}

		//If the user is NOT logged in
		$this->log->warning([
			"icon" => "lock-alt",
			"title" => 'Credentials required',
			"message" => 'Please log in to continue.',
		]);

		$this->hash->set([
			"action" => "login",
			"vars" => [
				"callback" => $this->hash->getCallback()
			]
		]);

		return false;
	}
}