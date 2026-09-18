<?php

namespace App\Common\CronLog;

use App\Common\CronJob\RuntimePolicy;
use App\Common\str;
use App\UI\Icon;
use App\UI\Table;

class Card extends \App\Common\Prototype {
	private array $summary_rows = [];

	public function cronLog(array $a): string
	{
		$id = str::id("cron_run_ledger");
		$body = Table::onDemand([
			"id" => $id,
			"hash" => [
				"rel_table" => $a["rel_table"] ?? "cron_log",
				"action" => "get_cron_log",
				"vars" => $a["vars"] ?? [],
			],
			"length" => 25,
		]);
		$card = new \App\UI\Card\Card([
			"header" => [
				"icon" => Icon::get("log"),
				"title" => "Execution ledger",
				"button" => $this->cronJobButtons($a),
			],
			"body" => $body,
		]);
		return $card->getHTML();
	}

	private function cronJobButtons(array $a): array
	{
		$buttons = [];
		$vars = $this->normaliseVars($a["vars"] ?? []);
		$cron_job_id = (string)($vars["cron_job_id"] ?? "");
		if(!$cron_job_id){
			return [];
		}

		$job = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_job",
			"id" => $cron_job_id,
		]);
		if(!$job){
			return [];
		}

		$buttons[] = [
			"title" => "Edit",
			"alt" => "Edit this cron job",
			"icon" => Icon::get("edit"),
			"size" => "s",
			"basic" => true,
			"hash" => [
				"rel_table" => "cron_job",
				"rel_id" => $cron_job_id,
				"action" => "edit",
			],
		];

		$is_paused = !empty($job["paused"]);
		$buttons[] = [
			"title" => $is_paused ? "Resume schedule" : "Pause",
			"alt" => $is_paused
				? "Resume running this job on schedule"
				: "Pause this job from running on schedule",
			"icon" => "pause",
			"colour" => "primary",
			"size" => "s",
			"basic" => !$is_paused,
			"hash" => [
				"rel_table" => "cron_job",
				"rel_id" => $cron_job_id,
				"action" => "pause",
				"vars" => ["paused" => $is_paused ? "false" : 1],
			],
		];

		$buttons[] = [
			"title" => "Run now",
			"alt" => "Queue a manual run of this cron job",
			"icon" => "play",
			"colour" => "green",
			"size" => "s",
			"basic" => false,
			"hash" => [
				"rel_table" => "cron_job",
				"rel_id" => $cron_job_id,
				"action" => "run",
				"vars" => [RunFilter::TRACK_KEY => 1],
			],
			"approve" => [
				"colour" => "green",
				"icon" => "play",
				"title" => "Run this cron job?",
				"message" => "A manual run will be queued with normal overlap and timeout protection.",
			],
		];
		$buttons[] = [
			"title" => "Clear log",
			"alt" => "Permanently delete completed run history for this cron job",
			"icon" => Icon::get("trash"),
			"colour" => "danger",
			"size" => "s",
			"basic" => true,
			"hash" => [
				"rel_table" => "cron_log",
				"action" => "clear_job_log",
				"vars" => ["cron_job_id" => $cron_job_id],
			],
			"approve" => [
				"colour" => "red",
				"icon" => Icon::get("trash"),
				"title" => "Clear this cron job's log?",
				"message" => "This permanently deletes every completed run and its structured output for this job. Active and queued runs are kept.",
			],
		];

		return $buttons;
	}

	public function cronLogByStatus(array $a): string
	{
		$vars = $this->normaliseVars($a["vars"] ?? []);
		$results = $this->aggregateSummaryRows($vars, "status", "");

		$rows = [];
		foreach($results as $result){
			$status = (string)$result["status"];
			$hash = $this->filterHash($a, ["status" => urlencode($status) ?: "NULL"]);
			$rows[] = [
				"Status" => [
					"hash" => $hash,
					"html" => str::title(str_replace("_", " ", $status ?: "unknown")),
				],
				"Runs" => ["hash" => $hash, "html" => $result["Runs"]],
			];
		}

		return $this->breakdown($a, ["status"], $rows, "Runs by execution status", "traffic-light");
	}

	public function cronLogByResult(array $a): string
	{
		$vars = $this->normaliseVars($a["vars"] ?? []);
		$results = $this->aggregateSummaryRows($vars, "result_status", RuntimePolicy::RESULT_NONE);

		$rows = [];
		foreach($results as $result){
			$status = (string)($result["result_status"] ?: RuntimePolicy::RESULT_NONE);
			$hash = $this->filterHash($a, ["result_status" => urlencode($status)]);
			$rows[] = [
				"Result" => ["hash" => $hash, "html" => str::title(str_replace("_", " ", $status))],
				"Runs" => ["hash" => $hash, "html" => $result["Runs"]],
			];
		}

		return $this->breakdown($a, ["result_status"], $rows, "Runs by reported result", "list-check");
	}

	public function cronLogByJob(array $a): string
	{
		$vars = $this->normaliseVars($a["vars"] ?? []);
		$results = $this->aggregateSummaryRows($vars, "cron_job_id", "");

		$rows = [];
		foreach($results as $result){
			$job_id = (string)$result["cron_job_id"];
			$job = $job_id ? $this->sql->select(["db" => RuntimePolicy::DB, "table" => "cron_job", "id" => $job_id]) : NULL;
			$snapshot = (!$job && $job_id) ? $this->sql->select([
				"db" => RuntimePolicy::DB,
				"columns" => ["job_title"],
				"table" => "cron_log",
				"where" => ["cron_job_id" => $job_id],
				"order_by" => ["created" => "DESC"],
				"start" => 0,
				"length" => 1,
			]) : NULL;
			if(is_array($snapshot) && array_is_list($snapshot)){
				$snapshot = reset($snapshot);
			}
			$title = $job["title"] ?? $snapshot["job_title"] ?? $job_id ?: "Unknown job";
			$hash = $this->filterHash($a, ["cron_job_id" => urlencode($job_id)]);
			$rows[] = [
				"Job" => ["hash" => $hash, "html" => htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")],
				"Runs" => ["hash" => $hash, "html" => $result["Runs"]],
			];
		}

		return $this->breakdown($a, ["cron_job_id"], $rows, "Runs by job", Icon::get("cron_job"));
	}

	public function cronLogByTrigger(array $a): string
	{
		$vars = $this->normaliseVars($a["vars"] ?? []);
		$results = $this->aggregateSummaryRows($vars, "trigger_type", "legacy");

		$rows = [];
		foreach($results as $result){
			$trigger = (string)($result["trigger_type"] ?: "legacy");
			$hash = $this->filterHash($a, ["trigger_type" => urlencode($trigger)]);
			$rows[] = [
				"Trigger" => ["hash" => $hash, "html" => str::title($trigger)],
				"Runs" => ["hash" => $hash, "html" => $result["Runs"]],
			];
		}

		return $this->breakdown($a, ["trigger_type"], $rows, "Runs by trigger", "bolt");
	}

	private function breakdown(array $a, array $filter_keys, array $rows, string $title, string $icon): string
	{
		$vars = $this->normaliseVars($a["vars"] ?? []);
		$button = NULL;
		if(array_intersect(array_keys($vars), $filter_keys)){
			$cleared = $vars;
			foreach($filter_keys as $key){
				unset($cleared[$key]);
			}
			$button = [
				"title" => "Clear",
				"size" => "s",
				"basic" => true,
				"hash" => [
					"rel_table" => $a["rel_table"] ?? "cron_log",
					"action" => $a["action"] ?? "all",
					"vars" => $cleared,
				],
			];
		}

		$card = new \App\UI\Card\Card([
			"header" => ["icon" => $icon, "title" => $title, "button" => $button],
			"body" => $rows
				? Table::generate($rows)
				: "<div class=\"text-muted text-center p-3\">No runs found.</div>",
		]);
		return $card->getHTML();
	}

	private function filterHash(array $a, array $filter): array
	{
		return [
			"rel_table" => $a["rel_table"] ?? "cron_log",
			"action" => $a["action"] ?? "all",
			"vars" => array_merge($this->normaliseVars($a["vars"] ?? []), $filter),
		];
	}

	private function normaliseVars($vars): array
	{
		return RunFilter::normalise($vars);
	}

	/**
	 * Load every summary dimension in one grouped query. The result is cached on
	 * this Card instance so the four breakdown cards share the same table scan.
	 */
	private function summaryRows(array $vars): array
	{
		$key = md5(serialize($vars));
		if(!array_key_exists($key, $this->summary_rows)){
			$this->summary_rows[$key] = $this->normaliseRows($this->sql->select([
				"db" => RuntimePolicy::DB,
				"columns" => [
					"cron_job_id",
					"status",
					"result_status",
					"trigger_type",
					"Runs" => ["count", "cron_log_id"],
				],
				"table" => "cron_log",
				"where" => RunFilter::toWhere($vars, $this->sql),
				"group_by" => ["cron_job_id", "status", "result_status", "trigger_type"],
			]));
		}

		return $this->summary_rows[$key];
	}

	private function aggregateSummaryRows(array $vars, string $column, string $fallback): array
	{
		$counts = [];
		foreach($this->summaryRows($vars) as $row){
			$value = (string)(($row[$column] ?? NULL) ?: $fallback);
			$counts[$value] = ($counts[$value] ?? 0) + (int)$row["Runs"];
		}
		arsort($counts, SORT_NUMERIC);

		$rows = [];
		foreach($counts as $value => $count){
			$rows[] = [$column => $value, "Runs" => $count];
		}
		return $rows;
	}

	private function normaliseRows($rows): array
	{
		if(!$rows || !is_array($rows)){
			return [];
		}
		return array_is_list($rows) ? $rows : [$rows];
	}
}
