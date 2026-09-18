<?php

namespace App\Common\CronJob;

use App\UI\Icon;

class Card extends \App\Common\Prototype {
	public function summary(array $a): string
	{
		$card = new \App\UI\Card\Card([
			"icon" => "tachometer-alt",
			"header" => "Scheduler health",
			"body" => [
				"id" => "cron_job_summary",
				"html" => "<div class=\"text-muted text-center\">Loading scheduler health…</div>",
			],
		]);

		return $card->getHTML();
	}

	public function all(array $a): string
	{
		$card = new \App\UI\Card\Card([
			"icon" => Icon::get("cron_job"),
			"header" => "Scheduled jobs",
			"body" => [
				"id" => "all_cron_job",
				"html" => "<div class=\"text-muted text-center\">Loading jobs…</div>",
			],
			"buttons" => [[
				"hash" => [
					"rel_table" => $a["rel_table"] ?? "cron_job",
					"action" => "new",
				],
				"title" => "New cron job...",
				"icon" => Icon::get("new"),
			]],
		]);

		return $card->getHTML();
	}

	public function running(array $a): string
	{
		$card = new \App\UI\Card\Card([
			"icon" => "play",
			"header" => "Active and queued runs",
			"body" => [
				"id" => "currently_running_cron_jobs",
				"html" => "<div class=\"text-muted text-center\">Loading active runs…</div>",
				"style" => [
					"min-height" => "5rem",
					"max-height" => "50vh",
					"overflow-y" => "auto",
				],
			],
		]);

		return $card->getHTML();
	}
}
