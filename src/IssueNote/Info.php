<?php


namespace App\Common\IssueNote;


use App\Common\str;

/**
 * Class Info
 * @package App\Common\IssueNote
 */
class Info extends \App\Common\Common implements \App\Common\SQL\Info\InfoInterface {

	/**
	 * @inheritDoc
	 */
	public static function prepare(array &$a, ?array $joins): void
	{
		$a['join'][] = [
			"table" => "user",
			"on" => [
				"user_id" => ["issue_note", "created_by"]
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function format (array &$row): void
	{

		# Add "name" and "full_name", and format first and last names
		str::addNames($row['user']);

		# Clean up email
		$row['email'] = strtolower($row['user']['email']);
		//email addresses must be parsed as lowercase
		//because they're used in string comparisons
	}
}