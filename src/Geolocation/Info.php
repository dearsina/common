<?php

namespace App\Common\Geolocation;

use App\Common\SQL\Info\InfoInterface;
use App\Common\str;

class Info implements InfoInterface {

	/**
	 * @inheritDoc
	 */
	public static function prepare(array &$a, ?array $joins): void
	{
		// TODO: Implement prepare() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function format(array &$row): void
	{
		str::flattenSingleChildren($row, [
			"carrier",
			"currency",
			"languages",
			"time_zone"
		]);
	}
}