<?php


namespace App\Common;

use App\UI\Icon;

/**
 * Class Permission
 * @package App\Common
 */
class Permission extends Common {
	/**
	 * Combines role and user permissions and checks
	 * if they together amount to the requested permission
	 * combo.
	 *
	 * @param string      $rel_table A mandatory rel_table
	 * @param string|null $rel_id An optional rel_id
	 * @param string|null $crud Create, Read, Update and/or Delete, NULL = CRUD
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function get (string $rel_table, ?string $rel_id = NULL, ?string $crud = NULL): bool
	{
		# If no CRUD is specified = ALL access
		if (!strlen($crud)) {
			$crud = "crud";
		}

		# The user is always the current user
		if (!$user_id = $this->user->isLoggedIn()) {
			return false;
		}

		// Get the logged in user's current role
		global $role;

		# Admins have rights to everything
		if($role == "admin"){
			return true;
		}

		# Get role and user permissions for the role/user + rel_table/id combo
		$role_permissions = $this->getRolePermission($role, $rel_table, $rel_id);
		$user_permissions = $this->getUserPermission($user_id, $rel_table, $rel_id);

		# Check permissions
		foreach (str_split(strtolower($crud)) as $l) {
			// For each permission requested
			if(!$role_permissions[$l] && !$user_permissions[$l]){
				// If neither the role or the user has the permission
				return false;
				// Return false
			}
		}

		return true;
		// Otherwise return true
	}

	/**
	 * What kind of permissions does this user have on this rel_table/id?
	 *
	 * @param string      $user_id
	 * @param string      $rel_table
	 * @param string|null $rel_id
	 *
	 * @return array A single row of CRUDs from the user_permission table.
	 */
	private function getUserPermission (string $user_id, string $rel_table, ?string $rel_id): array
	{
		$where = [
			"user_id" => $user_id,
			"rel_table" => $rel_table,
		];

		/**
		 * If a rel_id has been included use it.
		 * But if it hasn't, this particular user
		 * needs to have been given blanket access
		 * to the $rel_table, thus rel_id must be NULL.
		 */
		if($rel_id){
			$where['rel_id'] = $rel_id;
		} else {
			$where['rel_id'] = NULL;
		}

		if ($permission = $this->sql->select([
			"columns" => [
				"c",
				"r",
				"u",
				"d",
			],
			"table" => "user_permission",
			"where" => $where,
			"limit" => 1
		])) {
			//if this user in particular has the access
			return $permission;
		}

		return [];
	}

	/**
	 * What permission does this _role_ have on this rel_table/id?
	 *
	 * @param string|null $role
	 * @param string      $rel_table
	 * @param string|null $rel_id
	 *
	 * @return array A single row of CRUDs from the role_permission table
	 */
	private function getRolePermission (?string $role, string $rel_table, ?string $rel_id): array
	{

		# Not all users have roles
		if(!$role){
			return [];
		}

		# A rel_table is not optional
		$where["rel_table"] = $rel_table;

		/**
		 * A rel_id is optional, and role access
		 * is mostly on rel_table alone,
		 * superseding rel_id.
		 *
		 * So we have to check for both the value,
		 * and NULL.
		 */
		if($rel_id){
			$where['rel_id'] = [$rel_id, NULL];
		}

		if ($permission = $this->sql->select([
			"columns" => [
				"role_id",
				"c",
				"r",
				"u",
				"d",
			],
			"table" => "role_permission",
			"join" => [[
				"columns" => false,
				"table" => "role",
				"on" => "role_id",
				"where" => [
					"role" => $role
				]
			]],
			"where" => $where,
			"limit" => 1
		])) {
			//if the role this user currently has, gives them access
			return $permission;
		}

		return [];
	}

	/**
	 * Given a rel_table/id + user_id and CRUD,
	 * will create a record in the user_permission table
	 * for the given user giving them the prescribed
	 * access to the rel_table/id.
	 *
	 * If access has already been granted, the access
	 * level is updated to match the current request.
	 *
	 * @param string      $rel_table
	 * @param string|null $rel_id
	 * @param string|null $crud
	 * @param string|null $user_id
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function set (string $rel_table, ?string $rel_id, ?string $crud = NULL, ?string $user_id = NULL): bool
	{
		# If not CRUD = ALL access
		if (strlen($curd)) {
			$crud = "crud";
		}

		# No user ID = the current user
		if (!$user_id) {
			if (!$user_id = $this->user->isLoggedIn()) {
				throw new \Exception("Permission was requested without a valid user ID.");
			}
		}

		$set = [
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"user_id" => $user_id,
		];

		foreach (["c", "r", "u", "d"] as $l) {
			$access[$l] = in_array($l, str_split(strtolower($crud)));
		}

		if ($existing_permission = $this->sql->select([
			"table" => "user_permission",
			"where" => $set,
		])) {
			//if there already exists permissions for this user/rel_table/id combo
			$this->sql->update([
				"table" => "user_permission",
				"set" => $access,
				"id" => $existing_permission['user_permission_id'],
			]);
			return true;
		}

		# If permissions do not already exist
		$this->sql->insert([
			"table" => "user_permission",
			"set" => array_merge($set, $access),
		]);

		return true;
	}

	/**
	 * Remove any and all permissions.
	 *
	 * Given a rel_table/id, remove one or all permissions associated with it.
	 * If a user_id is given, only permissions on the rel_table/id for that user
	 * are removed.
	 *
	 * @param string      $rel_table
	 * @param string      $rel_id
	 * @param string|null $user_id
	 */
	public function remove(string $rel_table, string $rel_id, ?string $user_id = NULL): void
	{
		$this->sql->remove([
			"table" => "user_permission",
			"where" => [
				"rel_table" => $rel_table,
				"rel_id" => $rel_id,
				"user_id" => $user_id
			]
		]);
	}

	/**
	 * Updates permissions or role permissions,
	 * depending on which class is using the method.
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
	public function update (array $a, $silent = NULL): bool
	{
		extract($a);

		if (!$this->user->is("admin")) {
			//Only admins have access
			return $this->accessDenied($a);
		}

		if ($vars['role_id']) {
			if (!$item = $this->sql->select([
				"table" => "role",
				"id" => $vars['role_id'],
			])) {
				// If the role cannot be found
				throw new \Exception("The requested role cannot be found.");
			}
			$rel = "role";
		} else if ($vars['user_id']) {
			if (!$item = $this->info("user", $vars['user_id'])) {
				// If the user cannot be found
				throw new \Exception("The requested user cannot be found.");
			}
			$rel = "user";
		} else {
			throw new \Exception("You must provide either a role or a user to see their permissions.");
		}

		# Get existing permissions
		if ($results = $this->sql->select([
			"distinct" => true,
			"columns" => [
				"rel_table",
				"c",
				"r",
				"u",
				"d",
				"count_rel_id" => ["COUNT", "rel_id"]
			],
			"table" => $rel_table,
			"where" => [
				"{$rel}_id" => $item["{$rel}_id"],
			],
		])) {
			foreach ($results as $table) {
				$existing_permissions[$table['rel_table']] = $table;
			}
		} else {
			//if this role has no permissions
			$existing_permissions = [];
		}

		# For each table returned
		foreach ($vars['table'] as $table => $crud) {

			# New table
			if (!is_array($existing_permissions) || !key_exists($table, $existing_permissions)) {
				//if this table doesn't exist in the existing role permissions

				if (empty(array_filter($crud))) {
					//if the table doesn't have any values
					continue;
					//There is nothing to set, continue
				}

				# Insert all values for this table and continue
				$this->sql->insert([
					"table" => $rel_table,
					"set" => [
						"{$rel}_id" => $item["{$rel}_id"],
						"rel_table" => $table,
						"c" => $crud['c'],
						"r" => $crud['r'],
						"u" => $crud['u'],
						"d" => $crud['d'],
					],
				]);
				$counts['added'] += count(array_filter($crud));
				continue;
			}

			# If the table already exists
			foreach ($crud as $key => $val) {
				//For each CRUD value for that table
				if ($existing_permissions[$table][$key] == $val) {
					//if the value remains the same
					if ($val) {
						//only count the permission if it exists
						$counts['same']++;
					}
					continue;
				}

				if ($existing_permissions[$table][$key]) {
					//if the value is being removed
					$counts['removed']++;
				} else {
					//If the value is being added
					$counts['added']++;
				}

				# Update the changed value
				$this->sql->update([
					"table" => $rel_table,
					"set" => [
						$key => $val,
					],
					"where" => [
						$rel => $item["{$rel}_id"],
						"rel_table" => $table,
					],
				]);
			}
		}

		if (!$silent) {
			$narrative[] = str::were($counts['added'], "permission", true) . " added";
			$narrative[] = str::were($counts['same'], "permission", true) . " kept the same";
			$narrative[] = str::were($counts['removed'], "permission", true) . " removed";
			$this->log->success([
				"alert" => Icon::get("permission"),
				"title" => str::title("{$rel} permissions updated"),
				"message" => str::title(str::oxford_implode($narrative)) . ".",
			]);
		}

		return true;
	}
}