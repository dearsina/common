<?php

namespace App\Common\CronJob;

use App\Common\SQL\Info\InfoInterface;

/** Ensures generic cron-job forms read and write the dedicated schema. */
final class Info implements InfoInterface {
	public static function prepare(array &$a, ?array $joins): void
	{
		$a["db"] = RuntimePolicy::DB;
	}

	public static function format(array &$row): void
	{
	}
}
