<?php


namespace App\Common\User;

use App\Common\Common;
use App\Common\Email\Email;
use App\Common\Navigation\Navigation;
use App\Common\Permission;
use App\Common\str;
use App\Common\UserRole\UserRole;
use App\UI\Form\Form;
use App\UI\Icon;
use App\UI\Page;
use Exception;

/**
 * Class User
 *
 * Handles User affairs
 *
 * @package App\Common\User
 */
class User extends Common {
	/**
	 * The threshold where which a user's action is ignored.
	 * The recommended default is 0.5.
	 */
	const recaptchaThreshold = 0.5;

	/**
	 * @return Card
	 */
	public function card(){
		return new Card();
	}

	/**
	 * @return Modal
	 */
	public function modal(){
		return new Modal();
	}

	/**
	 * @return UserRole
	 */
	public function userRole(){
		return new UserRole();
	}

	public function view(array $a) : bool
	{
		extract($a);

		# rel_id is optional, use global if not found
		if(!$rel_id){
			global $user_id;
			$rel_id = $user_id;
		}

		if(!$this->permission()->get($rel_table, $rel_id, "crud")){
			return $this->accessDenied($a);
		}

		$user = $this->info("user", $rel_id);

		$page = new Page([
			"icon" => Icon::get($user['last_role']),
			"alt" => "You are currently logged in as a {$user['last_role']}",
			"title" => str::title($user['name'], true),
			"subtitle" => "You first registered on {$_ENV['title']} ".str::ago($user['created']),
		]);

		$page->setGrid([[
			"html"=> $this->card()->user($user),
			"sm" => 4
		]]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Edit one cron job. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function edit(array $a) : bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "u")){
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->edit($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * Edit one cron job. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function editEmail(array $a) : bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "u")){
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->editEmail($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * Update a user's details (except email, password).
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function update(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "u")){
			return $this->accessDenied();
		}

		$this->sql->update([
			"table" => $rel_table,
			"set" => [
				"first_name" => $vars['first_name'],
				"last_name" => $vars['last_name'],
				"phone" => $vars['phone'],
			],
			"id" => $rel_id
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# If there is a callback, use it
		$this->hash->set($this->hash->getCallback());

		return true;
	}

	/**
	 * Toggle a user's 2FA setting
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function toggle2FA(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "u")){
			return $this->accessDenied();
		}

		$user = $this->info($rel_table, $rel_id);

		if($user['2fa_enabled']){
			$set = ["2fa_enabled" => NULL];
		} else {
			$set = ["2fa_enabled" => true];
		}

		$this->sql->update([
			"table" => $rel_table,
			"set" => $set,
			"id" => $rel_id
		]);

		$this->hash->set(-1);

		return true;
	}

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
			"row_class" => "justify-content-md-center",
			"row_style" => [
				"height" => "85% !important"
			],
			"html" => [
				"sm" => 4,
				"class" => "fixed-width-column my-auto",
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
	 * @throws Exception
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
					"session_id" => NULL
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
			$this->setCookie($var, "", true);
			unset($$var);
		}

		# Update the navigation
		Navigation::update();

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
			"row_class" => "justify-content-md-center",
			"row_style" => [
				"height" => "85% !important"
			],
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
	 * Given a response, an action and a callback array,
	 * checks to see if the reCAPTCHA response is legit,
	 * and checks if the score is above the threshold.
	 * <code>
	 * # Ensure reCAPTCHA is validated
	 * if(!$this->validateRecaptcha($vars['recaptcha_response'], "insert_user", $hash)){
	 *    return false;
	 * }
	 * </code>
	 *
	 * @param string $response
	 * @param string $action
	 * @param mixed  $hash
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function validateRecaptcha(string $response, string $action, $hash = NULL){
		# Get the reCAPTCHA score
		if(!$score = $this->getRecaptchaScore($response, $action)){
			//If a score was not received
			if($hash){
				$this->hash->set($hash);
			}
			$this->log->warning([
				"message" => "Please try again."
			]);
			return false;
		}

		if($score < self::recaptchaThreshold){
			//if the score is below the reCAPTCHA threshold
			$this->log->error("Computer says no.");
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
	 * @throws Exception
	 */
	public function insert($a){
		extract($a);

		# The hash to send the user back to in case there is an error with the reCAPTCHA
		$hash =  [
			"rel_table" => $rel_table,
			"action" => "new",
			"vars" => [
				"first_name" => $vars['first_name'],
				"last_name" => $vars['last_name'],
				"email" => $vars['email'],
				"phone" => $vars['phone'],
				"tnc" => $vars['tnc'],
			]
		];

		# Ensure reCAPTCHA is validated
		if(!$this->validateRecaptcha($vars['recaptcha_response'], "insert_user", $hash)){
			return false;
		}

		# Ensure form is complete
		if(!$this->formComplete(["first_name","last_name","email","phone"], $vars)){
			return false;
		}

		# Ensure the email address is valid
		if(!str::isValidEmail($vars['email'])){
			$this->log->error([
				"title" => 'Invalid E-mail Address',
				"message" => "This email address [<code>{$vars['email']}</code>] is invalid. Please ensure you've written the correct address."
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
				"rel_id" => $user['user_id'],
				"action" => "verification_email_sent"
			]);
			return true;
		}

		# Create a random key for the verification process
		$key = str::uuid();

		# Insert the new user, get the new user_id
		$user_id = $this->sql->insert([
			"table" => 'user',
			"set" => [
				"first_name" => $vars['first_name'],
				"last_name" => $vars['last_name'],
				"email" => $vars['email'],
				"phone" => $vars['phone'],
				"last_role" => "user",
				"2fa_enabled" => true,
				"key" => $key
			],
		]);

		# Give the user permission to their own account
		$this->permission()->set($rel_table, $user_id, "rud", $user_id);

		# Create the link between the user and the user type
		$this->sql->insert([
			"table" => "user_role",
			"set" => [
				"user_id" => $user_id,
				"rel_table" => "user",
				"rel_id" => $user_id
			],
		]);

		$a['rel_id']  = $user_id;
		return $this->sendVerificationEmail($a, true);
	}

	/**
	 * Sends a verification email.
	 *
	 * @param array $a Needs to include a rel_table/rel_id
	 * @param null $internal
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function sendVerificationEmail(array $a, ?bool $internal = NULL) : bool
	{
		extract($a);

		if(!$internal){
			//If this is not an internal request
			$hash = [
				"rel_table" => $rel_table,
				"rel_id" => $rel_id,
				"action" => "verification_email_sent"
			];

			# Ensure reCAPTCHA is validated
			if(!$this->validateRecaptcha($vars['recaptcha_response'], "send_verification_email", $hash)){
				return false;
			}
		}

		if(!$user = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		])){
			//if the user cannot be found
			$this->log->error("Cannot find user to send verification email to.");
			return false;
		}

		if($user['verified']){
			//if the user is already verified
			$this->log->info([
				"title" => "Already verified",
				"message" => "You do not need to verify this email address again. Next time just log in.",
			]);
			$this->hash->set("login");
			return true;
		}

		$variables = [
			"user_id" => $rel_id,
			"email" => $user['email'],
			"key" => $user['key'],
		];

		$email = new Email();
		$email->template("verify_email", $variables)
			->to([$user['email'] => "{$user['first_name']} {$user['last_name']}"])
			->send();

		if($internal) {
			//if it's internal, meaning it's part of a process (eg. insert new user), direct them to the email sent page
			$this->hash->set([
				"rel_table" => $rel_table,
				"rel_id" => $rel_id,
				"action" => "verification_email_sent"
			]);
		} else {
			//if it's not internal let the user know that a verification email has been sent
			$this->log->success([
				"container" => ".card-body",
				"icon" => "envelope",
				"title" => "Verification email sent",
				"message" => "Verification email sent to <code>{$user['email']}</code>."
			]);
		}

		return true;
	}

	public function sendEmailUpdateEmail(array $a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "u")){
			return $this->accessDenied();
		}

		$user = $this->info($rel_table, $rel_id);

		# Ensure the new email is valid
		if(!str::isValidEmail($vars['new_email'])){
			$this->log->error([
				"container" => ".modal-body",
				"reset" => true,
				"title" => 'Invalid E-mail Address',
				"message" => "Your new email address [<code>{$vars['new_email']}</code>] is invalid. Please ensure you've written the correct address."
			]);
			return false;
		}

		# Ensure the new email is different from the last one
		if($user['email'] == $vars['new_email']){
			$this->log->warning([
				"container" => ".modal-body",
				"reset" => true,
				"title" => "Same as existing email",
				"message" => "If you wish to change your email address, enter the new email address, not your existing one.",
			]);
			return false;
		}

		# Check to see if the password is correct, compared to the password on file
		if (!$this->validatePassword($vars['password'], $user['password'])) {
			$this->log->error([
				"container" => ".modal-body",
				"title" => 'Incorrect password',
				"message" => 'Please ensure you have written the correct password.',
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
			]
		])){
			return false;
		}

		$variables = [
			"user_id" => $rel_id,
			"email" => $user['email'],
			"new_email" => $vars['new_email'],
			"checksum" => $this->createChecksum([$vars['new_email'], $key]),
		];

		# Send an email to the current email address warning about the update
		$email = new Email();
		$email->template("update_email_warning", $variables)
			->to([$user['email'] => "{$user['first_name']} {$user['last_name']}"])
			->send();

		# Send an email to the new email with a link to change email
		$email = new Email();
		$email->template("update_email", $variables)
			->to([$vars['new_email'] => "{$user['first_name']} {$user['last_name']}"])
			->send();

		# Closes the (top-most) modal
		$this->output->closeModal();

		$this->log->success([
			"container" => ".headsup",
			"title" => 'Update request email sent',
			"message" => "
			An email has been sent to <code>{$vars['new_email']}</code>
			with a link to update your email address. Once you change it,
			you can start using this new address to log in to {$_ENV['title']}.",
		]);

		return true;
	}

	public function updateEmail($a): bool
	{
		extract($a);

		if(!$rel_id || !$vars['new_email'] || !$vars['checksum']){
			$this->log->error([
				"container" => "#ui-view",
				'title' => "Verification failed",
				'message' => "The email update link is incomplete. Please ensure you are using the entire URL.",
			]);
			return false;
		}

		$user = $this->info($rel_table, $rel_id);

		# Make sure the checksum is valid (aka, the key hasn't expired or the email tampered with)
		if(!$this->validateChecksum([$vars['new_email'], $user['key']], $vars['checksum'])){
			$this->log->error([
				"container" => "#ui-view",
				'title' => "Verification failed",
				'message' => "The email update link is incorrect or it has expired. Please try again.",
			]);
			return false;
		}

		# Now that email is verified, update the email
		$this->sql->update([
			"table" => $rel_table,
			"id" => $rel_id,
			"set" => [
				"email" => $vars['new_email'],
				"verified" => "NOW()"
			]
		]);

		$this->log->success([
			"title" => "Email updated",
			"message" => "Your email address has successfully been updated to <code>{$vars['new_email']}</code>. Please use this new address to log in from now on."
		]);

		$this->hash->set("home");
		return true;
	}

	/**
	 * Given two or more strings
	 * (or a whole multidimensional array of data),
	 * create a checksum that can be used to
	 * validate the completeness of the data.
	 *
	 * @param array $a Array order doesn't matter, as the array will be sorted before the CRC32 checksum is created
	 *
	 * @return int the CRC32 checksum
	 */
	private function createChecksum(array $a): int
	{
		array_multisort($a);
		return crc32(implode("",str::flatten($a)));
	}

	/**
	 * Given an array of data and its checksum,
	 * compare it and return the results.
	 *
	 * @param array  $a Array of data
	 * @param string $checksum Checksum belonging to the data
	 *
	 * @return bool Returns TRUE if the data is complete, FALSE if not.
	 */
	private function validateChecksum(array $a, string $checksum): bool
	{
		return ($checksum == $this->createChecksum($a));
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
			"row_class" => "justify-content-md-center",
			"row_style" => [
				"height" => "85% !important"
			],
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
	 * Logs a connection and returns a connection ID
	 * back to the browser, that will be used as both
	 * the CSRF token for AJAX requests and the ID
	 * for any websockets data transfers both ways.
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function getSessionToken()
	{
		$geolocation = $this->getGeolocation();
		$user_agent_id = $this->getUserAgentId();
		global $user_id;
		if(!$connection_id = $this->sql->insert([
			"table" => "connection",
			"set" => [
				"session_id" => session_id(),
				"ip_address" => $geolocation['ip'],
				"user_agent_id" => $user_agent_id,
				"user_id" => $user_id
			],
		])){
			//The new connection was not saved
			throw new Exception("Unable to store connection details");
		}
		$this->output->set_var("token", $connection_id);
		return true;
	}

	public function sampler($a){
		extract($a);
		$cron_jobs_count = $this->sql->select([
			"count" => true,
			"table" => "cron_job"
		]);
		$this->output->set_var("key", $cron_jobs_count);
		$seconds = rand(4,7);
		sleep($seconds);
		$this->output->set_var("seconds", $seconds);
		$this->log->success("That took {$seconds}.");
		return true;
	}

	/**
	 * Returns an ID for the current user's user agent
	 * from the user_agent table.
	 *
	 * @return bool|int|mixed
	 * @throws Exception
	 */
	private function getUserAgentId()
	{
		# If the agent already exists, get the existing one
		if($user_agent = $this->sql->select([
			"table" => "user_agent",
			"where" => [
				"desc" => $_SERVER['HTTP_USER_AGENT']
			],
			"limit" => 1
		])) {
			//if it already exists
			return $user_agent['user_agent_id'];
		}

		//If the user agent does not exist, create a new record
		if(!$user_agent_id = $this->sql->insert([
			"table" => 'user_agent',
			"set" => [
				"desc" => $_SERVER['HTTP_USER_AGENT']
			],
		])){
			throw new Exception("Unable to create a user agent record.");
		}

		return $user_agent_id;
	}

	/**
	 * Given an IP address,
	 * returns geolocation details.
	 *
	 * Is public because, problamatically, it's called from elsewhere also.
	 * 
	 * @param string|null $ip
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
		catch (Exception $e) {
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
	 *    'success' => false,
	 *    'hostname' => 'app.registerofmembers.co.uk',
	 *    'challenge_ts' => '2020-04-22T08:10:13Z',
	 *    'apk_package_name' => NULL,
	 *    'score' => 0.9,
	 *    'action' => 'reset_password',
	 *    'error-codes' => array (
	 *        0 => 'timeout-or-duplicate',
	 *        1 => 'hostname-mismatch',
	 *        2 => 'action-mismatch',
	 *   ),
	 * )
	 * </code>
	 *
	 * @param string $response
	 * @param string $action
	 *
	 * @return float|bool
	 * @throws Exception
	 */
	private function getRecaptchaScore($response, $action){
		if(!$_ENV['recaptcha_key'] || !$_ENV['recaptcha_secret']){
			//if either recaptcha_key or secret has not been assigned
			throw new Exception("The reCAPTCHA key and secret have not been set.");
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

	/**
	 * Send a reset password email.
	 *
	 * @param null $a
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function sendResetPasswordEmail($a = NULL){
		extract($a);

		$hash = [
			"rel_table" => $rel_table,
			"action" => "reset_password",
			"vars" => [
				"email" => $vars['email']
			]
		];

		# Ensure reCAPTCHA is validated
		if(!$this->validateRecaptcha($vars['recaptcha_response'], "reset_password", $hash)) {
			return false;
		}

		if(!$vars['email']){
			$this->log->error([
				"container" => ".card-body",
				"icon" => "envelope",
				"title" => "No email address",
				"message" => "No email address was provided."
			]);
			return false;
		}

		if(!$user = $this->sql->select([
			"table" => "user",
			"where" => [
				"email" => $vars['email']
			],
			"limit" => 1
		])){
			//If the given email address cannot be found.
			$this->log->error([
				"container" => ".card-body",
				"icon" => "envelope",
				"title" => "Email not found",
				"message" => "The given email address cannot be found."
			]);
			return false;
		}

		if(!$user['verified']){
			$this->log->warning([
				"icon" => "envelope",
				"title" => "Email not verified",
				"message" => "Verify your account first, you should have received a verification email when you signed up."
			]);
			$this->hash->set([
				"rel_table" => "user",
				"rel_id" => $user['user_id'],
				"action" => "verification_email_sent"
			]);
			return true;
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
		$email->template("reset_password", $variables)
			->to([$user['email'] => $user['name']])
			->send();

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
	 * @param null $silent
	 *
	 * @return int|bool Returns the user_id of the user that's currently logged in or FALSE if user is not logged in
	 * @throws Exception
	 */
	public function isLoggedIn($silent = NULL) {
		global $user_id;

		if($user_id){
			return $user_id;
		}

		# If no local variables are stored (session expired), check to see if any cookies are stored
		if($this->loadCookies($silent)){
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
	 * @throws Exception
	 */
	function is($a){
		if(!$this->isLoggedIn()){
			return false;
		}
		global $user_id;
		global $role;

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

	/**
	 * Verify credentials before logging user in.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws Exception
	 */
	function verifyCredentials($a){
		extract($a);

		# Decrypt any encrypted variables
		Form::decryptVars($vars);

		# Check to see if the given email address can be found
		if(!$user = $this->sql->select([
			"table" => "user",
			"where" => [
				"email" => $vars['email'],
			],
			"limit" => 1
		])){
			$this->log->error([
				"closeInSeconds" => 10,
				"container" => ".card-body",
				"title" => 'Email not found',
				"message" => "The email address <code>{$vars['email']}</code> cannot be found. Are you sure have registered with {$_ENV['title']}?",
			]);
			return false;
		}

		# Check to see if the account is verified
		if(!$user['verified']) {
			//If the user has not verified their account
			$this->log->info([
				"icon" => "envelope-o",
				"title" => 'Email not verified yet',
				"message" => 'Please verify your email address before logging in.',
			]);
			$this->hash->set([
				"rel_table" => $rel_table,
				"rel_id" => $user['user_id'],
				"action" => "verification_email_sent"
			]);
			return false;
		}

		# Check to see if the password is correct, compared to the password on file
		if (!$this->validatePassword($vars['password'], $user['password'])) {
			$this->log->error([
				"container" => ".card-body",
				"title" => 'Incorrect password',
				"message" => 'Please ensure you have written the correct password.',
			]);
			return false;
		}

		# 2FA (if enabled)
		if($user['2fa_enabled']){
			return $this->prepare2FACode($a, $user);
		}

		$this->hash->set($vars['callback'] ? $vars['callback'] : "home");
		//This prevents callback loops by forcing callback to home if none is set

		# Log the verified user in
		return $this->logUserIn($user, $vars['remember']);
	}

	/**
	 * Prepare the 2FA code and send it in an email to the user.
	 * Present the user with instructions and a form to enter
	 * the code.
	 *
	 * @param $a
	 * @param $user
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function prepare2FACode($a, $user) : bool
	{
		extract($a);

		# Create the 2FA code
		$code = str::uuid(4);

		# Save the code in the key column
		$this->sql->update([
			"table" => "user",
			"id" => $user['user_id'],
			"set" => [
				"key" => $code,
				"session_id" => session_id()
			],
			"user_id" => $user['user_id']
		]);

		# Prepare variables for the template
		$variables = [
			"code" => $code,
		];

		# Prepare the template to place in an email and send
		$email = new Email();
		$email->template("email2FACode", $variables)
			->to([$user['email'] => "{$user['first_name']} {$user['last_name']}"])
			->send();

		# Set up the page with the code form
		$page = new Page();

		$page->setGrid([
			"row_class" => "justify-content-md-center",
			"row_style" => [
				"height" => "85% !important"
			],
			"html" => [
				"sm" => 4,
				"class" => "fixed-width-column my-auto",
				"html" => $this->card()->codeFor2FA($a, $user)
			]
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Verifies the 2FA code the user has entered.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function verify2FACode($a) : bool
	{
		extract($a);

		# If no user ID has been provided
		if(!$rel_id){
			throw new \Exception("No user ID was provided for the 2FA verification");
		}

		# Remove all characters (including spaces) except A to F and 0 to 9
		$vars['code'] = (string) preg_replace("/[^A-F0-9]/i", '', $vars['code']);

		# Make sure something remains
		if(!$vars['code']){
			throw new \Exception("An invalid 2FA verification code was provided.");
		}

		if(!$user = $this->sql->select([
			"table" => "user",
			"id" => $rel_id,
			"where" => [
				"key" => $vars['code'],
				"session_id" => session_id()
			]
		])){
			$this->log->error([
				"title" => 'Incorrect key',
				"message" => "The two-factor authentication key you provided was incorrect. Please ensure you have entered the right key.",
			]);
			return false;
		}

		if(str::moreThanMinutesSince(15, $user['updated'])){
			//If more than 15 minutes has passed since the user profile was last updated

			$this->log->error([
				"title" => 'Expired key',
				"message" => "The two-factor authentication key you provided has expired. Please log in again.",
			]);

			$this->hash->set([
				"action" => "login",
				"variables" => [
					"email" => $user['email']
				]
			]);

			return true;
		}

		# At this point the 2FA code has been successfully validated

		$this->hash->set($vars['callback'] ? $vars['callback'] : "home");
		//This prevents callback loops by forcing callback to home if none is set

		# Log the verified user in
		return $this->logUserIn($user, $vars['remember']);
	}

	/**
	 * After the credentials of a user has been verified,
	 * log the user in.
	 *
	 * @param $user
	 * @param $remember
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function logUserIn($user, $remember){
		# Store the user_id both globally and locally
		$_SESSION['user_id'] = $user['user_id'];
		global $user_id;
		$user_id = $_SESSION['user_id'];

		# Log access
		$this->logAccess($user_id);

		# Store cookies if the user has asked for it
		if($remember){
			$this->storeCookies($user_id);
		}

		# Assign role
		$this->assignRole($user);

		# Update navigation
		Navigation::update();

		return true;
	}

	/**
	 * Assigns a role to a user.
	 * Part of the log in process.
	 *
	 * @param $user
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function assignRole($user) : bool
	{
		if($user['last_role']){
			//if the last role variable is set, use it
			$_SESSION['role'] = $user['last_role'];
			global $role;
			$role = $_SESSION['role'];
			return true;
		}

		$roles = $this->sql->select([
			"table" => "user_role",
			"where" => [
				"user_id" => $user['user_id']
			]
		]);

		if(count($roles) == 1){
			//if they only have one role to play, use it
			return $this->userRole()->performSwitch($user['user_id'], $roles[0]['rel_table']);
		}

		# If the user needs to decide on a role first
		$this->hash->set("home");

		# Set up the modal to allow the user to chose roles
		$this->output->modal($this->modal()->selectRole($user['user_id'], $roles));

		# Remove the hash (as it's been moved to a callback
		$this->hash->unset();

		return true;
	}

	/**
	 * Logs the time the user logged in and keeps a record of the time before that they logged in.
	 *
	 * @param $user_id
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function logAccess(string $user_id){
		$this->sql->update([
			"table" => "user",
			"id" => $user_id,
			"set" => [
				"session_id" => session_id(),
				"last_logged_in" => "`user`.logged_in",
				"logged_in" => "NOW()"
			],
		]);

		# Update the connection record to include user_id
		$this->sql->update([
			"table" => "connection",
			"set" => [
				"user_id" => $user_id,
			],
			"id" => $_SERVER['HTTP_CSRF_TOKEN']
		]);

		return true;
	}

	/**
	 * Used for first time registrations and when users change address.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws Exception
	 */
	function verifyEmail($a){
		extract($a);

		if(!$rel_id || !$vars['key']){
			$this->log->error([
				"container" => "#ui-view",
				'title' => "Verification failed",
				'message' => 'Please ensure you have entered the entire verification code.',
			]);
			return false;
		}

		$vars['email'] = str_replace("%40", "@", $vars['email']);
		//hotmail.com will sometimes translate the @ to %40

		# Find the user
		if(!$user = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		])){
			$this->log->error([
				"container" => "#ui-view",
				'title' => "Verification failed",
				'message' => "Cannot find the user in question.",
			]);
			return false;
		}

		# Check and see if this user has already been verified
		if($user['verified'] && $user['password']){
			//if the user address has already been verified (and password has been set)
			$this->log->warning("This address has already been verified. You don't need to re-verify it. Log in straight away.");
			$this->hash->set([
				"rel_table" => "user",
				"action" => "login",
				"vars" => [
					"email" => $user['email']
				]
			]);
			return true;
		}

		# Make sure the key matches the ID
		if($user['key'] != $vars['key']){
			/**
			 * If the keys don't match up,
			 * resend the verification email.
			 * Keep in mind that at this point
			 * the user has yet to be verified
			 * and doesn't have a password set:
			 * It's an empty account.
			 */
			$this->sendVerificationEmail([
				"rel_table" => "user",
				"rel_id" => $user['user_id']
			], true);

			$this->log->error([
				"container" => "#ui-view",
				'title' => "Verification failed",
				'message' => "Invalid or expired verification code. A new verification code has been emailed to you.",
			]);
			return false;
		}

		# Verify the user
		$this->sql->update([
			"table" => "user",
			"set" => [
				"verified" => "NOW()"
			],
			"id" => $user['user_id'],
			"user_id" => $user['user_id']
		]);

		# If an existing user (with an existing password) is verifying a new email address
		if($user['password']){
			$this->log->success("Your new email address has been verified, thanks. From now on, please use <b>{$user['email']}</b> to log in.");
			$this->hash->set([
				"rel_table" => "user",
				"action" => "login",
				"vars" => [
					"email" => $user['email']
				]
			]);
			return true;
		}

		$page = new Page();

		$page->setGrid([
			"row_class" => "justify-content-md-center",
			"row_style" => [
				"height" => "85% !important"
			],
			"html" => [
				"sm" => 4,
				"class" => "fixed-width-column",
				"html" => $this->card()->newPassword($a)
			]
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Updates a password if:
	 * 1. First time user (no password set)
	 * 2. User gives both current and new password (change password)
	 * 3. User gives password hash and new password (password reset)
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function updatePassword(array $a) : bool
	{
		extract($a);

		# Passwords will be encrypted
		Form::decryptVars($vars);

		# Get the user
		if(!$user = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		])){
			$this->log->error("Cannot find the given user.");
			return false;
		}

		# Ensure key is correct
		if($user['key'] != $vars['key']){
			$this->log->error("You can only set the password for your own account.");
			return false;
		}

		if (!$vars['new_password'] || !$vars['repeat_new_password']) {
			$this->log->error("Ensure both password fields are filled out.");
			return false;
		}

		if ($vars['new_password'] != $vars['repeat_new_password']) {
			$this->log->error("Ensure both passwords match.");
			return false;
		}

		if (strlen($vars['new_password']) < Field::minimumPasswordLength) {
			$this->log->error("Ensure your password is at least ".Field::minimumPasswordLength." characters long.");
			return false;
		}

		# Encrypt and store the new password and update the key
		$this->sql->update([
			"table" => $rel_table,
			"set" => [
				"password" => $this->generatePasswordHash($vars['new_password']),
				"key" => str::uuid()
			],
			"id" => $rel_id,
			"user_id" => $rel_id,
		]);

		$this->log->success([
			"icon" => "lock",
			"title" => "New password saved",
			"message" => "Your new password has been saved. Give it a try!"
		]);

		$this->hash->set([
			"rel_table" => "user",
			"action" => "login",
			"vars" => [
				"email" => $email
			]
		]);

		return true;
	}

	/**
	 * Generate a secure hash for a given password.
	 * Uses the blowfish algorithm.
	 *
	 * @param string $password
	 * @param int    $cost
	 * @link https://www.php.net/manual/en/function.crypt.php#114060
	 * @return string|null
	 */
	private function generatePasswordHash(string $password, $cost=11){
		/* To generate the salt, first generate enough random bytes. Because
		 * base64 returns one character for each 6 bits, the we should generate
		 * at least 22*6/8=16.5 bytes, so we generate 17. Then we get the first
		 * 22 base64 characters
		 */
		$salt=substr(base64_encode(openssl_random_pseudo_bytes(17)),0,22);
		/* As blowfish takes a salt with the alphabet ./A-Za-z0-9 we have to
		 * replace any '+' in the base64 string with '.'. We don't have to do
		 * anything about the '=', as this only occurs when the b64 string is
		 * padded, which is always after the first 22 characters.
		 */
		$salt=str_replace("+",".",$salt);
		/* Next, create a string that will be passed to crypt, containing all
		 * of the settings, separated by dollar signs
		 */
		$param='$'.implode('$',array(
				"2y", //select the most secure version of blowfish (>=PHP 5.3.7)
				str_pad($cost,2,"0",STR_PAD_LEFT), //add the cost in two digits
				$salt //add the salt
			));

		//now do the actual hashing
		return crypt($password,$param);
	}

	/**
	 * Check the password against a hash generated by the generate_hash
	 * function.
	 *
	 * @param string $password
	 * @param string $hash
	 * @link https://www.php.net/manual/en/function.crypt.php#114060
	 * @return bool
	 */
	private function validatePassword(string $password, string $hash){
		/* Regenerating the with an available hash as the options parameter should
		 * produce the same hash if the same password is passed.
		 */
		return crypt($password, $hash)==$hash;
	}

	/**
	 * Confirmation page that the email verification
	 * email has been sent.
	 *
	 * @param $a
	 *
	 * @return bool
	 */
	public function verificationEmailSent($a){
		extract($a);

		$page = new Page();

		$page->setGrid([
			"row_class" => "justify-content-md-center",
			"row_style" => [
				"height" => "85% !important"
			],
			"html" => [
				"sm" => 4,
				"class" => "fixed-width-column",
				"html" => $this->card()->verificationEmailSent($a)
			]
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * If no local (session) variables are stored,
	 * meaning, the session expired,
	 * check to see if there are any cookie variables
	 * stored, and if so, revive the session.
	 *
	 * @param null $silent
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function loadCookies($silent = NULL) : bool
	{
		# If the cookies are not both present
		if(!$_COOKIE['session_id'] || !$_COOKIE['user_id']){
			return false;
		}

		# Are the cookie values correct?
		if(!$user = $this->sql->select([
			"table" => "user",
			"where" => [
				"session_id" => $_COOKIE['session_id'],
			],
			"where_not" => [
				"session_id" => NULL
			],
			"id" => $_COOKIE['user_id'],
		])) {
			//if the cookies are no longer valid
			return false;
		}

		# Message to the user returning
		if(!$silent){
			$this->log->info([
				"message" => "Welcome back {$user['first_name']}!"
			]);
		}

		# Repopulate the session variables
		$_SESSION['user_id'] = $user['user_id'];
		$_SESSION['role'] = $user['last_role'];

		# Repopulate the global variables
		global $user_id;
		global $role;

		$user_id = $user['user_id'];
		$role = $user['last_role'];

		# Update the session ID with the new PHP session ID
		$this->sql->update([
			"table" => "user",
			"set" => [
				"session_id" => session_id()
			],
			"id" => $user['user_id']
		]);

		# Log access
		$this->logAccess($user_id);

		# Update the cookies
		$this->storeCookies($user_id);

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
	 * @param $user_id
	 *
	 * @return bool
	 */
	private function storeCookies(string $user_id) : bool
	{
		$this->setCookie("user_id", $user_id);
		$this->setCookie("session_id", session_id());

		return true;
	}

	/**
	 * "Manual" set cookie function.
	 * Ensures all cookies are set the same.
	 *
	 *  - `Secure` is set to `TRUE`. Indicates that the cookie should
	 * only be transmitted over a secure HTTPS connection from the client
	 *  - `HttpOnly` is set to `TRUE`. When `TRUE` the cookie will be made
	 * accessible only through the HTTP protocol. This means that the cookie
	 * won't be accessible by scripting languages, such as JavaScript.
	 *  - `SameSite` is set to `Strict`. Asserts that a cookie must not
	 * be sent with cross-origin requests, providing some protection
	 * against cross-site request forgery attacks (CSRF).
	 *
	 * @param string    $key
	 * @param string    $val
	 * @param bool|null $remove set to TRUE to remove the cookie.
	 *
	 * @return bool
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie
	 * @link https://www.php.net/manual/en/function.header.php
	 */
	private function setCookie(string $key, string $val, ?bool $remove = NULL) : bool
	{
		$expires = gmdate('D, d-M-Y H:i:s T', strtotime($remove ? "-1 year" : "+30 days"));
		header("Set-Cookie: {$key}={$val}; Expires={$expires}; Path=/; Domain={$_ENV['domain']}; Secure; HttpOnly; SameSite=Strict;", false);
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
	 * @param array|null $a The current URL for the callback
	 *
	 * @return bool Returns FALSE to ensure the JS recipients understand what's going on.
	 */
	public function accessDenied(?array $a = NULL){
		global $user_id;

		if($user_id){
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
			"rel_table" => "user",
			"action" => "login",
			"vars" => [
				"callback" => str::generate_uri($a, true)
			]
		]);

		return false;
	}


	public function getOptions(array $a) : bool
	{
		extract($a);

		if(!$this->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		if($vars['term']){
			$term = preg_replace("/[^a-z0-9-'\. ]/i", "", $vars['term']);
			$or = [
				"first_name" => "%{$term}%",
				"last_name" => "%{$term}%",
			];
		}

		$results = $this->info([
			"rel_table" => "user",
			"or" => $or,
			"order_by" => [
				"first_name" => "ASC",
				"last_name" => "ASC"
			]
		]);

		if($results) {
			foreach($results as $row){
				$users[] = [
					"id" => $row['user_id'],
					"text" => $row['full_name']
				];
			}
		} else {
			$users = [];
		}

		$this->output->set_var("results", $users);
		return true;
	}
}