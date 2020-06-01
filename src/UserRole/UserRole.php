<?php


namespace App\Common\UserRole;


use App\Common\Navigation\Navigation;
use App\Common\str;
use App\UI\Icon;

class UserRole extends \App\Common\Common {
	public function switch($a){
		extract($a);

		if(!$this->user->isLoggedIn()){
			return $this->accessDenied();
		}

		# If a user has been given, use it, otherwise, use global
		if($vars['user_id']){
			$user_id = $vars['user_id'];
		} else {
			global $user_id;
		}

		if($vars['new_role']){
			//if a new role has been given

			# Perform the user role switch
			$this->performSwitch($user_id, $vars['new_role']);

			# Notify the user
			$this->log->info([
				"icon" => Icon::get($vars['new_role']),
				"title" => str::title("Switched to {$vars['new_role']}"),
				"message" => "You have successfully switched user roles to that of ".str::A($vars['new_role']).".",
			]);

			# Since you're changing roles, go home
			$this->hash->set("home");

			Navigation::update();

			return true;
		}

		# Set up the modal to allow the user to chose roles
		$this->output->modal($this->user->modal()->selectRole($user_id, $roles));

		# Remove the hash (as it's been moved to a callback
		$this->hash->unset();

		return true;
	}

	/**
	 * Given a user ID and a new role,
	 * will check if the user has been assigned that role,
	 * and if so, will switch that user to this new role.

	 * @param string $user_id
	 * @param string $new_role
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function performSwitch(string $user_id, string $new_role){
		# Ensure the user has been assigned this role
		if(!$this->sql->select([
			"table" => "user_role",
			"where" => [
				"user_id" => $user_id,
				"rel_table" => $new_role
			]
		])){
			throw new \Exception("This user has not been assigned the {$new_role} role.");
		}

		# Update the last role value
		if(!$this->user->isControlledByAdmin()){
			//except if the user is being controlled by an admin
			$this->sql->update([
				"table" => "user",
				"set" => [
					"last_role" => $new_role
				],
				"id" => $user_id
			]);
		}

		$_SESSION['role'] = $new_role;
		global $role;
		$role = $_SESSION['role'];

		return true;
	}
}