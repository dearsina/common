<?php


namespace App\Common\RolePermission;


use App\Common\Permission\Field;
use App\Common\str;
use App\UI\Form\Form;
use App\UI\Icon;
use App\UI\ListGroup;

/**
 * Class Card
 * @package App\Common\RolePermission
 */
class Card extends \App\Common\Common {
	/**
	 * @param array      $roles
	 * @param array|null $selected_role
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function selectRole(array $roles, ?array $selected_role = []){
		foreach ($roles as $role) {
			$items[] = [
				"icon" => $role['icon'],
				"html" => str::title($role['role']),
				"active" => $role['role'] == $selected_role['role'],
				"hash" => [
					"rel_table" => "role_permission",
					"vars" => [
						"role_id" => $role['role_id']
					]
				],
			];
		}

		$card = new \App\UI\Card([
			"body" => ListGroup::generate([
				"flush" => true,
				"items" => $items,
			]),
		]);

		return $card->getHTML();
	}

	public function rolePermission(array $role) : string
	{
		if($results = $this->sql->select([
			"distinct" => true,
			"columns" => [
				"rel_table",
				"c",
				"r",
				"u",
				"d",
				"count_rel_id" => ["count", "rel_id"],
			],
			"table" => "role_permission",
			"where" => [
				"role_id" => $role['role_id']
			]
		])){
			//if this role has permissions
			foreach($results as $table){
				$role_permissions[$table['rel_table']] = $table;
			}
		}

		$fields = Field::Permission($role_permissions);

		$fields[] = [
			"type" => "hidden",
			"value" => $role['role_id'],
			"name" => "role_id"
		];

		$buttons = ["save","cancel"];

		$form = new Form([
			"action" => "update",
			"rel_table" => "role_permission",
			"rel_id" => $role['role_id'],
			"fields" => $fields,
			"buttons" => $buttons
		]);

		$card = new \App\UI\Card([
			"header" => [
				"icon" => Icon::get("permission"),
				"title" => "Permissions",
			],
			"body" => $form->getHTML(),
		]);

		return $card->getHTML();


	}
}