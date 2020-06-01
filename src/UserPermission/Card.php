<?php


namespace App\Common\UserPermission;


use App\Common\Permission\Field;
use App\Common\str;
use App\UI\Form\Form;
use App\UI\Icon;

class Card extends \App\Common\Common {
	public function selectUser(?array $user = []){
		# The script called every time the select2 value changes
		$script = "ajaxCall(null, \"user_permission\", null, {user_id: $(this).val()});";

		$form = new Form([
			"fields" => [[
				"type" => "select",
				"label" => "User",
				"value" => [$user['user_id'] => $user['full_name']],
				"desc" => "Select a user to see their permissions",
				"ajax" => [
					"rel_table" => "user",
					"action" => "get_options"
				],
				"onChange" => $script
			]],
//			"buttons" => $buttons
		]);

		$card = new \App\UI\Card([
			"body" => $form->getHTML()
		]);

		return $card->getHTML();
	}

	public function userPermission(array $user) : string
	{
		# Get this user's permissions
		if($results = $this->sql->select([
			"columns" => [
				"rel_table",
				"c",
				"r",
				"u",
				"d",
				"count_rel_id" => "COUNT(DISTINCT `rel_id`)"
			],
			"table" => "user_permission",
			"where" => [
				"user_id" => $user['user_id']
			]
		])){
			//if this user has permissions
			foreach($results as $table){
				$user_permissions[$table['rel_table']] = $table;
			}
		}
		# Get the user _role_ permissions
		if($results = $this->sql->select([
			"columns" => [
				"rel_table",
				"c",
				"r",
				"u",
				"d",
				"count_rel_id" => "COUNT(DISTINCT `rel_id`)"
			],
			"table" => "role_permission",
			"join" => [[
				"columns" => false,
				"table" => "role",
				"on" => "role_id",
				"where" => [
					"role" => "user"
				]
			]]
		])){
			//if this user has permissions
			foreach($results as $table){
				$user_role_permissions[$table['rel_table']] = $table;
			}
		}

		$fields = Field::Permission($user_permissions, $user_role_permissions);

		$fields[] = [
			"type" => "hidden",
			"value" => $user['user_id'],
			"name" => "user_id"
		];

		$buttons = ["save","cancel"];

		$form = new Form([
			"action" => "update",
			"rel_table" => "user_permission",
			"rel_id" => $user['user_id'],
			"fields" => $fields,
			"buttons" => $buttons
		]);

		$card = new \App\UI\Card([
			"header" => [
				"icon" => Icon::get("permission"),
				"title" => "Permissions",
			],
			"body" => $form->getHTML(),
			"footer" => [
				"class" => "small text-muted",
				"html" => "Disabled permissions are those set at the role level."
			]
		]);

		return $card->getHTML();
	}
}