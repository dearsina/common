<?php


namespace App\Common\ErrorLog;

use App\Common\Common;
use App\Common\str;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Table;

class ErrorLog extends Common {

	/**
	 * @param bool|null $output_to_email
	 *
	 * @return Card
	 */
	public function card(?bool $output_to_email = NULL){
		return new Card($output_to_email);
	}

	public function unresolved($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$page = new Page([
			"title" => "Unresolved errors",
			"icon" => Icon::get("error")
		]);

		# UrlDEcode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				$a['vars'][$key] = urldecode($val);
			}
		}

		$page->setGrid([[
			"html" => $this->card()->errorsByType($a)
		],[
			"html" => $this->card()->errorsByUser($a)
		],[
			"html" => $this->card()->errorsByReltable($a)
		]]);

		$page->setGrid([
			"html" => $this->card()->errors($a)
		]);

		$this->output->html($page->getHTML());

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
	public function getErrors($a){
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
			"table" => "error_log",
			"left_join" => [[
				"table" => "user",
				"on" => [
					"user_id" => "`error_log`.`created_by`"
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
			$row["Date"] = [
				"value" => $error['created'],
				"html" => str::ago($error['created']),
				"sm" => 2
			];

			$row["Type"] = [
				"value" => $error['title'],
				"accordion" => [
					"header" => $error['title'],
					"body" => str::pre($error['message'])
				],
				"sm" => 4
			];

			$hash = str::generate_uri([
				"rel_table" => $error['rel_table'],
				"rel_id" => $error['rel_id'],
				"action" => $error['action']
			]);

			$row['Action'] = [
				"value" => $hash,
				"accordion" => [
					"header" => $hash,
					"body" => str::pre($error['vars'])
				]
			];

			$this->addNames($error['user']);
			$row['User'] = [
				"value" => $error['user'][0]['full_name'] ?: "(Not logged in)",
				"html" => $error['user'][0]['full_name'] ?: "(Not logged in)",
				"hash" => $error['user'][0]['user_id'] ? [
					"rel_table" => "user",
					"rel_id" => $error['user'][0]['user_id'],
					"action" => "log_in_as"
				] : false
			];

			return $row;
		};

		# This line is all that is required to respond to the page request
		Table::managePageRequest($a, $base_query, $row_handler);

		return true;
	}
}