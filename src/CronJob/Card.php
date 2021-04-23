<?php


namespace App\Common\CronJob;


use App\UI\Countdown;
use App\UI\Icon;

class Card extends \App\Common\Prototype {
	/**
	 * @param array $a
	 *
	 * @return string
	 */
	public function all(array $a){
		extract($a);

		$countdown = Countdown::generate([
			"modify" => "+10 seconds", //A string modifying the datetime
			"pre" => "Refreshing in ", //Text that goes before the timer
			"post" => ".",
			"callback" => "ajaxCall", //The name of a function to call at zero
			"vars" => [
				"rel_table" => "cron_job",
				"action" => "updateCronJobs"
			],//Variables to send to the callback function,
			"restart" => [
				"seconds" => 10
			]
		]);

		$card = new \App\UI\Card\Card([
			"icon" => Icon::get("cron_job"),
			"header" => "All cron jobs",
			"body" => [
				"id" => "all_cron_job",
			],
			"buttons" => [[
				"hash" => [
					"rel_table" => $rel_table,
					"action" => "new"
				],
				"title" => "New cron job...",
				"icon" => Icon::get("new"),
			]],
			"post" => [
				"class" => "text-center text-muted smaller",
				"html" => $countdown
			]
		]);

		return $card->getHTML();
	}

	public function running(array $a){
		$card = new \App\UI\Card\Card([
			"icon" => "play",
			"header" => "Output from currently running jobs",
			"body" => [
				"id" => "currently_running_cron_jobs",
				"style" => [
					"min-height" => "5rem",
					"max-height" => "50vh",
					"overflow-y" => "auto"
				]
			],
		]);

		return $card->getHTML();
	}
}