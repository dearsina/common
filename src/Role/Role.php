<?php


namespace App\Common\Role;

use App\Common\Common;
use App\Common\str;
use App\UI\Badge;
use App\UI\Icon;
use App\UI\Table;

class Role extends Common {
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
	 * View all roles. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function all(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->all($a));

		# Get the latest issue types
		$this->updateRelTable($a);

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
	public function edit(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->edit($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * New cron job form. Will open a modal.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function new(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->new($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * Insert a new role.
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function insert(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->insert([
			"table" => $rel_table,
			"set" => $vars
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	/**
	 * Update a role.
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

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->update([
			"table" => $rel_table,
			"set" => $vars,
			"id" => $rel_id
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	/**
	 * Is called by various methods to refresh
	 * the table of rows.
	 *
	 * @param $a
	 *
	 * @throws \Exception
	 */
	protected function updateRelTable($a) : void
	{
		extract($a);

		$roles = $this->sql->select([
			"table" => $rel_table,
			"order_by" => [
				"role" => "ASC"
			]
		]);

		if($roles){
			foreach($roles as $role){
				$buttons = [];

				$buttons[] = [
					"alt" => "See {$role['role']} permissions",
					"icon" => Icon::get("role_permission"),
					"hash" => [
						"rel_table" => "role_permission",
						"action" => "view",
						"vars" => [
							"role_id" => $role["{$rel_table}_id"]
						]
					],
					"size" => "xs",
					"basic" => true,
				];

				$buttons[] = [
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $role["{$rel_table}_id"],
						"action" => "remove"
					],
					"alt" => "Remove..",
					"icon" => Icon::get("trash"),
					"colour" => "danger",
					"size" => "xs",
					"basic" => true,
					"approve" => true
				];

				$rows[] = [
					"Role" => [
						"icon" => $role["icon"],
						"html" => str::title($role['role']),
						"hash" => [
							"rel_table" => $rel_table,
							"rel_id" => $role["{$rel_table}_id"],
							"action" => "edit"
						]
					],
					"Action" => [
						"sortable" => false,
						"header_style" => [
							"opacity" => 0
						],
						"button" => $buttons
					]
				];
			}
		} else {
			$rows[] = [
				"Roles" => [
					"icon" => Icon::get("new"),
					"html" => "New role...",
					"hash" => [
						"rel_table" => $rel_table,
						"action" => "new"
					]
				]
			];
		}

		$this->output->update("all_role", Table::generate($rows));
	}
}