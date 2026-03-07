<?php

namespace App\Common\SystemMailbox;

use App\Common\SQL\Info\InfoInterface;
use App\Common\str;

class Info implements InfoInterface {

	/**
	 * @inheritDoc
	 */
	public static function prepare(array &$a, ?array $joins): void
	{
		$a['db'] = "correspondence";
		$a['left_join'][] = [
			"db" => "app",
			"table" => "oauth_token",
			"on" => "oauth_token_id",
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function format(array &$row): void
	{
		str::flattenSingleChildren($row, [
			"oauth_token",
		]);
	}
}