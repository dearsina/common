<?php


namespace App\Common\User;

use App\Common\Prototype;
use App\Common\str;
use App\UI\Button;
use App\UI\Form\Form;
use App\UI\Icon;

/**
 * Class Modal
 * @package App\Common\User
 */
class Modal extends Prototype {
	/**
	 * @param string $user_id
	 * @param array  $roles
	 *
	 * @return string
	 * @throws \Exception
	 */
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

		$modal = new \App\UI\Modal\Modal([
			"size" => "s",
			"header" => "Log in as",
			"body" => $buttons,
			"draggable" => true,
		]);

		return $modal->getHTML();
	}

	public function edit(array $a): string
	{
		extract($a);

		$$rel_table = $this->info($rel_table, $rel_id);

		$buttons = ["save","cancel_md"];

		# If the user has elevated permissions
		if($this->permission()->get($rel_table)){
			$fields = Field::edit($$rel_table, true);
		} else {
			//if it's the user itself
			$fields = Field::edit($$rel_table);
		}

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $vars['callback'],
			"fields" => $fields,
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal\Modal([
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

		$buttons[] = Buttons::updateButton();
		$buttons[] = "cancel_md";

		$form = new Form([
			"action" => "send_email_update_email",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $vars['callback'],
			"fields" => Field::editEmail($$rel_table),
			"buttons" => $buttons,
			"modal" => true,
			"encrypt" => ["password"]
		]);

		$modal = new \App\UI\Modal\Modal([
			"icon" => Icon::get("edit"),
			"header" => str::title("Update email address"),
			"body" => $form->getHTML(),
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}

	public function editSignature(array $a): string
	{
		extract($a);

		$user = $this->info($rel_table, $rel_id);

		if($user['signature_id']){
			$buttons[] = Buttons::updateButton();
			$buttons[] = Buttons::removeButton($rel_id);
		}
		else {
			$buttons[] = Buttons::saveButton();
		}
		$buttons[] = "cancel_md";

		$form = new Form([
			"action" => "update_signature",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $vars['callback'],
			"fields" => Field::editSignature($user),
			"buttons" => $buttons,
			"modal" => true,
			"encrypt" => ["password"]
		]);

		$modal = new \App\UI\Modal\Modal([
			"size" => "m",
			"icon" => Icon::get("edit"),
			"header" => str::title("User signature"),
			"body" => $form->getHTML(),
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}

	public function editPassword(array $a, ?string $size = "m"): string
	{
		extract($a);

		$user = $this->info($rel_table, $rel_id);

		# If no key is set, create one
		if(!$user['key']){
			$this->sql->update([
				"table" => $rel_table,
				"id" => $rel_id,
				"set" => [
					"key" => str::uuid()
				],
				"user_id" => $rel_id
			]);
		}

		$buttons = [[
			"type" => "submit",
			"title" => "Update",
			"icon" => Icon::get("save"),
			"colour" => "primary",
		],"cancel_md"];

		$form = new Form([
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"action" => "update_existing_password",
			"fields" => Field::editPassword(),
			"buttons" => $buttons,
			"modal" => true,
			"encrypt" => ["password", "new_password", "repeat_new_password"]
		]);

	    $modal = new \App\UI\Modal\Modal([
			"id" => "edit-password",
			"size" => $size,
			"icon" => "key",
			"header" => str::title("Update password"),
			"body" => $form->getHTML(),
			"draggable" => true,
			"resizable" => true,
		]);

	    return $modal->getHTML();
	}

	public function new(array $a): string
	{
		extract($a);

		$$rel_table = $this->info($rel_table, $rel_id);

		$buttons = [[
			"type" => "submit",
			"title" => "Save",
			"icon" => Icon::get("save"),
			"colour" => "primary",
		],"cancel_md"];

		$form = new Form([
			"action" => "insert",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
//			"callback" => $vars['callback'],
			"fields" => Field::new($$rel_table),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal\Modal([
			"icon" => Icon::get("new"),
			"header" => str::title("New {$rel_table}"),
			"body" => $form->getHTML(),
			"draggable" => true,
			"approve" => "change",
		]);

		return $modal->getHTML();
	}
}