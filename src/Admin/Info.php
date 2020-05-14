<?php


namespace App\Common\Admin;


use App\Common\str;

class Info extends \App\Common\Common implements \App\Common\SQL\Info\InfoInterface {

	/**
	 * @inheritDoc
	 */
	public function prepare (array &$a): void
	{
		$a['table'] = "user";
		$a['join'][] = [
			"columns" => false,
			"table" => "user_role",
			"on" => "user_id",
			"where" => [
				"rel_table" => "admin"
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function format (array &$row): void
	{
		# Add "name" and "full_name", and format first and last names
		str::addNames($row);

		# Clean up email
		$row['email'] = strtolower($row['email']);
		//email addresses must be parsed as lowercase
		//because they're used in string comparisons
	}
}