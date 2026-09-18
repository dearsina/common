<?php

namespace App\Common\CronLog;

use App\Common\CronJob\RuntimePolicy;

/**
 * Converts public ledger filters into SQL conditions. Summary scopes keep
 * compound filters out of the URL while ensuring the dashboard count and
 * the rows shown after clicking it use the same definition.
 */
final class RunFilter {
	public const SCOPE_KEY = "run_scope";
	public const TRACK_KEY = "track_run_id";

	private static array $job_ids_by_scope = [];

	public static function normalise($vars): array
	{
		if(!is_array($vars)){
			return [];
		}

		foreach($vars as $key => $value){
			if($value === "NULL"){
				$vars[$key] = NULL;
			}
			else if(is_string($value)){
				$vars[$key] = urldecode($value);
			}
		}

		return $vars;
	}

	public static function toWhere($vars, $sql): array
	{
		$where = self::normalise($vars);
		$scope = (string)($where[self::SCOPE_KEY] ?? "");
		foreach([
			self::SCOPE_KEY,
			self::TRACK_KEY,
			"id",
			"start",
			"length",
			"cursor",
			"order_by_col",
			"order_by_dir",
		] as $control_key){
			unset($where[$control_key]);
		}

		switch($scope){
			case "running":
				$where[] = ["status", "IN", [
					RuntimePolicy::STATUS_RUNNING,
					RuntimePolicy::STATUS_CANCELLING,
				]];
				break;

			case "queued":
				$where["status"] = RuntimePolicy::STATUS_QUEUED;
				break;

			case "succeeded_24h":
				$where[] = ["status", "IN", [
					RuntimePolicy::STATUS_SUCCESS,
					RuntimePolicy::STATUS_WARNING,
				]];
				$where[] = self::last24HoursCondition();
				break;

			case "failed_24h":
				$where[] = ["status", "IN", [
					RuntimePolicy::STATUS_FAILED,
					RuntimePolicy::STATUS_TIMED_OUT,
				]];
				$where[] = self::last24HoursCondition();
				break;

			case "reported_failures_24h":
				$where["result_status"] = RuntimePolicy::RESULT_FAILURE;
				$where[] = self::last24HoursCondition();
				break;

			case "configured":
			case "paused":
				$where[] = ["cron_job_id", "IN", self::jobIds($scope, $sql)];
				break;
		}

		return $where;
	}

	public static function title($vars): ?string
	{
		$scope = (string)(self::normalise($vars)[self::SCOPE_KEY] ?? "");
		return [
			"running" => "Running",
			"queued" => "Queued",
			"succeeded_24h" => "Succeeded in the last 24 hours",
			"failed_24h" => "Failed or timed out in the last 24 hours",
			"reported_failures_24h" => "Reported failures in the last 24 hours",
			"configured" => "Currently configured jobs",
			"paused" => "Currently paused jobs",
		][$scope] ?? NULL;
	}

	private static function last24HoursCondition(): array
	{
		return ["created", ">=", date("Y-m-d H:i:s", strtotime("-24 hours"))];
	}

	private static function jobIds(string $scope, $sql): array
	{
		if(isset(self::$job_ids_by_scope[$scope])){
			return self::$job_ids_by_scope[$scope];
		}

		$query = [
			"db" => RuntimePolicy::DB,
			"columns" => ["cron_job_id"],
			"table" => "cron_job",
		];
		if($scope === "paused"){
			$query["where"] = ["paused" => 1];
		}

		$rows = $sql->select($query);
		if(!$rows || !is_array($rows)){
			$rows = [];
		}
		else if(!array_is_list($rows)){
			$rows = [$rows];
		}

		$ids = array_values(array_filter(array_column($rows, "cron_job_id")));
		// An impossible identifier ensures an empty job scope cannot expose all runs.
		return self::$job_ids_by_scope[$scope] = $ids ?: ["__no_matching_cron_jobs__"];
	}
}
