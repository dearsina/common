<?php


namespace App\Common\Navigation;


use App\Common\Common;
use App\Common\str;
use App\UI\Icon;

class User extends Common {
	private $levels = [];

	public function update($a = NULL){
		global $user_id;

		if(!$user_id){
			throw new \Exception("User ID missing from the <code>Navigation\\User</code> method. This should not be possible.");
		}

		# Grab the user
		$user = $this->info("user", $user_id);

		# User account
		$children[] = [
			"title" => "Account",
			"icon" => "user",
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user_id
			],
		];

		# Switch roles
		$children[] = $this->switchRoles($user);

		# Log out
		$children[] = [
			"title" => "Log out",
			"icon" => "power-off",
			"hash" => [
				"rel_table" => "user",
				"action" => "logout"
			]
		];

		$this->levels[1]['items'][] = [
			"icon" => "user",
			"children" => $children
		];

		$this->levels[2]['title'] = "Level 2 title";

		return $this->levels;
	}

	private function switchRoles($user){
		if(!is_array($user['user_role'] || count($user['user_role']) == 1)){
			//if the user doesn't have multiple roles
			return false;
		}

		global $role;

		foreach($user['user_role'] as $user_role){
			if($user_role['rel_table'] == $role){
				$disabled = true;
				$badge = [
					"colour" => "success",
					"icon" => "check",
					"pill" => true,
				];
			} else {
				$disabled = false;
				$badge = false;
			}
			$children[] = [
				"title" => str::title($user_role['rel_table']),
				"badge" => $badge,
				"icon" => Icon::get($user_role['rel_table']),
				"hash" => [
					"rel_table" => "user_role",
					"action" => "switch",
					"vars" => [
						"user_id" => $user['user_id'],
						"new_role" => $user_role['rel_table'],
						"callback" => $this->hash->getCallback(true)
					]
				],
				"disabled" => $disabled
			];
		}

		return [
			"title" => "Switch role",
			"icon" => "random",
			"children" => $children
		];
	}
}