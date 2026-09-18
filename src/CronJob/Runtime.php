<?php

namespace App\Common\CronJob;

use App\Common\Prototype;
use App\Common\SQL\Factory;
use App\Common\str;
use Cron\CronExpression;

/**
 * Durable cron dispatcher and isolated worker runtime.
 *
 * runScheduled() is deliberately short lived. It reconciles previous workers,
 * records the current minute's due work, starts as many queued runs as capacity
 * permits, and exits. The actual application method always runs in a separate,
 * supervised PHP process.
 */
class Runtime extends Prototype {
	private const DISPATCHER_LOCK = "kycdd:cron-dispatcher:v2";
	private const JOB_LOCK_PREFIX = "kycdd:cron-job:";
	private const DEFAULT_MAX_CONCURRENT_RUNS = 4;
	private const MAX_CONCURRENT_RUNS_LIMIT = 20;
	private const CANCEL_GRACE_SECONDS = 30;
	private const TIMEOUT_TERMINATION_GRACE_SECONDS = 5;
	private const SIGNAL_TERM = 15;
	private const SIGNAL_KILL = 9;
	private const SCHEDULER_STATE_ID = "00000000-0000-0000-0000-000000000001";
	private static ?string $current_worker_lock = NULL;

	public function runScheduled(): bool
	{
		str::runFromCLI(true);
		$this->setSystemUser();

		if(!$this->acquireNamedLock(self::DISPATCHER_LOCK)){
			return true;
		}

		$started_at = microtime(true);
		try {
			$this->updateSchedulerState([
				"status" => "running",
				"last_started" => date("Y-m-d H:i:s"),
				"last_finished" => NULL,
				"duration" => NULL,
				"hostname" => gethostname() ?: NULL,
				"dispatcher_pid" => getmypid(),
				"error_message" => NULL,
			]);
			$this->reconcileActiveRuns();
			$this->queueDueRuns();
			$this->startQueuedRuns();
			$this->pruneHistoryIfDue();
			$this->updateSchedulerState([
				"status" => "success",
				"last_finished" => date("Y-m-d H:i:s"),
				"duration" => max(0, microtime(true) - $started_at),
				"hostname" => gethostname() ?: NULL,
				"dispatcher_pid" => getmypid(),
				"error_message" => NULL,
			]);
		}
		catch(\Throwable $throwable) {
			try {
				$this->updateSchedulerState([
					"status" => "failed",
					"last_finished" => date("Y-m-d H:i:s"),
					"duration" => max(0, microtime(true) - $started_at),
					"hostname" => gethostname() ?: NULL,
					"dispatcher_pid" => getmypid(),
					"error_message" => substr(get_class($throwable) . ": " . $throwable->getMessage(), 0, 65535),
				]);
			}
			catch(\Throwable $ignored) {
			}
			throw $throwable;
		}
		finally {
			$this->releaseNamedLock(self::DISPATCHER_LOCK);
		}

		return true;
	}

	/**
	 * Queue a manually requested execution. The system scheduler launches it on
	 * its next tick so every worker runs under the same operating-system account.
	 * Manual runs use the same overlap and timeout rules as scheduled work.
	 */
	public function queueManualRun(string $cron_job_id): string
	{
		if(!$this->acquireNamedLock(self::DISPATCHER_LOCK)){
			throw new \RuntimeException("The cron dispatcher is busy. Please try again in a few seconds.");
		}

		try {
			$this->reconcileActiveRuns();

			if($this->getActiveRunForJob($cron_job_id)){
				throw new \RuntimeException("This cron job already has an active or queued execution.");
			}

			$job = $this->getJob($cron_job_id);
			if(!$job){
				throw new \RuntimeException("The cron job cannot be found.");
			}

			$now = date("Y-m-d H:i:s");
			$nonce = bin2hex(random_bytes(12));
			$run_id = $this->insertRun($job, [
				"dispatch_key" => RuntimePolicy::manualDispatchKey($cron_job_id, $nonce),
				"trigger_type" => "manual",
				"status" => RuntimePolicy::STATUS_QUEUED,
				"scheduled_for" => $now,
			]);

			if(!$run_id){
				throw new \RuntimeException("The manual cron run could not be queued.");
			}

			return $run_id;
		}
		finally {
			$this->releaseNamedLock(self::DISPATCHER_LOCK);
		}
	}

	/**
	 * CLI entry point for an isolated job worker.
	 */
	public function runWorker(string $cron_log_id): int
	{
		str::runFromCLI(true);
		$this->setSystemUser();

		$run = $this->getRun($cron_log_id);
		if(!$run || ($run["status"] ?? NULL) !== RuntimePolicy::STATUS_QUEUED){
			return 0;
		}

		$job = $this->getJob((string)$run["cron_job_id"]);
		if(!$job){
			$this->finishRun(
				$run,
				RuntimePolicy::STATUS_FAILED,
				"The cron job was removed before its worker started.",
				"MissingCronJob",
				"The cron job configuration no longer exists.",
				1
			);
			return 1;
		}

		$job_lock = self::JOB_LOCK_PREFIX . $job["cron_job_id"];
		if(!$this->acquireNamedLock($job_lock)){
			$this->finishRun(
				$run,
				RuntimePolicy::STATUS_SKIPPED_OVERLAP,
				"Another execution of this cron job already owns its worker lock.",
				NULL,
				NULL,
				0
			);
			return 0;
		}
		self::$current_worker_lock = $job_lock;

		$started_at = microtime(true);
		$completed = false;
		$captured_output = "";
		$output_truncated = false;
		$base_buffer_level = ob_get_level();

		$worker_exit_code = 0;
		try {
			$timeout = RuntimePolicy::timeout($run["timeout_seconds"] ?? $job["timeout_seconds"] ?? NULL);
			$started = date("Y-m-d H:i:s");
			$enforcement_seconds = $this->timeoutEnforcementSeconds($timeout);
			$launch_deadline = strtotime((string)($run["deadline_at"] ?? "")) ?: (time() + $enforcement_seconds);
			$deadline = date("Y-m-d H:i:s", min($launch_deadline, time() + $enforcement_seconds));

			$claim = $this->sql->update([
				"db" => RuntimePolicy::DB,
				"table" => "cron_log",
				"id" => $cron_log_id,
				"where" => ["status" => RuntimePolicy::STATUS_QUEUED],
				"set" => [
					"status" => RuntimePolicy::STATUS_RUNNING,
					"worker_pid" => getmypid(),
					"process_group_id" => function_exists("posix_getpgrp") ? posix_getpgrp() : NULL,
					"hostname" => gethostname() ?: NULL,
					"started_at" => $started,
					"deadline_at" => $deadline,
				],
				"user_id" => NULL,
			]);
			if((int)($claim["affected_rows"] ?? 0) !== 1){
				return 0;
			}

			$this->sql->update([
				"db" => RuntimePolicy::DB,
				"table" => "cron_job",
				"id" => $job["cron_job_id"],
				"set" => [
					"pid" => $run["supervisor_pid"] ?: getmypid(),
					"last_started" => $started,
					"last_status" => RuntimePolicy::STATUS_RUNNING,
				],
				"user_id" => NULL,
			]);

			register_shutdown_function(function() use ($cron_log_id, &$completed, $started_at, &$captured_output): void {
				if($completed){
					return;
				}

				$this->recordWorkerShutdown($cron_log_id, error_get_last(), $started_at, $captured_output);
			});

			$this->log->clearAlerts();
			$this->log->startTimer();

			ob_start(function(string $chunk) use (&$captured_output, &$output_truncated): string {
				$remaining = RuntimePolicy::MAX_CAPTURED_OUTPUT_BYTES - strlen($captured_output);
				if($remaining > 0){
					$captured_output .= substr($chunk, 0, $remaining);
				}
				if(strlen($chunk) > $remaining){
					$output_truncated = true;
				}
				return "";
			}, 8192);

			$class = (string)$run["job_class"];
			$method = (string)$run["job_method"];
			if(!$class || !class_exists($class)){
				throw new \RuntimeException("The configured cron class {$class} cannot be loaded.");
			}

			$class_instance = new $class();
			if(!str::methodAvailable($class_instance, $method)){
				throw new \RuntimeException("The configured cron method {$class}::{$method} is unavailable.");
			}

			RunOutput::begin($cron_log_id);
			$result = $class_instance->{$method}();
			$this->closeWorkerOutputBuffers($base_buffer_level);
			if($output_truncated){
				$captured_output = RuntimePolicy::appendOutput($captured_output, "[Process output exceeded the capture limit and was truncated.]");
			}

			$output = RuntimePolicy::appendOutput($this->log->getAlertMessages(), $captured_output);
			$output = RuntimePolicy::appendOutput($output, $this->normaliseResult($result));
			// Application log warnings/errors remain diagnostic output. Only the
			// method contract or an exception determines execution success.
			$status = RuntimePolicy::executionStatusForResult($result);
			$this->finishRun(
				$run,
				$status,
				$output,
				$result === false ? "CronMethodReturnedFalse" : NULL,
				$result === false ? "The cron method returned false." : NULL,
				$result === false ? 1 : 0,
				$started_at
			);
			$worker_exit_code = $result === false ? 1 : 0;
			$completed = true;
		}
		catch(\Throwable $throwable) {
			$this->closeWorkerOutputBuffers($base_buffer_level);
			$output = RuntimePolicy::appendOutput($this->log->getAlertMessages(), $captured_output);
			$output = RuntimePolicy::appendOutput($output, $throwable->getMessage());

			$this->finishRun(
				$run,
				RuntimePolicy::STATUS_FAILED,
				$output,
				get_class($throwable),
				$throwable->getMessage(),
				1,
				$started_at
			);
			$worker_exit_code = 1;
			$completed = true;
		}
		finally {
			RunOutput::end();
			$this->closeWorkerOutputBuffers($base_buffer_level);
			if(self::$current_worker_lock === $job_lock){
				$this->releaseNamedLock($job_lock);
				self::$current_worker_lock = NULL;
			}
		}

		return $worker_exit_code;
	}

	/**
	 * Release the current cron worker's second-line named lock before starting a
	 * process that will fork or daemonize. The active cron_log row continues to
	 * prevent another dispatcher/manual run from overlapping this execution.
	 *
	 * Without this hand-off, a daemon can inherit the MySQL socket and retain the
	 * named lock after the short-lived cron worker exits.
	 */
	public static function releaseCurrentWorkerLockBeforeDaemonizing(): bool
	{
		$name = self::$current_worker_lock;
		if(!$name){
			return true;
		}

		$name = preg_replace("/[^a-zA-Z0-9:_-]/", "", $name);
		$rows = Factory::getInstance()->select("SELECT RELEASE_LOCK('{$name}') AS `released`");
		$released = (int)($rows[0]["released"] ?? 0) === 1;
		if($released){
			self::$current_worker_lock = NULL;
		}

		return $released;
	}

	/**
	 * Called by the detached shell supervisor after the worker exits. This is
	 * deliberately a second PHP invocation: it can record hard kills, crashes,
	 * and bootstrap failures which never reach runWorker()'s shutdown handler.
	 */
	public function recordSupervisorExit(string $cron_log_id, int $exit_code): void
	{
		str::runFromCLI(true);
		$this->setSystemUser();
		$exit_code = max(0, min(255, $exit_code));
		$supervisor_output = $this->readSupervisorOutput($cron_log_id);
		$processed = false;

		try {
			$run = $this->getRun($cron_log_id);
			if(!$run){
				$processed = true;
				return;
			}

			if(RuntimePolicy::isTerminal($run["status"] ?? NULL)){
				$set = ["exit_code" => $exit_code];
				if($supervisor_output){
					$set["output"] = RuntimePolicy::appendOutput(
						$run["output"] ?? NULL,
						"[Supervisor output]\n" . $supervisor_output
					);
				}
				$this->sql->update([
					"db" => RuntimePolicy::DB,
					"table" => "cron_log",
					"id" => $cron_log_id,
					"set" => $set,
					"user_id" => NULL,
				]);
				$processed = true;
				return;
			}

			if(($run["status"] ?? NULL) === RuntimePolicy::STATUS_CANCELLING){
				$this->finishRun(
					$run,
					RuntimePolicy::STATUS_CANCELLED,
					RuntimePolicy::appendOutput($supervisor_output, "The worker stopped after cancellation was requested."),
					NULL,
					NULL,
					$exit_code ?: 143
				);
				$processed = true;
				return;
			}

			if($exit_code === 124){
				$this->finishRun(
					$run,
					RuntimePolicy::STATUS_TIMED_OUT,
					RuntimePolicy::appendOutput($supervisor_output, "The worker reached its supervised runtime limit."),
					"CronTimeout",
					"The timeout supervisor terminated the worker.",
					$exit_code
				);
				$processed = true;
				return;
			}

			// Some successful methods intentionally daemonize. Their short-lived
			// worker parent exits cleanly before it can return through runWorker().
			if($exit_code === 0){
				$this->finishRun(
					$run,
					RuntimePolicy::STATUS_SUCCESS,
					$supervisor_output,
					NULL,
					NULL,
					0
				);
				$processed = true;
				return;
			}

			$started = !empty($run["started_at"]);
			$error_type = $started ? "UnexpectedWorkerExit" : "WorkerBootstrapFailure";
			$error_message = $started
				? "The worker process exited before recording a terminal status."
				: "The worker process exited before it could claim the queued run.";
			$technical_output = RuntimePolicy::appendOutput(
				$supervisor_output,
				$error_message . " Supervisor exit code: {$exit_code}."
			);
			$this->finishRun(
				$run,
				RuntimePolicy::STATUS_FAILED,
				$technical_output,
				$error_type,
				$error_message,
				$exit_code
			);
			$processed = true;
		}
		finally {
			// Leave artifacts in place when persistence failed so that the next
			// dispatcher tick can make another recovery attempt.
			if($processed){
				$this->removeSupervisorArtifacts($cron_log_id);
			}
		}
	}

	/**
	 * Ask the scheduler to stop a specific run. The web process makes a best
	 * effort to send TERM; the next privileged scheduler tick completes the kill.
	 */
	public function requestRunCancellation(string $cron_log_id): bool
	{
		$run = $this->getRun($cron_log_id);
		if(!$run || !RuntimePolicy::isActive($run["status"] ?? NULL)){
			return false;
		}

		$cancel = $this->sql->update([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"id" => $cron_log_id,
			"where" => [["status", "IN", RuntimePolicy::ACTIVE_STATUSES]],
			"set" => [
				"status" => RuntimePolicy::STATUS_CANCELLING,
				"cancel_requested_at" => "NOW()",
				"output" => RuntimePolicy::appendOutput($run["output"] ?? NULL, "Cancellation requested by an administrator."),
			],
			"user_id" => NULL,
		]);
		if((int)($cancel["affected_rows"] ?? 0) !== 1){
			return false;
		}

		if(empty($run["launched_at"]) && empty($run["supervisor_pid"]) && empty($run["worker_pid"])){
			$this->finishRun($run, RuntimePolicy::STATUS_CANCELLED, "Cancelled before the worker was launched.", NULL, NULL, 143);
			return true;
		}

		$this->signalRun($run, self::SIGNAL_TERM);
		return true;
	}

	public function requestJobCancellation(string $cron_job_id): int
	{
		$runs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"where" => [
				"cron_job_id" => $cron_job_id,
				["status", "IN", RuntimePolicy::ACTIVE_STATUSES],
			],
		]));

		$count = 0;
		foreach($runs as $run){
			if($this->requestRunCancellation($run["cron_log_id"])){
				$count++;
			}
		}
		return $count;
	}

	public function getActiveRuns(?string $cron_job_id = NULL): array
	{
		$where = [["status", "IN", RuntimePolicy::ACTIVE_STATUSES]];
		if($cron_job_id){
			$where["cron_job_id"] = $cron_job_id;
		}

		return $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"where" => $where,
			"order_by" => [
				"scheduled_for" => "ASC",
				"created" => "ASC",
			],
		]));
	}

	private function queueDueRuns(): void
	{
		$jobs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_job",
			"where" => ["paused" => NULL],
			"order_by" => ["order" => "ASC"],
		]));

		$scheduled_for = date("Y-m-d H:i:00");
		$run_time = new \DateTimeImmutable($scheduled_for, new \DateTimeZone("UTC"));

		foreach($jobs as $job){
			try {
				if(!CronExpression::factory($job["interval"])->isDue($run_time)){
					continue;
				}
			}
			catch(\Throwable $throwable) {
				$this->recordConfigurationFailure($job, $throwable);
				continue;
			}

			$dispatch_key = RuntimePolicy::scheduledDispatchKey($job["cron_job_id"], $scheduled_for);
			if($this->getActiveRunForJob($job["cron_job_id"])){
				$this->insertRun($job, [
					"dispatch_key" => $dispatch_key,
					"trigger_type" => "scheduled",
					"status" => RuntimePolicy::STATUS_SKIPPED_OVERLAP,
					"scheduled_for" => $scheduled_for,
					"finished_at" => "NOW()",
					"duration" => 0,
					"output" => "Skipped because this job already has an active or queued execution.",
				]);
				continue;
			}

			$this->insertRun($job, [
				"dispatch_key" => $dispatch_key,
				"trigger_type" => "scheduled",
				"status" => RuntimePolicy::STATUS_QUEUED,
				"scheduled_for" => $scheduled_for,
			]);
		}
	}

	private function insertRun(array $job, array $set): ?string
	{
		$set = array_merge([
			"cron_job_id" => $job["cron_job_id"],
			"job_title" => $job["title"] ?? NULL,
			"job_class" => $job["class"] ?? NULL,
			"job_method" => $job["method"] ?? NULL,
			"job_interval" => $job["interval"] ?? NULL,
			"timeout_seconds" => RuntimePolicy::timeout($job["timeout_seconds"] ?? NULL),
		], $set);
		if(!empty($set["dispatch_key"])){
			$existing = $this->sql->select([
				"db" => RuntimePolicy::DB,
				"columns" => ["cron_log_id"],
				"table" => "cron_log",
				"include_removed" => true,
				"where" => ["dispatch_key" => $set["dispatch_key"]],
				"start" => 0,
				"length" => 1,
			]);
			if($existing){
				return NULL;
			}
		}

		try {
			return $this->sql->insert([
				"db" => RuntimePolicy::DB,
				"table" => "cron_log",
				"set" => $set,
				"user_id" => NULL,
			]);
		}
		catch(\Throwable $exception) {
			if((int)$exception->getCode() === 1062){
				return NULL;
			}
			throw $exception;
		}
	}

	private function startQueuedRuns(): void
	{
		$capacity = $this->maxConcurrentRuns() - $this->inFlightRunCount();
		if($capacity <= 0){
			return;
		}

		$queued_runs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"where" => [
				"status" => RuntimePolicy::STATUS_QUEUED,
				"launched_at" => NULL,
			],
			"order_by" => [
				"scheduled_for" => "ASC",
				"created" => "ASC",
			],
		]));

		foreach($queued_runs as $run){
			if($capacity-- <= 0){
				break;
			}

			$job = $this->getJob((string)$run["cron_job_id"]);
			if(!$job){
				$this->finishRun($run, RuntimePolicy::STATUS_MISSED, "The job was removed while this run was queued.");
				continue;
			}

			$timeout = RuntimePolicy::timeout($run["timeout_seconds"] ?? NULL);
			$launched_at = date("Y-m-d H:i:s");
			$deadline_at = date("Y-m-d H:i:s", time() + $this->timeoutEnforcementSeconds($timeout));

			$launch_claim = $this->sql->update([
				"db" => RuntimePolicy::DB,
				"table" => "cron_log",
				"id" => $run["cron_log_id"],
				"where" => [
					"status" => RuntimePolicy::STATUS_QUEUED,
					"launched_at" => NULL,
				],
				"set" => [
					"launched_at" => $launched_at,
					"deadline_at" => $deadline_at,
					"hostname" => gethostname() ?: NULL,
				],
				"user_id" => NULL,
			]);
			if((int)($launch_claim["affected_rows"] ?? 0) !== 1){
				continue;
			}

			try {
				$pid = $this->startSupervisor($run["cron_log_id"], $timeout);
				if($pid < 1){
					throw new \RuntimeException("The detached cron worker returned no process ID.");
				}

				$this->sql->update([
					"db" => RuntimePolicy::DB,
					"table" => "cron_log",
					"id" => $run["cron_log_id"],
					"set" => [
						"supervisor_pid" => $pid,
						// setsid makes the detached shell the process-group leader.
						// Publishing it here also makes pre-bootstrap runs cancellable.
						"process_group_id" => $pid,
					],
					"user_id" => NULL,
				]);
			}
			catch(\Throwable $throwable) {
				$run["launched_at"] = $launched_at;
				$this->finishRun(
					$run,
					RuntimePolicy::STATUS_FAILED,
					$throwable->getMessage(),
					get_class($throwable),
					$throwable->getMessage(),
					1
				);
			}
		}
	}

	private function startSupervisor(string $cron_log_id, int $timeout): int
	{
		$bootstrap = $_ENV["cron_bootstrap_path"] ?? "/var/www/html/app/settings.php";
		$php_binary = PHP_BINARY ?: "php";
		$code = "require " . var_export($bootstrap, true) . ";"
			. "exit((new \\App\\Common\\CronJob\\Runtime())->runWorker("
			. var_export($cron_log_id, true) . "));";

		$worker_command = escapeshellarg($php_binary) . " -r " . escapeshellarg($code);
		$timeout_binary = $_ENV["cron_timeout_binary"] ?? "/usr/bin/timeout";
		if(!is_executable($timeout_binary)){
			throw new \RuntimeException("The cron timeout supervisor is not executable at {$timeout_binary}.");
		}
		$worker_command = escapeshellarg($timeout_binary)
			. " --signal=TERM --kill-after=" . self::TIMEOUT_TERMINATION_GRACE_SECONDS . "s "
			. $this->timeoutEnforcementSeconds($timeout) . "s " . $worker_command;

		$setsid_binary = $_ENV["cron_setsid_binary"] ?? "/usr/bin/setsid";
		if(!is_executable($setsid_binary)){
			throw new \RuntimeException("The cron process-group supervisor is not executable at {$setsid_binary}.");
		}

		$log_path = $this->supervisorArtifactPath($cron_log_id, "log");
		$exit_path = $this->supervisorArtifactPath($cron_log_id, "exit");
		@unlink($log_path);
		@unlink($exit_path);

		$finalizer_code = "require " . var_export($bootstrap, true) . ";"
			. "(new \\App\\Common\\CronJob\\Runtime())->recordSupervisorExit("
			. var_export($cron_log_id, true) . ", (int)(\$argv[1] ?? 1));";
		$finalizer_command = escapeshellarg($php_binary) . " -r " . escapeshellarg($finalizer_code)
			. " -- \"\$cron_exit_code\"";
		$shell_code = $worker_command
			. "; cron_exit_code=\$?; printf '%s\\n' \"\$cron_exit_code\" > " . escapeshellarg($exit_path)
			. "; " . $finalizer_command;
		$command = escapeshellarg($setsid_binary) . " /bin/sh -c " . escapeshellarg($shell_code);
		$detached_command = "nohup {$command} >> " . escapeshellarg($log_path) . " 2>&1 < /dev/null & echo \$!";

		$output = [];
		$launch_exit_code = 0;
		exec(str_replace(chr(0), "", $detached_command), $output, $launch_exit_code);
		$pid = (int)($output[0] ?? 0);
		if($launch_exit_code !== 0 || $pid < 1){
			throw new \RuntimeException("The detached cron supervisor could not be started.");
		}

		return $pid;
	}

	private function reconcileActiveRuns(): void
	{
		$runs = $this->getActiveRuns();
		$now = time();

		foreach($runs as $run){
			$status = $run["status"] ?? NULL;

			if($status === RuntimePolicy::STATUS_CANCELLING){
				if(!$this->isLocalRun($run)){
					$deadline = strtotime((string)($run["deadline_at"] ?? "")) ?: 0;
					if($deadline && $deadline <= $now){
						$this->finishRun($run, RuntimePolicy::STATUS_CANCELLED, "Cancellation reached the worker's hard deadline.", NULL, NULL, 143);
					}
					continue;
				}

				$this->signalRun($run, self::SIGNAL_TERM);
				$requested = strtotime((string)($run["cancel_requested_at"] ?? "")) ?: 0;
				if(!$this->isRunAlive($run) || ($requested && $requested <= $now - self::CANCEL_GRACE_SECONDS)){
					if($this->isRunAlive($run)){
						$this->signalRun($run, self::SIGNAL_KILL);
					}
					$this->finishRun($run, RuntimePolicy::STATUS_CANCELLED, "Cancelled by an administrator.", NULL, NULL, 143);
				}
				continue;
			}

			$deadline = strtotime((string)($run["deadline_at"] ?? "")) ?: 0;
			if($deadline && $deadline <= $now){
				$this->finishRun(
					$run,
					RuntimePolicy::STATUS_TIMED_OUT,
					"The worker exceeded its hard runtime limit of " . RuntimePolicy::formatDuration($run["timeout_seconds"] ?? NULL) . ".",
					"CronTimeout",
					"Hard runtime limit exceeded.",
					124
				);
				$this->signalRun($run, self::SIGNAL_TERM);
				usleep(250000);
				if($this->isRunAlive($run)){
					$this->signalRun($run, self::SIGNAL_KILL);
				}
				continue;
			}

			if($status === RuntimePolicy::STATUS_QUEUED && !$run["launched_at"]){
				$scheduled = strtotime((string)($run["scheduled_for"] ?? $run["created"] ?? "")) ?: $now;
				if($scheduled <= $now - RuntimePolicy::QUEUE_MAX_AGE_SECONDS){
					$this->finishRun($run, RuntimePolicy::STATUS_MISSED, "The run expired in the queue before worker capacity became available.");
				}
				continue;
			}

			if($this->isRunAlive($run)){
				continue;
			}

			$recorded_exit_code = $this->readSupervisorExitCode((string)$run["cron_log_id"]);
			if($recorded_exit_code !== NULL){
				$this->recordSupervisorExit((string)$run["cron_log_id"], $recorded_exit_code);
				continue;
			}

			$launched = strtotime((string)($run["launched_at"] ?? "")) ?: 0;
			// A stored supervisor PID that is already dead is conclusive. The short
			// grace only protects the launch/PID-publication race when no PID exists.
			if(empty($run["supervisor_pid"])
				&& $launched
				&& $launched > $now - RuntimePolicy::STARTUP_GRACE_SECONDS){
				continue;
			}

			$supervisor_output = $this->readSupervisorOutput((string)$run["cron_log_id"]);
			$started = !empty($run["started_at"]);
			$this->finishRun(
				$run,
				RuntimePolicy::STATUS_FAILED,
				RuntimePolicy::appendOutput(
					$supervisor_output,
					"The worker process exited without recording a terminal status."
				),
				$started ? "UnexpectedWorkerExit" : "WorkerBootstrapFailure",
				$started
					? "Worker process is no longer present."
					: "The worker exited before it could claim the queued run.",
				1
			);
			$this->removeSupervisorArtifacts((string)$run["cron_log_id"]);
		}
	}

	private function finishRun(
		array $run,
		string $status,
		?string $output = NULL,
		?string $error_type = NULL,
		?string $error_message = NULL,
		?int $exit_code = NULL,
		?float $started_at = NULL
	): void
	{
		$current = $this->getRun((string)$run["cron_log_id"]);
		if(!$current || RuntimePolicy::isTerminal($current["status"] ?? NULL)){
			return;
		}

		$started_timestamp = $started_at
			?: (strtotime((string)($current["started_at"] ?? $current["launched_at"] ?? $current["created"] ?? "")) ?: microtime(true));
		$duration = max(0, microtime(true) - $started_timestamp);
		$finished = date("Y-m-d H:i:s");
		$output = RuntimePolicy::appendOutput($current["output"] ?? NULL, $output);

		$worker_pid = (int)($current["worker_pid"] ?? 0);
		$peak_memory = $worker_pid > 0 && $worker_pid === getmypid()
			? memory_get_peak_usage(true)
			: ($current["peak_memory_bytes"] ?? NULL);

		$result_summary = $this->getRunOutputSummary((string)$current["cron_log_id"]);
		$finish = $this->sql->update([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"id" => $current["cron_log_id"],
			"where" => [["status", "IN", RuntimePolicy::ACTIVE_STATUSES]],
			"set" => [
				"status" => $status,
				"finished_at" => $finished,
				"duration" => $duration,
				"exit_code" => $exit_code,
				"error_type" => $error_type,
				"error_message" => $error_message,
				"peak_memory_bytes" => $peak_memory,
				"output" => $output,
				"result_status" => $result_summary["status"],
				"reported_successes" => $result_summary[RuntimePolicy::RESULT_SUCCESS],
				"reported_warnings" => $result_summary[RuntimePolicy::RESULT_WARNING],
				"reported_failures" => $result_summary[RuntimePolicy::RESULT_FAILURE],
				"reported_metrics" => $result_summary["metrics"],
			],
			"user_id" => NULL,
		]);
		if((int)($finish["affected_rows"] ?? 0) !== 1){
			return;
		}

		$this->sql->update([
			"db" => RuntimePolicy::DB,
			"table" => "cron_job",
			"id" => $current["cron_job_id"],
			"set" => [
				"pid" => NULL,
				"last_run" => $finished,
				"last_finished" => $finished,
				"last_status" => $status,
				"last_duration" => $duration,
			],
			"user_id" => NULL,
		]);
	}

	private function recordWorkerShutdown(string $cron_log_id, ?array $error, float $started_at, ?string $captured_output = NULL): void
	{
		try {
			$run = $this->getRun($cron_log_id);
			if(!$run || RuntimePolicy::isTerminal($run["status"] ?? NULL)){
				return;
			}

			if(($run["status"] ?? NULL) === RuntimePolicy::STATUS_CANCELLING){
				$this->finishRun($run, RuntimePolicy::STATUS_CANCELLED, "The worker stopped after cancellation was requested.", NULL, NULL, 143, $started_at);
				return;
			}

			$deadline = strtotime((string)($run["deadline_at"] ?? "")) ?: 0;
			if($deadline && $deadline <= time()){
				$this->finishRun(
					$run,
					RuntimePolicy::STATUS_TIMED_OUT,
					"The worker was terminated at its hard runtime deadline.",
					"CronTimeout",
					"Hard runtime limit exceeded.",
					124,
					$started_at
				);
				return;
			}

			$is_fatal = $error && in_array($error["type"] ?? NULL, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
			if(!$is_fatal){
				$message = "The cron worker shut down before recording a result.";
				$this->finishRun(
					$run,
					RuntimePolicy::STATUS_FAILED,
					RuntimePolicy::appendOutput($captured_output, $message),
					"UnexpectedWorkerShutdown",
					$message,
					1,
					$started_at
				);
				return;
			}

			$message = trim(($error["message"] ?? "Fatal PHP error")
				. (isset($error["file"], $error["line"]) ? " in {$error['file']}:{$error['line']}" : ""));
			$this->finishRun(
				$run,
				RuntimePolicy::STATUS_FAILED,
				RuntimePolicy::appendOutput($captured_output, $message),
				"PhpFatalError",
				$message,
				255,
				$started_at
			);
		}
		catch(\Throwable $ignored) {
			error_log("Unable to record fatal cron worker failure for {$cron_log_id}: " . $ignored->getMessage());
		}
	}

	private function recordConfigurationFailure(array $job, \Throwable $throwable): void
	{
		$dispatch_key = "configuration:" . $job["cron_job_id"] . ":" . sha1((string)$job["interval"]);
		$run_id = $this->insertRun($job, [
			"dispatch_key" => $dispatch_key,
			"trigger_type" => "scheduled",
			"status" => RuntimePolicy::STATUS_FAILED,
			"scheduled_for" => date("Y-m-d H:i:00"),
			"finished_at" => "NOW()",
			"duration" => 0,
			"exit_code" => 1,
			"error_type" => get_class($throwable),
			"error_message" => $throwable->getMessage(),
			"output" => "Invalid cron schedule: " . $throwable->getMessage(),
		]);

		if($run_id){
			$this->sql->update([
				"db" => RuntimePolicy::DB,
				"table" => "cron_job",
				"id" => $job["cron_job_id"],
				"set" => ["last_status" => "configuration_error"],
				"user_id" => NULL,
			]);
		}
	}

	private function getJob(string $cron_job_id): ?array
	{
		$job = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_job",
			"id" => $cron_job_id,
		]);
		return is_array($job) && $job ? $job : NULL;
	}

	private function getRun(string $cron_log_id): ?array
	{
		$run = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_log",
			"id" => $cron_log_id,
		]);
		return is_array($run) && $run ? $run : NULL;
	}

	private function getActiveRunForJob(string $cron_job_id): ?array
	{
		$runs = $this->getActiveRuns($cron_job_id);
		return $runs ? reset($runs) : NULL;
	}

	private function inFlightRunCount(): int
	{
		$count = 0;
		foreach($this->getActiveRuns() as $run){
			if(($run["status"] ?? NULL) !== RuntimePolicy::STATUS_QUEUED || !empty($run["launched_at"])){
				$count++;
			}
		}
		return $count;
	}

	private function maxConcurrentRuns(): int
	{
		$max = (int)($_ENV["cron_max_concurrent_runs"] ?? self::DEFAULT_MAX_CONCURRENT_RUNS);
		return max(1, min(self::MAX_CONCURRENT_RUNS_LIMIT, $max));
	}

	private function timeoutEnforcementSeconds(int $timeout): int
	{
		return max(1, $timeout - self::TIMEOUT_TERMINATION_GRACE_SECONDS);
	}

	private function acquireNamedLock(string $name): bool
	{
		$name = preg_replace("/[^a-zA-Z0-9:_-]/", "", $name);
		$rows = $this->sql->select("SELECT GET_LOCK('{$name}', 0) AS `acquired`");
		return (int)($rows[0]["acquired"] ?? 0) === 1;
	}

	private function releaseNamedLock(string $name): void
	{
		$name = preg_replace("/[^a-zA-Z0-9:_-]/", "", $name);
		try {
			$this->sql->select("SELECT RELEASE_LOCK('{$name}') AS `released`");
		}
		catch(\Throwable $ignored) {
		}
	}

	private function supervisorArtifactPath(string $cron_log_id, string $extension): string
	{
		if(!preg_match("/^[a-f0-9-]{36}$/i", $cron_log_id)){
			throw new \InvalidArgumentException("A valid cron run ID is required for supervisor artifacts.");
		}
		if(!in_array($extension, ["log", "exit"], true)){
			throw new \InvalidArgumentException("Unsupported cron supervisor artifact type.");
		}

		$directory = rtrim((string)($_ENV["cron_runtime_dir"] ?? "/var/www/tmp/cron-runs"), "/\\");
		if($directory === ""){
			throw new \RuntimeException("The cron runtime directory is not configured.");
		}
		if(!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)){
			throw new \RuntimeException("The cron runtime directory {$directory} could not be created.");
		}
		if(!is_writable($directory)){
			throw new \RuntimeException("The cron runtime directory {$directory} is not writable.");
		}

		return $directory . DIRECTORY_SEPARATOR . $cron_log_id . "." . $extension;
	}

	private function readSupervisorExitCode(string $cron_log_id): ?int
	{
		try {
			$path = $this->supervisorArtifactPath($cron_log_id, "exit");
		}
		catch(\Throwable $ignored) {
			return NULL;
		}
		if(!is_file($path)){
			return NULL;
		}

		$value = trim((string)@file_get_contents($path));
		return preg_match("/^\\d{1,3}$/", $value) ? max(0, min(255, (int)$value)) : NULL;
	}

	private function readSupervisorOutput(string $cron_log_id): ?string
	{
		try {
			$path = $this->supervisorArtifactPath($cron_log_id, "log");
		}
		catch(\Throwable $ignored) {
			return NULL;
		}
		if(!is_file($path)){
			return NULL;
		}

		$maximum = RuntimePolicy::MAX_CAPTURED_OUTPUT_BYTES;
		$output = @file_get_contents($path, false, NULL, 0, $maximum + 1);
		if($output === false){
			return NULL;
		}
		if(strlen($output) > $maximum){
			$output = substr($output, 0, $maximum);
			$output = RuntimePolicy::appendOutput($output, "[Supervisor output exceeded the capture limit and was truncated.]");
		}

		return trim($output) ?: NULL;
	}

	private function removeSupervisorArtifacts(string $cron_log_id): void
	{
		foreach(["log", "exit"] as $extension){
			try {
				$path = $this->supervisorArtifactPath($cron_log_id, $extension);
				if(is_file($path)){
					@unlink($path);
				}
			}
			catch(\Throwable $ignored) {
			}
		}
	}

	private function isRunAlive(array $run): bool
	{
		if(!$this->isLocalRun($run)){
			return true;
		}

		$supervisor_pid = (int)($run["supervisor_pid"] ?? 0);
		$worker_pid = (int)($run["worker_pid"] ?? 0);
		$process_group_id = (int)($run["process_group_id"] ?? 0);

		if($this->pidBelongsToRun($supervisor_pid, $run) || $this->pidBelongsToRun($worker_pid, $run)){
			return true;
		}

		return $process_group_id > 0
			&& function_exists("posix_kill")
			&& @posix_kill(-$process_group_id, 0);
	}

	private function pidExists(int $pid): bool
	{
		if($pid < 1){
			return false;
		}
		if(function_exists("posix_kill")){
			return @posix_kill($pid, 0);
		}
		return trim((string)@shell_exec("ps -p {$pid} -o pid=")) !== "";
	}

	private function signalRun(array $run, int $signal): bool
	{
		if(!$this->isLocalRun($run)){
			return false;
		}

		$supervisor_pid = (int)($run["supervisor_pid"] ?? 0);
		$worker_pid = (int)($run["worker_pid"] ?? 0);
		$process_group_id = (int)($run["process_group_id"] ?? 0);
		$sent = false;

		if(function_exists("posix_kill")){
			if($process_group_id > 0){
				$sent = @posix_kill(-$process_group_id, $signal) || $sent;
			}
			if($this->pidBelongsToRun($supervisor_pid, $run)){
				$sent = @posix_kill($supervisor_pid, $signal) || $sent;
			}
			if($process_group_id < 1 && $this->pidBelongsToRun($worker_pid, $run)){
				$sent = @posix_kill($worker_pid, $signal) || $sent;
			}
			return $sent;
		}

		if($process_group_id > 0){
			exec("kill -{$signal} -- -{$process_group_id} 2>/dev/null", $unused, $code);
			$sent = $code === 0;
		}
		if(!$sent && $this->pidBelongsToRun($supervisor_pid, $run)){
			exec("kill -{$signal} {$supervisor_pid} 2>/dev/null", $unused, $code);
			$sent = $code === 0;
		}
		if(!$sent && $process_group_id < 1 && $this->pidBelongsToRun($worker_pid, $run)){
			exec("kill -{$signal} {$worker_pid} 2>/dev/null", $unused, $code);
			$sent = $code === 0;
		}
		return $sent;
	}

	private function pidBelongsToRun(int $pid, array $run): bool
	{
		if($pid < 1 || !$this->pidExists($pid)){
			return false;
		}

		$command_line = @file_get_contents("/proc/{$pid}/cmdline");
		return $command_line !== false
			&& str_contains($command_line, (string)$run["cron_log_id"]);
	}

	private function isLocalRun(array $run): bool
	{
		$run_hostname = trim((string)($run["hostname"] ?? ""));
		$current_hostname = trim((string)(gethostname() ?: ""));
		return $run_hostname === "" || $current_hostname === "" || strcasecmp($run_hostname, $current_hostname) === 0;
	}

	private function closeWorkerOutputBuffers(int $base_buffer_level): void
	{
		while(ob_get_level() > $base_buffer_level){
			@ob_end_flush();
		}
	}

	private function normaliseResult($result): ?string
	{
		if($result === NULL || $result === true){
			return NULL;
		}
		if($result === false){
			return "The cron method returned false.";
		}
		if(is_scalar($result)){
			return (string)$result;
		}

		$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return $json === false ? get_debug_type($result) : $json;
	}

	private function normaliseRows($rows): array
	{
		if(!$rows || !is_array($rows)){
			return [];
		}
		return array_is_list($rows) ? $rows : [$rows];
	}

	private function getRunOutputSummary(string $cron_log_id): array
	{
		$summary = [
			RuntimePolicy::RESULT_INFO => 0,
			RuntimePolicy::RESULT_SUCCESS => 0,
			RuntimePolicy::RESULT_WARNING => 0,
			RuntimePolicy::RESULT_FAILURE => 0,
			"metrics" => 0,
		];
		$groups = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"columns" => ["status", "Outputs" => ["count", "cron_run_output_id"]],
			"table" => "cron_run_output",
			"where" => ["cron_log_id" => $cron_log_id],
			"group_by" => "status",
		]));
		foreach($groups as $group){
			if(array_key_exists((string)$group["status"], $summary)){
				$summary[(string)$group["status"]] = (int)($group["Outputs"] ?? 0);
			}
		}
		$summary["metrics"] = (int)$this->sql->select([
			"db" => RuntimePolicy::DB,
			"count" => true,
			"table" => "cron_run_output",
			"where" => [
				"cron_log_id" => $cron_log_id,
				"output_type" => "metric",
			],
		]);
		$summary["status"] = RuntimePolicy::resultStatus($summary);
		return $summary;
	}

	/** Permanently remove terminal run history in small per-minute batches. */
	private function pruneHistoryIfDue(): void
	{
		$retention_days = RuntimePolicy::retentionDays($_ENV["cron_retention_days"] ?? NULL);
		$state = $this->sql->select([
			"db" => RuntimePolicy::DB,
			"table" => "cron_scheduler_state",
			"id" => self::SCHEDULER_STATE_ID,
		]);
		if($retention_days === 0){
			$this->updateSchedulerState(["retention_days" => 0, "last_pruned_rows" => 0]);
			return;
		}

		$last_pruned = strtotime((string)($state["last_pruned_at"] ?? "")) ?: 0;
		if((int)($state["retention_days"] ?? 0) === $retention_days && $last_pruned > time() - 86400){
			return;
		}

		$runs = $this->normaliseRows($this->sql->select([
			"db" => RuntimePolicy::DB,
			"columns" => ["cron_log_id"],
			"table" => "cron_log",
			"include_removed" => true,
			"where" => [
				["created", "<", date("Y-m-d H:i:s", strtotime("-{$retention_days} days"))],
				["status", "IN", RuntimePolicy::TERMINAL_STATUSES],
			],
			"order_by" => ["created" => "ASC"],
			"start" => 0,
			"length" => RuntimePolicy::RETENTION_BATCH_SIZE,
		]));
		$ids = array_values(array_filter(array_column($runs, "cron_log_id")));
		if($ids){
			$this->sql->delete([
				"db" => RuntimePolicy::DB,
				"table" => "cron_run_output",
				"where" => [["cron_log_id", "IN", $ids]],
				"user_id" => NULL,
			]);
			$this->sql->delete([
				"db" => RuntimePolicy::DB,
				"table" => "cron_log",
				"where" => [["cron_log_id", "IN", $ids]],
				"user_id" => NULL,
			]);
		}

		$state_update = [
			"retention_days" => $retention_days,
			"last_pruned_rows" => count($ids),
		];
		if(count($ids) < RuntimePolicy::RETENTION_BATCH_SIZE){
			$state_update["last_pruned_at"] = date("Y-m-d H:i:s");
		}
		$this->updateSchedulerState($state_update);
	}

	private function setSystemUser(): void
	{
		global $user_id;
		$user_id = "0";
		$_SESSION["user_id"] = $user_id;
	}

	private function updateSchedulerState(array $set): void
	{
		$this->sql->update([
			"db" => RuntimePolicy::DB,
			"table" => "cron_scheduler_state",
			"id" => self::SCHEDULER_STATE_ID,
			"set" => $set,
			"user_id" => NULL,
		]);
	}
}
