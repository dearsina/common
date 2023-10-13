<?php


namespace App\Common\ErrorLog;


use App\Common\SQL\Factory;

/**
 * Class Field
 * @package App\Common\ErrorLog
 */
class Field {
	/**
	 * @param null $a
	 *
	 * @return array[]
	 */
	public static function existingIssues($a = NULL){
		if(is_array($a))
			extract($a);

		$sql = Factory::getInstance();

		if($issues = $sql->select([
			"table" => "issue_tracker",
			"where" => [
				["progress", "<>", 1]
			]
		])){
			foreach($issues as $issue){
				$issue_tracker_options[$issue['issue_tracker_id']] = $issue['title'];
			}
		}

		return [[
			"type" => "hidden",
			"name" => "button_id",
			"value" => $button_id
			/**
			 * This is the ID of the action buttons
			 * for this given line in the error list.
			 * The ID is used to refresh only this line,
			 * once the update has been completed.
			 */
		],[
			"type" => "select",
			"name" => "issue_tracker_id",
			"label" => "Existing issue",
			"value" => $issue_tracker_id,
			"options" => $issue_tracker_options,
			"required" => true,
		]];
	}


}