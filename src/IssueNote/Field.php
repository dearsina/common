<?php


namespace App\Common\IssueNote;


use App\Common\str;

/**
 * Class Field
 * @package App\Common\IssueNote
 */
class Field {
	/**
	 * @param null $a
	 *
	 * @return array[]
	 */
	public static function issueNote($a = NULL)
	{
		if (is_array($a))
			extract($a);

		$textarea_id = str::id("textarea");

		return [[
			"type" => "textarea",
			"id" => $textarea_id,
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
			"name" => "textarea_id",
			"value" => $textarea_id
		],[
			"type" => "hidden",
			"name" => "issue_tracker_id",
			"value" => $issue_tracker_id
		]];
	}
}