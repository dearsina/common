<?php


namespace App\Common\Role;


use App\Common\str;
use App\UI\Form\Form;
use App\UI\Icon;

/**
 * Class Modal
 * @package App\Common\Role
 */
class Modal extends \App\Common\Prototype {
	/**
	 * @param array $a
	 *
	 * @return string
	 */
	public function all(array $a)
	{
		extract($a);

		$modal = new \App\UI\Modal\Modal([
			"id" => "modal-role-all",
			//			"size" => "l",
			"icon" => Icon::get("roles"),
			"header" => "All roles",
			"body" => [
				"style" => [
					"overflow-y" => "auto",
					"overflow-x" => "hidden",
				],
				"id" => "all_role",
			],
			"footer" => [
				"button" => ["close_md", [
					"hash" => [
						"rel_table" => $rel_table,
						"action" => "new",
					],
					"title" => "New",
					"icon" => Icon::get("new"),
					"colour" => "primary",
					//					"class" => "float-right"
				]],
			],
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}

	public function new(array $a): string
	{
		extract($a);

		$buttons = ["save", "cancel_md"];

		$form = new Form([
			"action" => "insert",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::role($a['vars']),
			"buttons" => $buttons,
			"modal" => true,
		]);

		$modal = new \App\UI\Modal\Modal([
			"id" => "modal-role-new",
			"size" => "s",
			"header" => [
				"icon" => Icon::get("new"),
				"title" => str::title("New {$rel_table}"),
			],
			"body" => $form->getHTML(),
			"approve" => "change",
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}

	/**
	 * @param $a
	 *
	 * @return string
	 */
	public function edit(array $a): string
	{
		extract($a);

		$$rel_table = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id,
		]);

		$buttons = ["save", "cancel_md"];

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::role($$rel_table),
			"buttons" => $buttons,
			"modal" => true,
		]);

		$modal = new \App\UI\Modal\Modal([
			"id" => "modal-role-edit",
			"size" => "s",
			"icon" => Icon::get("edit"),
			"header" => str::title("Edit {$rel_table}"),
			"body" => $form->getHTML(),
			"approve" => "change",
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}
}