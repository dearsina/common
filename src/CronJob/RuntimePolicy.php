<?php

namespace App\Common\CronJob;

/**
 * Pure policy helpers shared by the scheduler, workers, UI, and tests.
 */
final class RuntimePolicy {
	public const DB = "cron";
	public const DEFAULT_TIMEOUT_SECONDS = 900;
	public const MIN_TIMEOUT_SECONDS = 60;
	public const MAX_TIMEOUT_SECONDS = 3600;
	public const QUEUE_MAX_AGE_SECONDS = 3600;
	/**
	 * Only covers the small launch-to-PID-publication race. Once a supervisor
	 * PID has been stored, a dead process can be failed immediately.
	 */
	public const STARTUP_GRACE_SECONDS = 10;
	public const MAX_CAPTURED_OUTPUT_BYTES = 1048576;
	public const DEFAULT_RETENTION_DAYS = 30;
	public const MAX_RETENTION_DAYS = 3650;
	public const RETENTION_BATCH_SIZE = 2000;

	public const RESULT_NONE = "no_output";
	public const RESULT_INFO = "info";
	public const RESULT_SUCCESS = "success";
	public const RESULT_WARNING = "warning";
	public const RESULT_FAILURE = "failure";

	public const STATUS_QUEUED = "queued";
	public const STATUS_RUNNING = "running";
	public const STATUS_CANCELLING = "cancelling";
	public const STATUS_SUCCESS = "success";
	public const STATUS_WARNING = "warning";
	public const STATUS_FAILED = "failed";
	public const STATUS_TIMED_OUT = "timed_out";
	public const STATUS_CANCELLED = "cancelled";
	public const STATUS_SKIPPED_OVERLAP = "skipped_overlap";
	public const STATUS_MISSED = "missed";

	public const ACTIVE_STATUSES = [
		self::STATUS_QUEUED,
		self::STATUS_RUNNING,
		self::STATUS_CANCELLING,
	];

	public const TERMINAL_STATUSES = [
		self::STATUS_SUCCESS,
		self::STATUS_WARNING,
		self::STATUS_FAILED,
		self::STATUS_TIMED_OUT,
		self::STATUS_CANCELLED,
		self::STATUS_SKIPPED_OVERLAP,
		self::STATUS_MISSED,
	];

	public static function timeout($seconds): int
	{
		$seconds = is_numeric($seconds) ? (int)$seconds : self::DEFAULT_TIMEOUT_SECONDS;
		return max(self::MIN_TIMEOUT_SECONDS, min(self::MAX_TIMEOUT_SECONDS, $seconds));
	}

	public static function isActive(?string $status): bool
	{
		return in_array($status, self::ACTIVE_STATUSES, true);
	}

	public static function isTerminal(?string $status): bool
	{
		return in_array($status, self::TERMINAL_STATUSES, true);
	}

	public static function executionStatusForResult($result): string
	{
		return $result === false ? self::STATUS_FAILED : self::STATUS_SUCCESS;
	}

	public static function retentionDays($days): int
	{
		if($days === NULL || $days === ""){
			return self::DEFAULT_RETENTION_DAYS;
		}
		$days = is_numeric($days) ? (int)$days : self::DEFAULT_RETENTION_DAYS;
		return $days === 0 ? 0 : max(1, min(self::MAX_RETENTION_DAYS, $days));
	}

	public static function resultStatus(array $counts): string
	{
		if((int)($counts[self::RESULT_FAILURE] ?? 0) > 0){
			return self::RESULT_FAILURE;
		}
		if((int)($counts[self::RESULT_WARNING] ?? 0) > 0){
			return self::RESULT_WARNING;
		}
		if((int)($counts[self::RESULT_SUCCESS] ?? 0) > 0){
			return self::RESULT_SUCCESS;
		}
		if((int)($counts[self::RESULT_INFO] ?? 0) > 0){
			return self::RESULT_INFO;
		}
		return self::RESULT_NONE;
	}

	public static function scheduledDispatchKey(string $cron_job_id, string $scheduled_for): string
	{
		return "scheduled:{$cron_job_id}:" . date("YmdHi", strtotime($scheduled_for));
	}

	public static function manualDispatchKey(string $cron_job_id, string $nonce): string
	{
		return "manual:{$cron_job_id}:{$nonce}";
	}

	public static function appendOutput(?string $existing, ?string $addition): ?string
	{
		$output = trim(implode("\n", array_filter([
			trim((string)$existing),
			trim((string)$addition),
		], static fn($value) => $value !== "")));

		if($output === ""){
			return NULL;
		}

		if(strlen($output) <= self::MAX_CAPTURED_OUTPUT_BYTES){
			return $output;
		}

		$notice = "\n\n[Output truncated at " . self::MAX_CAPTURED_OUTPUT_BYTES . " bytes]";
		return substr($output, 0, self::MAX_CAPTURED_OUTPUT_BYTES - strlen($notice)) . $notice;
	}

	public static function formatDuration($seconds): string
	{
		if($seconds === NULL || !is_numeric($seconds)){
			return "—";
		}

		$seconds = max(0, (float)$seconds);
		if($seconds < 1){
			return round($seconds * 1000) . " ms";
		}
		if($seconds < 60){
			return number_format($seconds, $seconds < 10 ? 2 : 1) . " s";
		}
		if($seconds < 3600){
			return floor($seconds / 60) . "m " . round(fmod($seconds, 60)) . "s";
		}

		return floor($seconds / 3600) . "h " . floor(fmod($seconds, 3600) / 60) . "m";
	}

	public static function formatBytes($bytes): string
	{
		if($bytes === NULL || !is_numeric($bytes)){
			return "—";
		}

		$bytes = max(0, (float)$bytes);
		$units = ["B", "KB", "MB", "GB", "TB"];
		$unit = 0;
		while($bytes >= 1024 && $unit < count($units) - 1){
			$bytes /= 1024;
			$unit++;
		}

		return number_format($bytes, $unit > 1 ? 1 : 0) . " " . $units[$unit];
	}
}
