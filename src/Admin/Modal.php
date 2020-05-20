<?php


namespace App\Common\Admin;


use App\UI\Icon;

class Modal extends \App\Common\Common {
	public function error_notification($a){
		extract($a);

		$modal = new \App\UI\Modal([
			"size" => "m",
			"icon" => "cog",
			"header" => "Error notification schedules",
			"body" => [
				"html" => [[
					"html" => "Select how frequently (or if at all) an admin should be notified of errors.",
					"row_style" => [
						"margin-bottom" => "1rem"
					]
				],[
					"html" => [
						"id" => "all_error_notification",
					]
				]]
			],
			"footer" => [
				"button" => "close_md"
			],
			"draggable" => true,
			"resizable" => true,
		]);

		return $modal->getHTML();
	}
}