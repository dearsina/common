<?php


namespace App\Common\Admin;

use App\Common\Common;
use App\UI\Icon;
use App\UI\Table;

class Admin extends Common {
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

	public function insert($a){
		extract($a);

		if(!$this->user->isLoggedIn()){
			$this->accessDenied();
		}

		if(!$this->info("admin")){
			//if this is the first admin
			global $user_id;
			$vars['user_id'] = $user_id;
			//Use _this_ user as the user_id

		} else {
			//if this is NOT the first admin
			if(!$this->user->is("admin")){
				//Only admins have access
				return $this->accessDenied();
			}
		}

		# Make sure user ID was sent
		if(!$vars['user_id']){
			throw new \Exception("No user ID was supplied for the new admin.");
		}

		# Make sure the user ID is valid
		if(count($this->sql->select([
			"table" => "user",
			"where" => [
				"user_id" => $vars['user_id']
			]
		])) !== 1){
			throw new \Exception("An invalid user ID was supplied for the new admin.");
		}

		# Create a new admin role
		$admin_id = $this->sql->insert([
			"table" => "admin",
		]);

		# Tie the new admin role to the user
		$this->sql->insert([
			"table" => "user_role",
			"set" => [
				"rel_table" => "admin",
				"rel_id" => $admin_id,
				"user_id" => $vars['user_id']
			]
		]);

		if($vars['user_id'] == $user_id){
			//if this is the first admin

			# Switch the user to the new role
			$this->hash->set([
				"rel_table" => "user_role",
				"action" => "switch",
				"vars" => [
					"new_role" => "admin",
				]
			]);
		} else {
			$this->hash->set(-1);
		}

		$this->log->success([
			"icon" => Icon::get("admin"),
			"title" => "Admin created",
			"message" => "A new admin has been created."
		]);

		return true;
	}

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

		# Get the latest issue types
		$this->updateErrorNotificationTable();

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	public function errorNotification($a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->error_notification($a));

		# Get the latest issue types
		$this->updateErrorNotificationTable();

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	private function updateErrorNotificationTable() : void
	{
		$admins = $this->info("admin");

		if($admins){
			foreach($admins as $admin){
				$buttons = [];

				$buttons[] = [
					"basic" => $admin["notification_frequency"] != 3,
					"alt" => "High alert (notifications every minute)",
					"icon" => "temperature-hot",
					"colour" => "danger",
					"size" => "xs",
					"hash" => [
						"rel_table" => "admin",
						"rel_id" => $admin['admin_id'],
						"action" => "update",
						"vars" => [
							"notification_frequency" => 3
						]
					]
				];

				$buttons[] = [
					"basic" => $admin["notification_frequency"] != 2,
					"alt" => "Medium alert (notifications every hour)",
					"icon" => "thermometer-half",
					"colour" => "warning",
					"size" => "xs",
					"hash" => [
						"rel_table" => "admin",
						"rel_id" => $admin['admin_id'],
						"action" => "update",
						"vars" => [
							"notification_frequency" => 2
						]
					]
				];

				$buttons[] = [
					"basic" => $admin["notification_frequency"] != 1,
					"alt" => "Low alert (notifications every day)",
					"icon" => "temperature-frigid",
					"colour" => "blue",
					"size" => "xs",
					"hash" => [
						"rel_table" => "admin",
						"rel_id" => $admin['admin_id'],
						"action" => "update",
						"vars" => [
							"notification_frequency" => 1
						]
					]
				];

				$buttons[] = [
					"basic" => $admin["notification_frequency"],
					"alt" => "No alerts",
					"icon" => "thermometer-empty",
					"colour" => "grey",
					"size" => "xs",
					"hash" => [
						"rel_table" => "admin",
						"rel_id" => $admin['admin_id'],
						"action" => "update",
						"vars" => [
							"notification_frequency" => NULL
						]
					]
				];

				$rows[] = [
					"Admin" => [
						"html" => $admin['user']['full_name']
					],
					"Notifications" => [
						"sortable" => false,
						"button" => $buttons
					]
				];
			}
		} else {
			$rows[] = [
				"Admin" => [
					"class" => "text-silent",
					"html" => "(No admins found)"
				]
			];
		}

		$this->output->update("all_error_notification", Table::generate($rows));
	}
}