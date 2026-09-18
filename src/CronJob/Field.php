<?php


namespace App\Common\CronJob;


use App\Common\str;

/**
 * Class Field
 * @package App\Common\CronJob
 */
class Field {

	public static function cronJob(?array $a = NULL): array
	{
		if (is_array($a))
			extract($a);

		# If the class has already been identified, load up the methods
		if ($class){
			// If we're editing an existing cron job
			foreach (str::getMethodsFromClass($class) as $m){
				$method_options[$m['name']] = $m['name'];
			}

			# Order the methods by title
			asort($method_options);
		}

		$title_fields = [
			[ //Row
				"name" => "title",
				"title" => "Cron job title",
				"required" => true,
				"value" => $title,
				"desc" => "Give the cron job a descriptive name.",
			], [
				"type" => "textarea",
				"name" => "desc",
				"title" => "Cron job description",
				"value" => $desc,
				"rows" => 7,
				"desc" => "Describe what the cron job does.",
				"required" => true,
			],
		];

		$method_fields = [
			[
				"type" => "select",
				"required" => true,
				"options" => [$class],
				"name" => "class",
				"value" => $class,
				"title" => "Class",
				"desc" => "In which class does your method reside?",
				"parent" => [
					"child" => "#class-method",
					"child_placeholder" => "Select a class first.",
					"ajax" => [
						"rel_table" => "cron_job",
						"action" => "get_class_methods",
					],
				],
				"ajax" => [
					"rel_table" => "cron_job",
					"action" => "get_class_options",
				],
			], [
				"id" => "class-method",
				"type" => "select",
				"required" => true,
				"name" => "method",
				"options" => $method_options,
				"value" => $method,
				"title" => "Method",
				"desc" => "Which method do you want to run?",
			], [
				"type" => "select",
				"required" => true,
				"options" => CronJob::INTERVALS,
				"name" => "interval",
				"value" => $interval,
				"title" => "Interval",
				"desc" => "How often do you want the cron job to run?",
			], [
				"type" => "number",
				"required" => true,
				"name" => "timeout_seconds",
				"value" => $timeout_seconds ?: RuntimePolicy::DEFAULT_TIMEOUT_SECONDS,
				"title" => "Hard runtime limit (seconds)",
				"desc" => "The worker and its child processes are terminated after this wall-clock limit. Minimum 60 seconds; absolute maximum 3600 seconds.",
				"min" => RuntimePolicy::MIN_TIMEOUT_SECONDS,
				"max" => RuntimePolicy::MAX_TIMEOUT_SECONDS,
				"step" => 60,
			], [[
				"type" => "checkbox",
				"name" => "paused",
				"title" => "Paused",
				"desc" => "When a job is paused, it will not run as scheduled.",
				"value" => 1,
				"checked" => $paused,
			], [
				"type" => "checkbox",
				"name" => "silent",
				"title" => "Silent",
				"desc" => "Keep routine notifications quiet. Every execution is still retained in the run ledger.",
				"value" => 1,
				"checked" => $silent,
			]],
		];

		// Wrapper / Switch / Col (if switch is present, otherwise Row) / Row (if switch is present, otherwise Col)
		return [[
			$title_fields,
			$method_fields,
		]];
	}
}
