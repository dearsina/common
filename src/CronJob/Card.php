<?php


namespace App\Common\CronJob;


use App\UI\Icon;

class Card extends \App\Common\Common {
	/**
	 * @param array $a
	 *
	 * @return string
	 */
	public function all(array $a){
		extract($a);

		$card = new \App\UI\Card([
			"icon" => Icon::get("cron_job"),
			"header" => "All cron jobs",
			"body" => [
				"id" => "all_cron_job",
			],
			"footer" => [
				"button" => [[
					"hash" => [
						"rel_table" => $rel_table,
						"action" => "new"
					],
					"title" => "New",
					"icon" => Icon::get("new"),
					"colour" => "primary",
				]]
			]
		]);

		return $card->getHTML();
	}

	public function running(array $a){
		$card = new \App\UI\Card([
			"icon" => "play",
			"header" => "Currently running jobs",
			"body" => [
				"id" => "currently_running_cron_jobs",
				"style" => [
					"min-height" => "5rem"
				]
			],
		]);

		return $card->getHTML();
	}
}