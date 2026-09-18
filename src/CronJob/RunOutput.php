<?php

namespace App\Common\CronJob;

use App\Common\SQL\Factory;

/**
 * Structured, business-level reporting for the cron run currently executing.
 *
 * Execution success is deliberately independent from these results. A method
 * may finish normally while reporting that one or more items failed.
 */
final class RunOutput {
	private static ?string $cron_log_id = NULL;
	private static ?self $instance = NULL;
	private $sql;

	private function __construct()
	{
		$this->sql = Factory::getInstance();
	}

	public static function begin(string $cron_log_id): void
	{
		self::$cron_log_id = $cron_log_id;
	}

	public static function end(): void
	{
		self::$cron_log_id = NULL;
	}

	public static function isActive(): bool
	{
		return self::$cron_log_id !== NULL;
	}

	public static function info(string $message, array $context = []): string
	{
		return self::message(RuntimePolicy::RESULT_INFO, $message, $context);
	}

	public static function success(string $message, array $context = []): string
	{
		return self::message(RuntimePolicy::RESULT_SUCCESS, $message, $context);
	}

	public static function warning(string $message, array $context = []): string
	{
		return self::message(RuntimePolicy::RESULT_WARNING, $message, $context);
	}

	public static function failure(string $message, array $context = []): string
	{
		return self::message(RuntimePolicy::RESULT_FAILURE, $message, $context);
	}

	public static function message(string $status, string $message, array $context = []): string
	{
		return self::instance()->write([
			"output_type" => "message",
			"status" => self::validateStatus($status),
			"message" => $message,
			"context_json" => self::encodeContext($context),
		]);
	}

	public static function metric(
		string $key,
		int|float $value,
		?string $unit = NULL,
		string $status = RuntimePolicy::RESULT_INFO,
		array $context = []
	): string
	{
		$key = trim($key);
		if($key === ""){
			throw new \InvalidArgumentException("A cron output metric requires a key.");
		}

		return self::instance()->write([
			"output_type" => "metric",
			"status" => self::validateStatus($status),
			"metric_key" => substr($key, 0, 255),
			"metric_value" => $value,
			"metric_unit" => $unit === NULL ? NULL : substr(trim($unit), 0, 64),
			"context_json" => self::encodeContext($context),
		]);
	}

	private function write(array $set): string
	{
		if(!self::$cron_log_id){
			throw new \LogicException("Cron output can only be recorded while a cron worker is running.");
		}

		return (string)$this->sql->insert([
			"db" => RuntimePolicy::DB,
			"table" => "cron_run_output",
			"set" => array_merge(["cron_log_id" => self::$cron_log_id], $set),
			"user_id" => NULL,
		]);
	}

	private static function instance(): self
	{
		return self::$instance ??= new self();
	}

	private static function validateStatus(string $status): string
	{
		if(!in_array($status, [
			RuntimePolicy::RESULT_INFO,
			RuntimePolicy::RESULT_SUCCESS,
			RuntimePolicy::RESULT_WARNING,
			RuntimePolicy::RESULT_FAILURE,
		], true)){
			throw new \InvalidArgumentException("Invalid cron output status {$status}.");
		}
		return $status;
	}

	private static function encodeContext(array $context): ?string
	{
		if(!$context){
			return NULL;
		}
		$json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		return $json === "[]" ? NULL : $json;
	}
}
