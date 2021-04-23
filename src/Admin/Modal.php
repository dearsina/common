<?php


namespace App\Common\Admin;


/**
 * Class Modal
 * @package App\Common\Admin
 */
class Modal extends \App\Common\Prototype {
	/**
	 * @param $a
	 *
	 * @return string
	 */
	public function error_notification($a){
		extract($a);

		$modal = new \App\UI\Modal\Modal([
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