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
			$user_options[$admin['user']['user_id']] = $admin['user']['full_name'];
		}

		return [
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
			],[
				"type" => "hidden",
				"name" => "button_id",
				"value" => $button_id
			]
		];
	}
}