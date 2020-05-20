<?php


namespace App\Common\ErrorLog;


use App\UI\Form\Form;
use App\UI\Icon;

class Modal extends \App\Common\Common {
	public function linkToExistingIssue($a){
		extract($a);

		$buttons[] = [
			"colour" => "primary",
			"icon" => "link",
			"title" => "Link",
			"type" => "submit"
		];

		if($vars['issue_tracker_id']){
			//if this error has already been linked
			$buttons[] = [
				"basic" => true,
				"colour" => "red",
				"icon" => "unlink",
				"alt" => "Remove the existing link between this error and an issue",
				"hash" => [
					"rel_table" => $rel_table,
					"rel_id" => $rel_id,
					"action" => "update",
					"vars" => [
						"issue_tracker_id" => "NULL",
						"id" => $vars['button_id']
					]
				],
				"approve" => true
			];
		}

		$buttons[] = "cancel_md";

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"fields" => Field::existingIssues($vars),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal([
			"size" => "xl",
			"icon" => Icon::get("link"),
			"header" => "Link to existing issue",
			"body" => $form->getHTML(),
			"approve" => "change",
			"draggable" => true,
			"resizable" => true
		]);

		return $modal->getHTML();
	}
}