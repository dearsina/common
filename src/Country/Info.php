<?php


namespace App\Common\Country;


class Info implements \App\Common\SQL\Info\InfoInterface {

	/**
	 * @inheritDoc
	 */
	public static function prepare(array &$a, ?array $joins): void
	{
		$a['order_by'] = $a['order_by'] ?: [
			"name" => "ASC",
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function format(array &$row): void
	{
		# The column contains several nationalities, only take the first one
		if(strpos($row['nationality'], ",")){
			$nationalities = explode(", ", $row['nationality']);
			$row['nationality'] = $nationalities[0];
		}
		# TODO Clean the column up some time
	}
}