<?php


namespace App\Common\Country;


class Info implements \App\Common\SQL\Info\InfoInterface {

	/**
	 * @inheritDoc
	 */
	public static function prepare(array &$a): void
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
		// TODO: Implement format() method.
	}
}