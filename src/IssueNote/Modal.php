<?php


namespace App\Common\IssueNote;


use App\Common\str;
use App\UI\Form\Form;
use App\UI\Icon;

/**
 * Class Modal
 * @package App\Common\IssueNote
 */
class Modal extends \App\Common\Common {
	/**
	 * @param $a
	 *
	 * @return string
	 */
	public function edit($a){
		extract($a);

		$issue_type = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		$buttons = [[
			"colour" => "green",
			"icon" => "save",
			"title" => "Save",
			"type" => "submit"
		],"cancel_md"];

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::issueNote($issue_type),
			"buttons" => $buttons
		]);

		$id = str::id("modal");

		$modal = new \App\UI\Modal\Modal([
			"id" => $id,
			"size" => "l",
			"icon" => Icon::get("edit"),
			"header" => str::title("Edit {$rel_table}"),
			"body" => $form->getHTML(),
			"approve" => "change",
			"draggable" => true,
			"resizable" => [
				"alsoResize" => "#{$id} textarea"
			],
		]);

		return $modal->getHTML();
	}
}