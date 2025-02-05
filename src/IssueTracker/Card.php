<?php


namespace App\Common\IssueTracker;


use App\Common\str;
use App\UI\Form\Form;
use App\UI\Icon;
use App\UI\Table;

/**
 * Class Card
 * @package App\Common\IssueTracker
 */
class Card extends \App\Common\Prototype {
	/**
	 * @param null $a
	 *
	 * @return string
	 * @throws \Exception
	 */
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

		$card = new \App\UI\Card\Card([
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

	public function filters(array $a): string
	{
	    extract($a);

	    # Issue types
	    if($issue_types = $this->sql->select([
	    	"distinct" => true,
	    	"table" => "issue_type",
			"join" => [[
				"columns" => false,
				"table" => "issue_tracker",
				"on" => "issue_type_id"
			]]
		])){
	    	foreach($issue_types as $issue_type){
				$filters['issue_type_id']['title'] = "Type";
				$filters['issue_type_id']['options'][$issue_type['issue_type_id']] = $issue_type['title'];
			}
		}

	    # Issue priorities
	    if($issue_priorities = $this->sql->select([
	    	"distinct" => true,
	    	"table" => "issue_priority",
			"join" => [[
				"columns" => false,
				"table" => "issue_tracker",
				"on" => "issue_priority_id"
			]]
		])){
	    	foreach($issue_priorities as $issue_priority){
				$filters['issue_priority_id']['title'] = "Priority";
				$filters['issue_priority_id']['options'][$issue_priority['issue_priority_id']] = $issue_priority['title'];
			}
		}

		$completeds = $this->sql->select([
			"distinct" => true,
			"columns" => "progress",
			"table" => "issue_tracker",
			"order_by" => [
				"progress" => "ASC"
			]
		]);
		foreach($completeds as $completed){
			$filters['progress']['title'] = "Progress";
			$filters['progress']['options'][$completed['progress']] = str::percent($completed['progress']);
		}

		return Table::getFilterCard($filters, "all_issue_tracker");
	}
}