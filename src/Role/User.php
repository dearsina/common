<?php


namespace App\Common\Role;

use App\Common\Hash;
use App\Common\Log;
use App\Common\SQL\mySQL;

class User {
	public function __construct () {
		$this->sql = mySQL::getInstance();
		$this->log = Log::getInstance();
		$this->hash = Hash::getInstance();
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