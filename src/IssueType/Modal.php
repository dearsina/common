<?php


namespace App\Common\IssueType;


use App\UI\Form\Form;
use App\UI\Icon;

/**
 * Class Modal
 * @package App\Common\IssueType
 */
class Modal extends \App\Common\Common {
	/**
	 * @param array $a
	 *
	 * @return string
	 */
	public function all(array $a){
		extract($a);

		$modal = new \App\UI\Modal\Modal([
			"size" => "m",
			"icon" => "cog",
			"header" => "All issue types",
			"body" => [
				"style" => [
					"overflow-y" => "auto",
					"overflow-x" => "hidden",
				],
				"id" => "all_issue_type",
			],
			"footer" => [
				"button" => ["close_md",[
					"hash" => [
						"rel_table" => $rel_table,
						"action" => "new"
					],
					"title" => "New",
					"icon" => Icon::get("new"),
					"colour" => "primary",
//					"class" => "float-right"
				]]
			],
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
	public function edit($a){
		extract($a);

		$issue_type = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		$buttons = ["save","cancel_md"];

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::issueType($issue_type),
			"buttons" => $buttons
		]);

		$modal = new \App\UI\Modal\Modal([
			"size" => "xs",
			"icon" => Icon::get("edit"),
			"header" => "Edit issue type",
			"body" => $form->getHTML(),
			"approve" => "change",
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}

	public function new($a){
		extract($a);

		$buttons = ["save","cancel_md"];

		$form = new Form([
			"action" => "insert",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::issueType($issue_type),
			"buttons" => $buttons
		]);

		$modal = new \App\UI\Modal\Modal([
			"size" => "xs",
			"icon" => Icon::get("new"),
			"header" => "New issue type",
			"body" => $form->getHTML(),
			"approve" => "change",
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}
}