<?php


namespace App\Common\IssueTracker;


use App\Common\Prototype;
use App\Common\ErrorLog\ErrorLog;
use App\Common\IssueNote\IssueNote;
use App\UI\Form\Form;
use App\UI\Icon;

/**
 * Class Modal
 * @package App\Common\IssueTracker
 */
class Modal extends Prototype {
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
//			"modal" => true
		]);

		$body[] = [
			"style" => [
				"width" => "50%",
			],
			"html" => $form->getHTML(),
		];

		if($html = $this->getError($vars['error_log_id'])){
			$body[] = [
				"class" => ["vl", "col-scroll"],
				"html" => [[$html]]
			];
		}

		$modal = new \App\UI\Modal\Modal([
			"size" => "lg",
			"icon" => Icon::get("new"),
			"header" => "New issue",
			"body" => [
				"class" => "col-scroll",
				"html" => [
					"html" => $body
				]
			],
			"approve" => "change",
			"draggable" => true,
			"resizable" => [
				"minHeight" => 650
			],
//			"dismissible" => false,
		]);

		return $modal->getHTML();
	}

	/**
	 * @param null $error_log_id
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function getError($error_log_id = NULL){
		if(!$error_log_id) {
			return false;
		}

		$error = $this->sql->select([
			"table" => "error_log",
			"left_join" => [[
				"table" => "user",
				"on" => [
					"user_id" => ["error_log", "created_by"]
				]
			]],
			"id" => $error_log_id
		]);

		$row = ErrorLog::rowHandler($error);

		$error_fields['class'] = ["vl", "col-scroll"];

		foreach($row as $key => $val){
			if($key == "Actions"){
				continue;
			}
			$error_fields['html'][] = [
				"class" => "h6",
				"style" => [
					"margin-top" => "1rem"
				],
				"html" => $key
			];
			if($val['accordion']){
				$error_fields['html'][]['html'] = $val['accordion']['header'];
				$error_fields['html'][]['html'] = $val['accordion']['body'];
				continue;
			}
			$error_fields['html'][] = $val;
		}

		return $error_fields;

	}

	/**
	 * @param $a
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function edit($a){
		extract($a);

		$vars = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		$buttons = [[
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
			],
			"class" => "float-right",
			"style"=> [
				"margin-right" => "1rem"
			]
		],"save","close_md"];

		$issue_form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::issueTracker($vars),
			"buttons" => $buttons,
//			"modal" => true
			// If set to true, will screw with formatting
		]);

		$note_form = new Form([
			"action" => "insert",
			"rel_table" => "issue_note",
			"fields" => \App\Common\IssueNote\Field::issueNote(["issue_tracker_id" => $rel_id]),
			"buttons" => [[
				"type" => "submit",
				"icon" => "comment-alt-plus",
				"title" => "Add",
				"colour" => "primary",
				"alt" => "Add a new note to this issue"
			]]
		]);

		$in = new IssueNote();
		$in->updateIssueNoteTable($rel_id);

		$modal = new \App\UI\Modal\Modal([
			"size" => "xl",
			"icon" => Icon::get("edit"),
			"header" => "Edit issue",
			"body" => [
				"class" => "col-scroll",
				"html" => [[
					"html" => [[
							"html" => $issue_form->getHTML(),
						],[
							"class" => ["vl","col-scroll"],
							"html" => [[
									"html" => 	$note_form->getHTML(),
								],[
									"id" => "all_issue_note",
								]
							]
						]
					]
				]]
			],
//			"approve" => [
//				"colour" => "warning",
//				"message" => "Any changes you may have made to the issue will not be saved."
//			],
			"approve" => "change",
			"draggable" => true,
			"resizable" => [
				"minHeight" => 650
			],
//			"dismissible" => false,
		]);

		return $modal->getHTML();
	}
}