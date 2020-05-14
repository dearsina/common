<?php


namespace App\Common\IssueTracker;


use App\Common\Common;
use App\UI\Form\Form;
use App\UI\Icon;

class Modal extends Common {
	public function new($a){
		extract($a);

		$buttons = [[
			"colour" => "green",
			"icon" => "save",
			"title" => "Save",
			"type" => "submit"
		],"cancel_md"];

		$form = new Form([
			"action" => "insert",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::issueTracker($vars),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal([
			"size" => "lg",
			"icon" => Icon::get("new"),
			"header" => "New issue",
			"body" => [
				"html" => $form->getHTML(),
			],
//			"approve" => true,
			"draggable" => true,
//			"dismissable" => false,
		]);

		return $modal->getHTML();
	}
	public function edit($a){
		extract($a);

		$vars = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		$buttons = ["save",[
			"icon" => Icon::get("trash"),
			"colour" => "danger",
			"basic" => true,
			"alt" => "Remove this issue",
			"approve" => [
				"icon" => Icon::get("trash"),
				"title" => "Remove issue?",
				"message" => "Are you sure you want to permanently remove this issue?",
				"colour" => "red"
			],
			"hash" => [
				"rel_table" => $rel_table,
				"rel_id" => $rel_id,
				"action" => "remove"
			]
		],"cancel_md"];

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::issueTracker($vars),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal([
			"size" => "lg",
			"icon" => Icon::get("edit"),
			"header" => "Edit issue",
			"body" => [
				"html" => $form->getHTML(),
			],
			"approve" => true,
			"draggable" => true,
//			"dismissable" => false,
		]);

		return $modal->getHTML();
	}
}