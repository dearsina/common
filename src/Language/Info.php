<?php

namespace App\Common\Language;

use App\Common\SQL\Info\InfoInterface;
use App\Common\str;

class Info implements InfoInterface {

	/**
	 * @inheritDoc
	 */
	public static function prepare(array &$a, ?array $joins): void
	{
		$a['left_join'][] = [
			"table" => "country",
			"on" => "country_code",
		];

		if(!key_exists("order_by", $a)){
			$a['order_by'] = [
				"title" => "ASC"
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function format(array &$row): void
	{
		str::flattenSingleChildren($row, [
			"country"
		]);

		$row['icon'] = [
			"type" => "flag",
			"name" => strtolower($row['country_code'])
		];
	}
}