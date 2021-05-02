<?php


namespace App\Common\CronJob;


use App\Common\str;

/**
 * Class Field
 * @package App\Common\CronJob
 */
class Field {


	public static function cronJob(?array $a = NULL) : array
	{
		if (is_array($a))
			extract($a);

		# The common path
		$common_path = __DIR__."/../../";

		# The core path
		$core_path = $_SERVER['DOCUMENT_ROOT']."/../app/";

		# The API path
		$api_path = $_SERVER['DOCUMENT_ROOT']."/../api/";

		# Get classes from both
		$classes = array_merge(
			str::getClassesFromPath($common_path)?:[],
			str::getClassesFromPath($core_path)?:[],
			str::getClassesFromPath($api_path)?:[],
		);

		# Sort them alphabetically
		sort($classes);

		# Load them in an options array
		foreach($classes as $m){
			$class_options[$m] = $m;
		}

		# If the class has already been identified, load up the methods
		if($class){
			foreach(str::getMethodsFromClass($class) as $m){
				$method_options[$m['name']] = $m['name'];
			}
		}

		$title_fields = [
			[ //Row
				"name" => "title",
				"title" => "Cron job title",
				"required" => true,
				"value" => $title,
				"desc" => "Give the cron job a descriptive name.",
			],[
				"type" => "textarea",
				"name" => "desc",
				"title" => "Cron job description",
				"value" => $desc,
				"rows" => 7,
				"desc" => "Describe what the cron job does.",
				"required" => true
			]
		];

		$method_fields = [
			[
				"type" => "select",
				"required" => true,
				"options" => $class_options,
				"name" => "class",
				"value" => $class,
				"title" => "Class",
				"desc" => "In which class does your method reside?",
				"parent" => [
					"child" => "#class-method",
					"child_placeholder" => "Select a class first.",
					"ajax" => [
						"rel_table" => "cron_job",
						"action" => "get_class_methods"
					]
				]
			],[
				"id" => "class-method",
				"type" => "select",
				"required" => true,
				"name" => "method",
				"options" => $method_options,
				"value" => $method,
				"title" => "Method",
				"desc" => "Which method do you want to run?",
			],[
				"type" => "select",
				"required" => true,
				"options" => [
					'@yearly' => 'Yearly',
					'@monthly' => 'Monthly',
					'@weekly' => 'Weekly',
					'@daily' => 'Daily, at midnight GMT',
					'0 2 * * *' => 'Daily, at 2am GMT',
					'0 4 * * *' => 'Daily, at 4am GMT',
					'@hourly' => 'Hourly',
					'* * * * *' => "Every minute"
				],
				"name" => "interval",
				"value" => $interval,
				"title" => "Interval",
				"desc" => "How often do you want the cron job to run?"
			],[[
				"type" => "checkbox",
				"name" => "paused",
				"title" => "Paused",
				"desc" => "When a job is paused, it will not run as scheduled.",
				"value" => 1,
				"checked" => $paused
			],[
				"type" => "checkbox",
				"name" => "silent",
				"title" => "silent",
				"desc" => "Silent jobs will not be logged if they are successful. Useful for high frequency jobs.",
				"value" => 1,
				"checked" => $silent
			]]
		];

		// Wrapper / Switch / Col (if switch is present, otherwise Row) / Row (if switch is present, otherwise Col)
		return [[
			$title_fields,
			$method_fields
		]];
	}
}