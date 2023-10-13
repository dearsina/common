<?php


namespace App\Common\CronLog;


use App\Common\str;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Table;

/**
 * Class CronLog
 * @package App\Common\CronLog
 */
class CronLog extends \App\Common\Prototype {
	/**
	 * @param bool|null $output_to_email
	 *
	 * @return Card
	 */
	public function card(?bool $output_to_email = NULL)
	{
		if($output_to_email){
			//			return new EmailCard();
		}
		return new Card();
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function all($a)
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => "Cron job alert",
			"icon" => Icon::get("log")
		]);

		# UrlDEcode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				$a['vars'][$key] = urldecode($val);
			}
		}

		$page->setGrid([[
			"html" => $this->card()->cronLogByStatus($a)
		], [
			"html" => $this->card()->cronLogByJob($a)
			//		],[
			//			"html" => $this->card()->errorsByReltable($a)
		]]);

		$page->setGrid([
			"html" => $this->card()->cronLog($a)
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function removeAll($a)
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		if($vars){
			foreach($vars as $key => $val){
				if($val == "NULL"){
					$vars[$key] = NULL;
					continue;
				}
				$vars[$key] = urldecode($val);
			}
		}

		$removed_count = $this->sql->select([
			"count" => true,
			"table" => $rel_table,
			"where" => $vars
		]);

		$this->sql->remove([
			"table" => $rel_table,
			"where" => $vars
		]);

		$this->log->success([
			"icon" => Icon::get("trash"),
			"title" => str::pluralise_if($removed_count, "alert", true) . " removed",
			"message" => str::were($removed_count, "alert", true) . " removed successfully."
		]);

		$this->hash->set([
			"rel_table" => $rel_table,
			"action" => "all"
		]);

		return true;
	}

	/**
	 * Removes a cron job.
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function remove(array $a, ?bool $silent = NULL): bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->remove([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		if($silent){
			return true;
		}

		$this->hash->set(-1);

		return true;
	}


	/**
	 * An excellent example of how to use the Table::onDemand feature.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function getCronLog($a)
	{
		# Replace "NULL" with NULL values
		str::replaceNullStrings($a);

		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		/**
		 * The base query is the base of the search
		 * query to run to get the results, it will
		 * be supplemented with vars are where clauses.
		 */
		$base_query = [
			"include_meta" => true,
			"table" => $rel_table,
			"join" => [[
				"table" => "cron_job",
				"on" => "cron_job_id"
			]],
			"left_join" => [[
				"table" => "user",
				"on" => [
					"user_id" => [$rel_table, "created_by"]
				]
			]],
			"order_by" => [
				"created" => "desc"
			]
		];

		/**
		 * The row handler gets one row of data from SQL,
		 * and its job is to format the row and return
		 * an array of metadata in addition to the column
		 * values to feed to the Grid() class.
		 *
		 * @param array $error
		 *
		 * @return array
		 */
		$row_handler = function(array $error){
			return $this->rowHandler($error);
		};

		# This line is all that is required to respond to the page request
		Table::managePageRequest($a, $base_query, $row_handler);

		return true;
	}

	/**
	 * Is static so it can be called independently from the rest of the class.
	 *
	 * @param array $item
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function rowHandler(array $item, ?array $a = []): array
	{
		switch($item['status']){
		case 'success':
			$name = "check-circle";
			$colour = "success";
			break;
		case 'warning':
			$name = "exclamation-circle";
			$colour = "warning";
			break;
		case 'error':
			$name = "times-circle";
			$colour = "danger";
			break;
		case 'info':
			$name = "info-circle";
			$colour = "info";
			break;
		default:
			$name = "question-circle";
			$colour = "muted";
		}

		$row['Status'] = [
			"icon" => [
				"name" => $name,
				"colour" => $colour,
				"size" => "2x"
			],
			"sm" => 1
		];

		$row['Job'] = [
			"accordion" => [
				"header" => $item['cron_job'][0]['title'],
				"body" => $item['output']
			]
		];

		$seconds = round($item['duration']);

		$row['Time'] = [
			"style" => "font-size:smaller;",
			"class" => "text-muted",
			"html" => "{$seconds} seconds, ".str::ago($item['created']),
			"value" => $item['created']
		];

		$buttons[] = [
			"title" => "Remove alert entry...",
			"icon" => "trash",
			"hash" => [
				"rel_table" => "cron_log",
				"rel_id" => $item['cron_log_id'],
				"action" => "remove"
			],
			"approve" => true,
		];

		$row['Actions'] = [
			"sortable" => false,
			"buttons" => $buttons,
		];

		return $row;
	}
}