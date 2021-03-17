<?php


namespace App\Common\RolePermission;


use App\Common\Permission\Permission;
use App\Common\str;
use App\UI\Icon;
use App\UI\Page;

/**
 * Class RolePermission
 * @package App\Common\RolePermission
 */
class RolePermission extends Permission {
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
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function view($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		if(!$roles = $this->sql->select([
			"table" => "role",
			"order_by" => [
				"role" => "ASC"
			]
		])){
			$this->log->warning([
				"title" => "No roles have been set up yet",
				"message" => "Set up a role first, before assigning permissions to it."
			]);
			$this->hash->set([
				"rel_table" => "role",
				"action" => "all"
			]);
			return true;
		}

		if($vars['role_id']){
			if(!$role = $this->sql->select([
				"table" => "role",
				"id" => $vars['role_id']
			])){
				throw new \Exception("Invalid role provided for permissions management.");
			}
			$page = new Page([
				"title" => str::title("{$role['role']} permissions"),
				"icon" => $role['icon'],
				"subtitle" => "All the permissions belonging to the {$role['role']} role."
			]);
			$role_permissions = $this->card()->rolePermission($role);
		} else {
			$page = new Page([
				"title" => str::title("Role permissions"),
				"icon" => Icon::get("role"),
				"subtitle" => "Select a role to see their permissions."
			]);
		}

		$page->setGrid([[
			"sm" => 3,
			"html" => $this->card()->selectRole($roles, $role)
		],[
			"html" => $role_permissions
		]]);

		$this->output->html($page->getHTML());

		# Closes the (top-most) modal
		$this->output->closeModal();

		return true;
	}
}