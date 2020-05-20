<?php


namespace App\Common\IssueNote;


class Field {
	public static function issueNote($a = NULL)
	{
		if (is_array($a))
			extract($a);

		return [[
			"type" => "textarea",
			"name" => "desc",
			"label" => "Note",
			"required" => true,
			"value" => $desc,
			"rows" => 6,
			"style" => [
				"min-width" => "100%"
			]
		],[
			"type" => "hidden",
			"name" => "issue_tracker_id",
			"value" => $issue_tracker_id
		]];
	}
}