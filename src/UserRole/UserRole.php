<?php


namespace App\Common\UserRole;


use App\Common\Email\Email;
use App\Common\Navigation\Navigation;
use App\Common\str;
use App\UI\Button;
use App\UI\Grid;
use App\UI\Icon;

/**
 * Class UserRole
 * @package App\Common\UserRole
 */
class UserRole extends \App\Common\Prototype {
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

	public function all($a): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied($a);
		}

		$this->output->modal($this->modal()->all($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	public function insert($a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table)){
			return $this->accessDenied($a);
		}

		if($this->sql->select([
			"table" => $rel_table,
			"where" => [
				"user_id" => $vars['user_id'],
				"rel_table" => $vars['role']
			]
		])){
			$this->log->error("This user has already been given this role.");
			return false;
		}

		if($removed_role = $this->sql->select([
			"table" => $rel_table,
			"where" => [
				"user_id" => $vars['user_id'],
				"rel_table" => $vars['role']
			],
			"include_removed" => true,
			"limit" => 1
		])){
			// If the user used to have this role before

			# Restore user_role
			$this->sql->restore([
				"table" => $rel_table,
				"id" => $removed_role["{$rel_table}_id"]
			]);
			# Restore role
			$this->sql->restore([
				"table" => $removed_role['rel_table'],
				"id" => $removed_role["rel_id"]
			]);
			$this->log->success([
				"icon" => Icon::get("role"),
				"title" => str::title("{$vars['role']} role restored"),
				"message" => "The user had the <code>{$vars['role']}</code> role before and this role has now been restored. This may have unintended consequences.",
				"container" => ".modal-body"
			]);

		} else {
			// If this is the first time the user is having this role

			$rel_id = $this->sql->insert([
				"table" => $vars['role'],
				"ignore_empty" => true
			]);

			$this->sql->insert([
				"table" => $rel_table,
				"set" => [
					"user_id" => $vars['user_id'],
					"rel_table" => $vars['role'],
					"rel_id" => $rel_id
				]
			]);

			$this->log->success([
				"icon" => Icon::get("role"),
				"title" => "{$this->user->get()['first_name']} given {$vars['role']} role",
				"message" => "{$this->user->get()['first_name']} was successfully given the <code>{$vars['role']}</code> role. There may be additional settings for the role that user needs to set in order to fully operate as ".str::A($vars['role']).".",
				"container" => ".modal-body"
			]);
		}

		# Get the user
		$user = $this->info("user", $vars['user_id']);

		# Prepare the variables for the email
		$variables = [
			"first_name" => $user['first_name'],
			"role" => $vars['role']
		];

		# Email the user about the new role
		$email = new Email();
		if($email->template("new_role", $variables)
			->to([$user['email'] => "{$user['first_name']} {$user['last_name']}"])
			->send()){
			$this->log->info([
				"icon" => Icon::get("role"),
				"title" => "{$user['first_name']} emailed about {$vars['role']} role",
				"message" => "An email was sent to {$user['first_name']} informing them about their new role as ".str::A($vars['role']).".",
				"container" => ".modal-body"
			]);
		}

		else {
			$this->log->error([
				"icon" => Icon::get("role"),
				"title" => "Failed to email {$user['first_name']} about {$vars['role']} role",
				"message" => "An email was not sent to {$user['first_name']} informing them about their new role as ".str::A($vars['role']).".",
				"container" => ".modal-body"
			]);
		}

		# A change in roles will have impact on the navigation
		Navigation::update();

		return $this->getUserRoles($a);
	}

	public function remove(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			return $this->accessDenied($a);
		}

		# Make sure role exists
		if(!$role = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		])){
			$this->log->error("This user does not have this role.");
			return false;
		}

		# Remove user_role
		$this->sql->remove([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		# Remove role
		$this->sql->remove([
			"table" => $role['rel_table'],
			"id" => $role['rel_id']
		]);

		$this->log->info([
			"icon" => Icon::get("remove"),
			"title" => str::title("{$role['rel_table']} role removed"),
			"message" => "The <code>{$role['rel_table']}</code> role was successfully removed from the user.",
			"container" => ".modal-body"
		]);

		# A change in roles will have impact on the navigation
		Navigation::update();

		$a['vars']['user_id'] = $role['user_id'];

		return $this->getUserRoles($a);
	}

	public function getUserRoles(array $a): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			return $this->accessDenied($a);
		}

		if(!$user_roles = $this->sql->select([
			"table" => "user_role",
			"where" => [
				"user_id" => $vars['user_id']
			]
		])){

			$this->output->update("#user_roles", Grid::generate([
				"row_style" => [
					"float" => "left"
				],
				"html" => [
					"button" => $this->modal()->getMutedRoleButtons()
				]
			]));
			return true;
		}

		$all_roles = $this->sql->select(["table" => "role"]);

		global $user_id;

		foreach($all_roles as $role){
			if($role['role'] == "user"){
				continue;
			}
			if(($key = array_search($role['role'], array_column($user_roles, "rel_table"))) !== false){
				//if the user has this role
				$buttons[] = [
					"colour" => "primary",
					"icon" => $role['icon'],
					"alt" => "Remove the {$role['role']} role from this user",
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $user_roles[$key]['user_role_id'],
						"action" => "remove"
					],
					"disabled" => ($role['role'] == "admin") && ($vars['user_id'] == $user_id),
					"approve" => [
						"colour" => "red",
						"icon" => Icon::get("remove"),
						"title" => "Remove this role?",
						"message" => "Removing the {$role['role']} role from this user will remove all permissions this user has thru the role, and may have unintended consequences."
					]
				];
			} else {
				//if the user does NOT have this role
				$buttons[] = [
					"colour" => "primary",
					"basic" => true,
					"icon" => $role['icon'],
					"alt" => "Add this user to the {$role['role']} role",
					"hash" => [
						"rel_table" => $rel_table,
						"action" => "insert",
						"vars" => [
							"role" => $role['role'],
							"user_id" => $vars['user_id']
						]
					],
					"approve" => [
						"colour" => "blue",
						"icon" => $role['icon'],
						"title" => "Add this role?",
						"message" => "Adding the {$role['role']} role to this user will give the user all the permissions of the {$role['role']} role."
					]
				];
			}
		}

		foreach($buttons as $button){
			$html .= Button::generate($button);
		}

		$this->output->update("#user_roles", "<div class=btn-float-right>{$html}</div>");
		return true;
	}

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
				"icon" => Icon::get("role"),
				"title" => str::title("Switched to {$vars['new_role']}"),
				"message" => "You have successfully switched user roles to that of ".str::A($vars['new_role']).".",
			]);

			# Since you're changing roles, go home
			$this->hash->set("home");

			Navigation::update();

			return true;
		}

		# Set up the modal to allow the user to choose roles
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