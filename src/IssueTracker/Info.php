<?php


namespace App\Common\IssueTracker;


use App\Common\str;

/**
 * Class Info
 * @package App\Common\IssueTracker
 */
class Info implements \App\Common\SQL\Info\InfoInterface {

	/**
	 * @inheritDoc
	 */
	public static function prepare(array &$a, ?array $joins): void
	{
		$a['join'][] = [
			"table" => "issue_type",
			"on" => "issue_type_id"
		];
		$a['join'][] = [
			"table" => "issue_priority",
			"on" => "issue_priority_id"
		];
		$a['left_join'][] = [
			"table" => "user",
			"on" => "user_id"
		];
		$a['left_join'][] = [
			"table" => "error_log",
			"on" => "issue_tracker_id"
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function format (array &$row): void
	{
		str::flattenSingleChildren($row, ["user"]);
		$row['progress_percent'] = ($row['progress'] * 100) . "%";
		$row['issue_type'] = $row['issue_type'][0];
		$row['issue_priority'] = $row['issue_priority'][0];
		str::addNames($row['user']);
	}
}