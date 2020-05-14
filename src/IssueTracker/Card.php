<?php


namespace App\Common\IssueTracker;


use App\UI\Icon;
use App\UI\Table;

class Card extends \App\Common\Common {
	public function issues($a = NULL){
		extract($a);

		$body = Table::onDemand([
			"id" => "all_issue_tracker",
			"hash" => [
				"rel_table" => $rel_table,
				"action" => "get_issues",
				"vars" => $vars
			],
			"length" => 10
		]);

		$card = new \App\UI\Card([
			"header" => [
				"icon" => Icon::get("issue"),
				"title" => "Issues",
				"button" => $button
			],
			"body" => $body,
			"footer" => true
		]);

		return $card->getHTML();

	}
}