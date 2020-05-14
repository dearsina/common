<?php


namespace App\Common\IssueTracker;


use App\Common\ErrorLog\ErrorLog;
use App\Common\SQL\Factory;
use App\Common\SQL\Info\Info;
use App\Common\str;

class Field {
	public static function issueTracker($a = NULL){
		if(is_array($a))
			extract($a);

		$sql = Factory::getInstance();

		$issue_types = $sql->select([
			"table" => "issue_type"
		]);

		foreach($issue_types as $type){
			$issue_type_options[$type['issue_type_id']] = $type['title'];
		}

		$issue_priorities = $sql->select([
			"table" => "issue_priority"
		]);

		foreach($issue_priorities as $priority){
			$issue_priority_options[$priority['issue_priority_id']] = $priority['title'];
		}

		$info = Info::getInstance();

		$admins = $info->getInfo("admin");

		foreach($admins as $admin){
			$user_options[$admin['user_id']] = $admin['full_name'];
		}

		$issue_tracker_fields = [
			[[
				"type" => "select",
				"name" => "issue_type_id",
				"label" => "Type of issue",
				"value" => $issue_type_id,
				"options" => $issue_type_options,
				"required" => true,
			],[
				"type" => "select",
				"name" => "issue_priority_id",
				"label" => "Priority",
				"value" => $issue_priority_id === NULL ? 3 : $issue_priority_id,
				"options" => $issue_priority_options,
				"required" => true,
			]],[
				"type" => "select",
				"name" => "user_id",
				"value" => $user_id,
				"options" => $user_options,
				"label" => "Assigned to",
			],[
				"name" => "title",
				"label" => false,
				"value" => $title === NULL ? $error['title'] : $title,
				"placeholder" => "Issue name",
				"required" => "An issue needs a name.",
			],[
				"type" => "textarea",
				"name" => "desc",
				"label" => false,
				"placeholder" => "Issue description.",
				"required" => "A short narrative describing this issue is very useful.",
				"value" => $desc,
				"rows" => 8
			],[
				"type" => "range",
				"name" => "progress",
				"min" => 0,
				"max" => 1,
				"step" => 0.1,
				"multiple" => 100,
				"suffix" => "%",
				"min_colour" => [255,0,0],
				"max_colour" => [40,167,69],
				"value" => $progress,
			],[
				"type" => "hidden",
				"name" => "error_log_id",
				"value" => $error_log_id
			]
		];

		if($error_log_id){
			$error = $sql->select([
				"table" => "error_log",
				"left_join" => [[
					"table" => "user",
					"on" => [
						"user_id" => "`error_log`.`created_by`"
					]
				]],
				"id" => $error_log_id
			]);

			$row = ErrorLog::rowHandler($error);

			$error_fields['style'] = [
//				"border-left" => ".1px solid black",
				"max-height" => "40vh",
				"overflow-y" => "auto",
				"overflow-x" => "hidden",
				"overflow-wrap" => "anywhere",
			];
			$error_fields['class'] = ["vl"];
			$error_fields['sm'] = 6;

			foreach($row as $key => $val){
				if($key == "Actions"){
					continue;
				}
				$error_fields['html'][] = [
					"class" => "h6",
					"style" => [
						"margin-top" => "1rem"
					],
					"html" => $key
				];
				if($val['accordion']){
					$error_fields['html'][] = $val['accordion']['header'];
					$error_fields['html'][] = $val['accordion']['body'];
					continue;
				}
				$error_fields['html'][] = $val;
			}

			return [[$issue_tracker_fields,$error_fields]];
		}

		return $issue_tracker_fields;
	}
}