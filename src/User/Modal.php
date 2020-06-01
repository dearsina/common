<?php


namespace App\Common\User;

use App\Common\Common;
use App\Common\str;
use App\UI\Button;
use App\UI\Form\Form;
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

	public function edit(array $a): string
	{
		extract($a);

		$$rel_table = $this->info($rel_table, $rel_id);

		$buttons = ["save","cancel_md"];

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $vars['callback'],
			"fields" => Field::edit($$rel_table),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal([
//			"size" => "xl",
			"icon" => Icon::get("edit"),
			"header" => str::title("Edit {$rel_table}"),
			"body" => $form->getHTML(),
			"approve" => "change",
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}

	public function editEmail(array $a): string
	{
		extract($a);

		$$rel_table = $this->info($rel_table, $rel_id);

		$buttons = [[
			"type" => "submit",
			"title" => "Update",
			"icon" => Icon::get("save"),
			"colour" => "primary",
		],"cancel_md"];

		$form = new Form([
			"action" => "send_email_update_email",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $vars['callback'],
			"fields" => Field::editEmail($$rel_table),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal([
//			"size" => "xl",
			"icon" => Icon::get("edit"),
			"header" => str::title("Update email address"),
			"body" => $form->getHTML(),
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}
}