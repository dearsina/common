<?php

namespace App\Common\CronLog;

use App\Common\CronJob\Runtime;
use App\Common\CronJob\RuntimePolicy;
use App\Common\str;
use App\UI\Badge;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Table;

/**
 * Searchable execution ledger for scheduled and manually triggered cron runs.
 */
class CronLog extends \App\Common\Prototype {
	public ?string $db = RuntimePolicy::DB;

	public function card(?bool $output_to_email = NULL): Card
	{
		return new Card();
	}

	public function all($a): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied($a);
		}

		$a["vars"] = RunFilter::normalise($a["vars"] ?? []);
		$title = "Cron run history";
		if(!empty($a["vars"]["cron_job_id"])){
			$job_title = $this->getJobTitle((string)$a["vars"]["cron_job_id"]);
			if($job_title){
				$title .= ": " . $job_title;
			}
		}
		if($scope_title = RunFilter::title($a["vars"])){
			$title .= ": " . $scope_title;
		}
		$page = new Page([
			"title" => $title,
			"icon" => Icon::get("log"),
		]);
		$card = $this->card();

		$page->setGrid([[
			"html" => $card->cronLogByStatus($a),
		], [
			"html" => $card->cronLogByResult($a),
		], [
			"html" => $card->cronLogByJob($a),
		], [
			"html" => $card->cronLogByTrigger($a),
		]]);
		$page->setGrid(["html" => $card->cronLog($a)]);
		$this->output->html($page->getHTML());
		return true;
	}

	public function cancel(array $a): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied();
		}

		$cron_log_id = (string)($a["rel_id"] ?? "");
		if((new Runtime())->requestRunCancellation($cron_log_id)){
			$this->log->success([
				"icon" => "stop-circle",
				"title" => "Cancellation requested",
				"message" => "The cron worker and its child processes will be terminated.",
			]);
		}
		else {
			$this->log->warning([
				"title" => "Run is no longer active",
				"message" => "No cancellation was necessary.",
			]);
		}

		$this->hash->set(-1);
		return true;
	}

	/**
	 * Retained for compatibility with existing links. The new UI intentionally
	 * does not expose bulk deletion because the ledger is operational evidence.
	 */
	public function removeAll($a): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied();
		}

		$vars = RunFilter::toWhere($a["vars"] ?? [], $this->sql);
		$vars[] = ["status", "NOT IN", RuntimePolicy::ACTIVE_STATUSES];
		$runs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"columns" => ["cron_log_id"],
			"table" => "cron_log",
			"include_removed" => true,
			"where" => $vars,
		]));
		$run_ids = array_values(array_filter(array_column($runs, "cron_log_id")));
		$removed_count = count($run_ids);
		if($run_ids){
			$this->sql->delete([
				"db" => RuntimePolicy::DB,
				"table" => "cron_run_output",
				"where" => [["cron_log_id", "IN", $run_ids]],
				"user_id" => NULL,
			]);
			$this->sql->delete([
				"db" => RuntimePolicy::DB,
				"table" => "cron_log",
				"where" => [["cron_log_id", "IN", $run_ids]],
				"user_id" => NULL,
			]);
		}
		$this->log->success(str::pluralise_if($removed_count, "cron run", true) . " removed.");
		$this->hash->set(["rel_table" => "cron_log", "action" => "all"]);
		return true;
	}

	/**
	 * Permanently clear terminal run history for one explicitly selected job.
	 * Active and queued runs remain available to the scheduler and operator.
	 */
	public function clearJobLog(array $a): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied();
		}

		$vars = RunFilter::normalise($a["vars"] ?? []);
		$cron_job_id = (string)($vars["cron_job_id"] ?? "");
		$job = str::isUuid($cron_job_id) ? $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_job",
			"id" => $cron_job_id,
		]) : NULL;
		if(!$job){
			throw new \InvalidArgumentException("A valid cron job is required before its run history can be cleared.");
		}

		$terminal_where = [
			"cron_job_id" => $cron_job_id,
			["status", "NOT IN", RuntimePolicy::ACTIVE_STATUSES],
		];
		$this->sql->delete([
			"db" => RuntimePolicy::DB,
			"table" => "cron_run_output",
			"include_removed" => true,
			"join" => [[
				"db" => RuntimePolicy::DB,
				"table" => "cron_log",
				"include_removed" => true,
				"on" => "cron_log_id",
				"where" => $terminal_where,
			]],
			"user_id" => NULL,
		]);
		$result = $this->sql->delete([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"include_removed" => true,
			"where" => $terminal_where,
			"user_id" => NULL,
		]);

		$removed_count = (int)($result["affected_rows"] ?? 0);
		$this->log->success(str::pluralise_if($removed_count, "cron run", true) . " permanently deleted.");
		$this->hash->set([
			"rel_table" => "cron_log",
			"action" => "all",
			"vars" => ["cron_job_id" => $cron_job_id],
		]);
		return true;
	}

	public function remove(array $a, ?bool $silent = NULL): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied();
		}
		$run = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"id" => $a["rel_id"],
		]);
		if($run && RuntimePolicy::isActive($run["status"] ?? NULL)){
			throw new \RuntimeException("An active cron run cannot be removed. Cancel it and wait for a terminal status first.");
		}

		$this->sql->remove([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"id" => $a["rel_id"],
		]);
		if(!$silent){
			$this->hash->set(-1);
		}
		return true;
	}

	public function getCronLog($a): bool
	{
		str::replaceNullStrings($a);
		if(!$this->user->is("admin")){
			return $this->accessDenied();
		}

		$request_vars = RunFilter::normalise($a["vars"] ?? []);
		$base_query = [
			"db" => RuntimePolicy::DB,
			"include_meta" => true,
			"table" => "cron_log",
			"where" => RunFilter::toWhere($request_vars, $this->sql),
			"order_by" => [
				"created" => "DESC",
				"cron_log_id" => "DESC",
			],
		];
		// The domain filters are already represented in the base query. Only pass
		// on-demand table controls through to the generic table request handler.
		$a["vars"] = array_intersect_key($request_vars, array_flip([
			"id",
			"start",
			"length",
			"cursor",
			"order_by_col",
			"order_by_dir",
		]));
		Table::managePageRequest($a, $base_query, function(array $run): array {
			return $this->rowHandler($run);
		});
		$this->startTrackedRunRefresh($request_vars);
		return true;
	}

	/** Refresh one visible run without recounting or reloading the full ledger. */
	public function refreshRun(array $a): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied();
		}

		$vars = RunFilter::normalise($a["vars"] ?? []);
		$cron_log_id = (string)($a["rel_id"] ?? "");
		$cron_job_id = (string)($vars["cron_job_id"] ?? "");
		$table_id = preg_replace("/[^a-zA-Z0-9_-]/", "", (string)($vars["table_id"] ?? ""));
		$expanded = filter_var($vars["expanded"] ?? false, FILTER_VALIDATE_BOOL);
		$run = str::isUuid($cron_log_id) ? $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"id" => $cron_log_id,
		]) : NULL;

		if(!$run || !$table_id || ($cron_job_id && $run["cron_job_id"] !== $cron_job_id)){
			$this->sendTrackedRunState($table_id, $cron_log_id, $cron_job_id, false);
			return true;
		}

		$this->output->replace(
			"#" . $this->runRowId($cron_log_id),
			Table::generate([$this->rowHandler($run)], [], true, true)
		);
		$this->sendTrackedRunState(
			$table_id,
			$cron_log_id,
			(string)$run["cron_job_id"],
			RuntimePolicy::isActive($run["status"] ?? NULL),
			$expanded
		);
		return true;
	}

	public function rowHandler(array $run, ?array $a = []): array
	{
		$status = (string)($run["status"] ?? "unknown");
		$display_status = $status === RuntimePolicy::STATUS_QUEUED && !empty($run["launched_at"])
			? "starting"
			: $status;
		$title = $run["job_title"] ?: $this->getJobTitle($run["cron_job_id"] ?? NULL);
		$title = htmlspecialchars((string)($title ?: "Unknown cron job"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
		$class_method = trim((string)($run["job_class"] ?? ""))
			? htmlspecialchars($run["job_class"] . "::" . $run["job_method"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")
			: "Legacy run";
		$scheduled = $run["scheduled_for"] ?: $run["created"];
		$launched = $run["launched_at"];
		$started = $run["started_at"];
		$finished = $run["finished_at"];
		$technical_output = RuntimePolicy::appendOutput($run["output"] ?? NULL, $run["error_message"] ?? NULL);
		$technical_output = $technical_output ?: "No technical output was recorded.";
		$reported_count = array_sum(array_map(static fn($column): int => (int)($run[$column] ?? 0), [
			"reported_successes",
			"reported_warnings",
			"reported_failures",
			"reported_metrics",
		]));
		$reported_output = $reported_count || RuntimePolicy::isActive($status)
			? $this->formatRunOutputs((string)$run["cron_log_id"])
			: "<div class=\"text-muted\">This run did not record structured results.</div>";

		$row["Execution"] = [
			"html" => Badge::generate([[
				"title" => strtoupper(str_replace("_", " ", $display_status)),
				"colour" => $this->statusColour($status),
			]]),
			"sm" => 1,
		];
		$result_status = (string)($run["result_status"] ?? RuntimePolicy::RESULT_NONE);
		$row["Reported result"] = [
			"html" => Badge::generate([[
				"title" => strtoupper(str_replace("_", " ", $result_status)),
				"colour" => $this->resultColour($result_status),
			]]) . $this->formatResultCounts($run),
			"sm" => 1,
		];
		$row["Job and outputs"] = [
			"accordion" => [
				"header" => "{$title}<br><code class=\"small\">{$class_method}</code>",
				"body" => "<h6>Reported results</h6>{$reported_output}"
					. "<hr><h6>Technical output</h6><pre class=\"mb-0 text-wrap\">"
					. htmlspecialchars($technical_output, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</pre>",
			],
		];
		$row["Timing"] = [
			"html" => $this->formatTiming($scheduled, $launched, $started, $finished, $run["duration"] ?? NULL, $run["deadline_at"] ?? NULL),
			"value" => $scheduled,
			"sm" => 2,
		];
		$row["Process"] = [
			"html" => $this->formatExecution($run),
			"sm" => 2,
		];

		$buttons = [];
		if(RuntimePolicy::isActive($status)){
			$buttons[] = [
				"title" => "Cancel this run...",
				"icon" => "stop",
				"colour" => "danger",
				"size" => "s",
				"hash" => [
					"rel_table" => "cron_log",
					"rel_id" => $run["cron_log_id"],
					"action" => "cancel",
				],
				"approve" => [
					"title" => "Cancel cron run?",
					"message" => "The worker and all of its child processes will be terminated.",
					"colour" => "red",
				],
			];
		}
		else if(!empty($run["cron_job_id"])){
			$buttons[] = [
				"title" => "Run this job again...",
				"icon" => "play",
				"colour" => "warning",
				"size" => "s",
				"hash" => [
					"rel_table" => "cron_job",
					"rel_id" => $run["cron_job_id"],
					"action" => "run",
					"vars" => [RunFilter::TRACK_KEY => 1],
				],
				"approve" => [
					"title" => "Run this cron job again?",
					"message" => "A new manual run will be queued with normal overlap and timeout protection.",
					"colour" => "yellow",
				],
			];
		}

		$row["Actions"] = [
			"sortable" => false,
			"buttons" => $buttons,
		];
		return [
			"row_id" => $this->runRowId((string)$run["cron_log_id"]),
			"row_data" => ["cron-log-id" => $run["cron_log_id"]],
			"html" => $row,
		];
	}

	private function startTrackedRunRefresh(array $vars): void
	{
		if((int)($vars["start"] ?? 0) !== 0){
			return;
		}

		$cron_log_id = (string)($vars[RunFilter::TRACK_KEY] ?? "");
		$cron_job_id = (string)($vars["cron_job_id"] ?? "");
		$table_id = preg_replace("/[^a-zA-Z0-9_-]/", "", (string)($vars["id"] ?? ""));
		if(!str::isUuid($cron_log_id) || !$table_id){
			return;
		}

		$run = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"id" => $cron_log_id,
		]);
		$matches_job = $run && (!$cron_job_id || $run["cron_job_id"] === $cron_job_id);
		$this->sendTrackedRunState(
			$table_id,
			$cron_log_id,
			$cron_job_id,
			$matches_job && RuntimePolicy::isActive($run["status"] ?? NULL)
		);
	}

	private function sendTrackedRunState(
		string $table_id,
		string $cron_log_id,
		string $cron_job_id,
		bool $active,
		bool $expanded = false
	): void
	{
		$this->output->function("trackCronLogRun", [
			"table_id" => $table_id,
			"run_id" => $cron_log_id,
			"cron_job_id" => $cron_job_id,
			"active" => $active,
			"expanded" => $expanded,
			"interval_ms" => 2000,
		]);
	}

	private function runRowId(string $cron_log_id): string
	{
		return "cron_log_run_" . str_replace("-", "", $cron_log_id);
	}

	private function formatTiming($scheduled, $launched, $started, $finished, $duration, $deadline): string
	{
		$elapsed_from = $started ?: $launched ?: (!$finished ? $scheduled : NULL);
		if($duration === NULL && $elapsed_from && !$finished){
			$duration = max(0, time() - (strtotime((string)$elapsed_from) ?: time()));
		}
		$lines = [];
		if($scheduled){
			$lines[] = "Scheduled: " . htmlspecialchars((string)$scheduled, ENT_QUOTES, "UTF-8");
		}
		if($launched){
			$lines[] = "Launched: " . htmlspecialchars((string)$launched, ENT_QUOTES, "UTF-8");
		}
		if($started){
			$lines[] = "Started: " . htmlspecialchars((string)$started, ENT_QUOTES, "UTF-8");
		}
		if($finished){
			$lines[] = "Finished: " . htmlspecialchars((string)$finished, ENT_QUOTES, "UTF-8");
		}
		else if($deadline){
			$lines[] = "Deadline: " . htmlspecialchars((string)$deadline, ENT_QUOTES, "UTF-8");
		}
		$duration_label = $started ? "Runtime" : ($launched ? "Launch wait" : "Queue wait");
		$lines[] = "{$duration_label}: " . RuntimePolicy::formatDuration($duration);
		return implode("<br>", $lines);
	}

	private function formatExecution(array $run): string
	{
		$trigger = str::title((string)($run["trigger_type"] ?: "legacy"));
		$lines = ["Trigger: " . htmlspecialchars($trigger, ENT_QUOTES, "UTF-8")];
		if($run["supervisor_pid"]){
			$lines[] = "Supervisor PID: " . (int)$run["supervisor_pid"];
		}
		if($run["worker_pid"]){
			$lines[] = "Worker PID: " . (int)$run["worker_pid"];
		}
		if($run["peak_memory_bytes"]){
			$lines[] = "Peak memory: " . RuntimePolicy::formatBytes($run["peak_memory_bytes"]);
		}
		if($run["exit_code"] !== NULL){
			$lines[] = "Exit code: " . (int)$run["exit_code"];
		}
		if($run["hostname"]){
			$lines[] = "Host: " . htmlspecialchars((string)$run["hostname"], ENT_QUOTES, "UTF-8");
		}
		return implode("<br>", $lines);
	}

	private function formatRunOutputs(string $cron_log_id): string
	{
		$outputs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_run_output",
			"where" => ["cron_log_id" => $cron_log_id],
			"order_by" => ["created" => "ASC", "cron_run_output_id" => "ASC"],
		]));
		if(!$outputs){
			return "<div class=\"text-muted\">This run did not record structured results.</div>";
		}

		$items = [];
		foreach($outputs as $output){
			$status = (string)($output["status"] ?: RuntimePolicy::RESULT_INFO);
			$badge = Badge::generate([[
				"title" => strtoupper($status),
				"colour" => $this->resultColour($status),
			]]);
			if(($output["output_type"] ?? NULL) === "metric"){
				$value = rtrim(rtrim((string)$output["metric_value"], "0"), ".");
				$value = $value === "" ? "0" : $value;
				$text = "<b>" . htmlspecialchars((string)$output["metric_key"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</b>: "
					. htmlspecialchars($value, ENT_QUOTES, "UTF-8");
				if($output["metric_unit"]){
					$text .= " " . htmlspecialchars((string)$output["metric_unit"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
				}
			}
			else {
				$text = nl2br(htmlspecialchars((string)$output["message"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
			}

			$context = $this->formatContext($output["context_json"] ?? NULL);
			$items[] = "<div class=\"mb-2\">{$badge} {$text}{$context}</div>";
		}
		return implode("", $items);
	}

	private function formatContext(?string $json): string
	{
		if(!$json){
			return "";
		}
		$decoded = json_decode($json, true);
		$formatted = is_array($decoded)
			? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			: $json;
		return "<pre class=\"small text-muted mt-1 mb-0 text-wrap\">"
			. htmlspecialchars((string)$formatted, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</pre>";
	}

	private function formatResultCounts(array $run): string
	{
		$parts = [];
		foreach([
			"reported_successes" => ["success", "successes"],
			"reported_warnings" => ["warning", "warnings"],
			"reported_failures" => ["failure", "failures"],
			"reported_metrics" => ["metric", "metrics"],
		] as $column => [$singular, $plural]){
			if((int)($run[$column] ?? 0) > 0){
				$count = (int)$run[$column];
				$parts[] = $count . " " . ($count === 1 ? $singular : $plural);
			}
		}
		return $parts ? "<br><span class=\"small text-muted\">" . implode("; ", $parts) . "</span>" : "";
	}

	private function getJobTitle(?string $cron_job_id): ?string
	{
		if(!$cron_job_id){
			return NULL;
		}
		$job = $this->sql->select(["db" => RuntimePolicy::DB, "table" => "cron_job", "id" => $cron_job_id]);
		return $job["title"] ?? NULL;
	}

	private function statusColour(string $status): string
	{
		return match($status){
			RuntimePolicy::STATUS_SUCCESS => "green",
			RuntimePolicy::STATUS_WARNING => "yellow",
			RuntimePolicy::STATUS_RUNNING => "blue",
			RuntimePolicy::STATUS_QUEUED => "grey",
			RuntimePolicy::STATUS_CANCELLING => "orange",
			RuntimePolicy::STATUS_FAILED, RuntimePolicy::STATUS_TIMED_OUT, "error" => "red",
			RuntimePolicy::STATUS_CANCELLED, RuntimePolicy::STATUS_MISSED, RuntimePolicy::STATUS_SKIPPED_OVERLAP => "black",
			default => "grey",
		};
	}

	private function resultColour(string $status): string
	{
		return match($status){
			RuntimePolicy::RESULT_SUCCESS => "green",
			RuntimePolicy::RESULT_WARNING => "yellow",
			RuntimePolicy::RESULT_FAILURE => "red",
			RuntimePolicy::RESULT_INFO => "blue",
			default => "grey",
		};
	}

	private function normaliseRows($rows): array
	{
		if(!$rows || !is_array($rows)){
			return [];
		}
		return array_is_list($rows) ? $rows : [$rows];
	}
}
