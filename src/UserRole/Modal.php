<?php


namespace App\Common\UserRole;


use App\UI\Form\Form;
use App\UI\Icon;

class Modal extends \App\Common\Common {
	public function all(array $a)
	{
		extract($a);

		# The script called every time the select2 value changes
		$script = "ajaxCall(\"getUserRoles\", \"{$rel_table}\", null, {user_id: $(this).val()});";

		$form = new Form([
			"fields" => [[
				"type" => "select",
				"value" => [$user['user_id'] => $user['full_name']],
				"label" => "Select a user to manage their roles.",
				"ajax" => [
					"rel_table" => "user",
					"action" => "get_options"
				],
				"onChange" => $script
			],[
				"row_id" => "user_roles",
				"row_style" => [
					"float" => "left"
				],
				"button" => $this->getMutedRoleButtons(),
			]],
		]);

		$modal = new \App\UI\Modal\Modal([
//			"size" => "s",
			"icon" => Icon::get("role"),
			"header" => "User roles",
			"body" => $form->getHTML(),
			"draggable" => true,
			"dismissible" => false,
			"footer" => [
				"button" => "close_md"
			]
		]);

		return $modal->getHTML();
	}

	public function getMutedRoleButtons(): array
	{
		foreach($this->sql->select(["table" => "role"]) as $role){
			$buttons[] = [
				"basic" => true,
				"icon" => $role['icon'],
				"disabled" => true
			];
		}
		return $buttons;
	}
}