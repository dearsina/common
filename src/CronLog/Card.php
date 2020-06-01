<?php


namespace App\Common\CronLog;


use App\Common\str;
use App\UI\Countdown;
use App\UI\Icon;
use App\UI\Table;

class Card extends \App\Common\Common {
	public function cronLog($a){
		extract($a);

		$id = str::id("on_demand_table");

		$body = Table::onDemand([
			"id" => $id,
			"hash" => [
				"rel_table" => $rel_table,
				"action" => "get_cron_log",
				"vars" => $vars
			],
			"length" => 10
		]);

		$countdown = Countdown::generate([
			"modify" => "+1 minutes", //A string modifying the datetime
			"pre" => "Refreshing in ", //Text that goes before the timer
			"post" => ".",
			"callback" => "onDemandReset", //The name of a function to call at zero
			"vars" => $id, //Variables to send to the callback function,
			"restart" => [
				"minutes" => 1
			]
		]);

		if($vars){
			$button[] = [
				"alt" => "Remove all these job logs...",
				"basic" => true,
				"icon" => Icon::get("trash"),
				"size" => "small",
				"colour" => "danger",
				"hash" => [
					"rel_table" => $rel_table,
					"action" => "remove_all",
					"vars" => $vars
				],
				"approve" => [
					"colour" => "red",
					"title" => "Batch remove job logs?",
					"message" => "Do you want to remove all the job logs in this list? This will include jobs that perhaps have yet to be loaded below."
				],
				"class" => "float-right"
			];
		}

		$card = new \App\UI\Card([
			"header" => [
				"icon" => $icon,
				"title" => "Cron jobs",
				"button" => $button
			],
			"body" => $body,
			"footer" => true,
			"post" => [
				"class" => "text-center text-muted smaller",
				"html" => $countdown
			]
		]);

		return $card->getHTML();
	}

	public function cronLogByStatus($a){
		extract($a);

		$results = $this->sql->select([
			"columns" => [
				"status",
				"Jobs" => "count(cron_log_id)",
			],
			"table" => "cron_log",
			"where" => $vars,
			"order_by" => [
				"Jobs" => "DESC"
			]
		]);

		if($results) {
			foreach ($results as $row) {
				$hash = [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($a['vars']?:[], [
						"status" => urlencode($row['status'])
					])
				];
				$rows[] = [
					"Status" => [
						"icon" => Icon::DEFAULTS[$row['status']],
						"hash" => $hash,
						"html" => str::title($row['status'])
					],
					"Jobs" => [
						"hash" => $hash,
						"html" => $row['Jobs']
					]
				];
			}
		}

		return $this->cronLogBreakdown($a, ["status"], $rows, "Cron jobs by status", "traffic-light");
	}

	public function cronLogByJob($a){
		extract($a);

		$results = $this->sql->select([
			"columns" => [
				"cron_job_id",
				"Jobs" => "count(cron_log_id)",
			],
			"join" => [[
				"columns" => "title",
				"table" => "cron_job",
				"on" => "cron_job_id"
			]],
			"table" => "cron_log",
			"where" => $vars,
			"order_by" => [
				"Jobs" => "DESC"
			]
		]);

		if($results) {
			foreach ($results as $row) {
				$hash = [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($a['vars']?:[], [
						"cron_job_id" => urlencode($row['cron_job_id'])
					])
				];
				$rows[] = [
					"Job" => [
						"hash" => $hash,
						"html" => $row['cron_job'][0]['title']
					],
					"Jobs" => [
						"hash" => $hash,
						"html" => $row['Jobs']
					]
				];
			}
		}

		return $this->cronLogBreakdown($a, ["cron_job_id"], $rows, "Cron jobs by job", Icon::get("cron_job"));
	}

	/**
	 * The actual method that builds the errorsBy...() cards.
	 *
	 * @param array      $a
	 * @param            $var
	 * @param array|null $cron_jobs
	 * @param string     $title
	 * @param string     $icon
	 *
	 * @return string
	 */
	private function cronLogBreakdown(array $a, $var, ?array $cron_jobs, string $title, string $icon){
		extract($a);

		if(!is_array($vars)){
			$vars = [];
		}

		if($cron_jobs){
			$body = Table::generate($cron_jobs);
		} else {
			$body = "<div class=\"text-silent align-text-center\">No cron jobs logged</div>";
		}

		foreach(is_array($var) ? $var : [$var] as $v){
			$var_array[$v] = NULL;
		}

		if(!empty(array_intersect(array_keys($vars), array_keys($var_array)))){
			$button = [
				"title" => "Clear",
				"size" => "xs",
				"basic" => true,
				"hash" => [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($vars,$var_array)
				]
			];
		}

		$card = new \App\UI\Card([
			"header" => [
				"icon" => $icon,
				"title" => $title,
				"button" => $button
			],
			"body" => $body
		]);

		return $card->getHTML();
	}
}