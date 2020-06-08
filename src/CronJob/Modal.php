<?php


namespace App\Common\CronJob;

use App\Common\str;
use App\UI\Form\Form;
use App\UI\Icon;

/**
 * Class Modal
 * @package App\Common\CronJob
 */
class Modal extends \App\Common\Common {
	public function new($a){
		extract($a);

		$buttons = ["save","cancel_md"];

		$form = new Form([
			"action" => "insert",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::cronJob($a['vars']),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal([
			"size" => "l",
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
	public function edit($a){
		extract($a);

		$$rel_table = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		$buttons = ["save","cancel_md"];

		$form = new Form([
			"action" => "update",
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"callback" => $this->hash->getCallback(),
			"fields" => Field::cronJob($$rel_table),
			"buttons" => $buttons,
			"modal" => true
		]);

		$modal = new \App\UI\Modal([
			"size" => "xl",
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