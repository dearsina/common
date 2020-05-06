<?php


namespace App\Common\User;

use App\Common\Common;
use App\Common\str;
use App\UI\Button;
use App\UI\Icon;

class Modal extends Common {
	public function selectRole(string $user_id, array $roles){
		if(is_array($a))
			extract($a);

		if(empty($roles)){
			throw new \Exception("No roles given to the select role modal.");
		}
		if(!$user_id){
			throw new \Exception("No user ID given to the select role modal.");
		}

		foreach($roles as $role){
			$options[] = [
				"icon" => Icon::get($role['rel_table']),
				"title" => str::title($role['rel_table']),
				"hash" => [
					"rel_table" => "user_role",
					"action" => "switch",
					"vars" => [
						"user_id" => $user_id,
						"new_role" => $role['rel_table'],
						"callback" => $this->hash->get(true)
					]
				],
				"style" => [
					"width" => "100%"
				],
				"colour" => "grey",
				"basic" => true,
			];
		}

		$buttons = Button::multi($options);

		$modal = new \App\UI\Modal([
			"size" => "xs",
			"header" => "Log in as",
			"body" => $buttons,
			"draggable" => true,
//			"resizable" => true,
		]);

		return $modal->getHTML();
	}
}