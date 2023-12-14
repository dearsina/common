<?php


namespace App\Common\ErrorLog;


use App\Common\str;
use App\UI\Countdown;
use App\UI\Icon;
use App\UI\Table;

/**
 * Class Card
 * @package App\Common\ErrorLog
 */
class Card extends \App\Common\Prototype {
	private $output_to_email;

	/**
	 * Card constructor.
	 *
	 * @param $output_to_email
	 */
	public function __construct ($output_to_email)
	{
		parent::__construct();
		$this->output_to_email = $output_to_email;
	}

	/**
	 * @param $a
	 *
	 * @return string
	 */
	public function errorsByType($a){
		# Replace "NULL" with NULL values
		str::replaceNullStrings($a);

		extract($a);

		$results = $this->sql->select([
			"columns" => [
				"Type" => "title",
				"Errors" => ["count", "error_log_id"],
			],
			"table" => $rel_table,
			"where" => array_merge($a['vars']?:[], [
				["resolved", "=",  $a['action'] == 'unresolved' ? NULL : false],
				["resolved", "<>", $a['action'] == 'resolved'   ? NULL : false]
			]),
			"order_by" => [
				"Errors" => "DESC"
			]
		]);

		if($results){
			foreach($results as $row){
				$hash = [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($a['vars']?:[], [
						"title" => $row['Type'] ? urlencode($row['Type']) : "NULL"
					])
				];
				$errors[] = [
					"Type" => [
						"hash" => $hash,
						"html" => $row['Type'] ?: "(None)"
					],
					"Errors" => [
						"hash" => $hash,
						"html" => $row['Errors']
					]
				];
			}
		}
		return $this->errorBreakdown($a, "title", $errors, "Unresolved errors by type", "object-group");
	}

	/**
	 * @param $a
	 *
	 * @return string
	 */
	public function errorsByUser($a){
		# Replace "NULL" with NULL values
		str::replaceNullStrings($a);

		extract($a);
		$results = $this->sql->select([
			"flat" => true,
			"columns" => [
				"Errors" => ["count", "error_log_id"],
			],
			"table" => "error_log",
			"left_join" => [[
				"columns" => [
					"user_id",
					"first_name",
					"last_name"
				],
				"table" => "user",
				"on" => [
					"user_id" => ["error_log", "created_by"]
				]
			]],
			"where" => array_merge($a['vars']?:[], [
				["resolved", "=",  $a['action'] == 'unresolved' ? NULL : false],
				["resolved", "<>", $a['action'] == 'resolved'   ? NULL : false]
			]),
			"order_by" => [
				"error_log_id_count" => "DESC"
			]
		]);

		if($results){
			foreach($results as $row){
				$hash = [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($a['vars']?:[],[
						"created_by" => urlencode($row['user.user_id'] ? $row['user.user_id'] : '') ?: "NULL"
					])
				];
				$errors[] = [
					"Type" => [
						"hash" => $hash,
						"html" => trim("{$row['user.first_name']} {$row['user.last_name']}") ?: "(Not logged in)",
					],
					"Errors" => [
						"hash" => $hash,
						"html" => $row['Errors']
					]
				];
			}
		}
		return $this->errorBreakdown($a, "created_by", $errors, "Unresolved errors by user", Icon::get("user"));
	}

	/**
	 * @param $a
	 *
	 * @return string
	 */
	public function errorsByReltable($a){
		# Replace "NULL" with NULL values
		str::replaceNullStrings($a);

		extract($a);
		$results = $this->sql->select([
			"columns" => [
				"rel_table",
				"action",
				"Errors" => ["count", "error_log_id"],
			],
			"table" => "error_log",
			"where" => array_merge($a['vars']?:[], [
				["resolved", "=",  $a['action'] == 'unresolved' ? NULL : false],
				["resolved", "<>", $a['action'] == 'resolved'   ? NULL : false]
			]),
			"order_by" => [
				"Errors" => "DESC"
			]
		]);

		if($results){
			foreach($results as $row){
				$rel_table_hash = [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($a['vars']?:[], [
						"rel_table" => $row['rel_table'] ?:"NULL"
					])
				];
				$action_hash = [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($a['vars']?:[], [
						"action" => $row['action'] ?:"NULL"
					])
				];
				$hash = [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($a['vars']?:[], [
						"rel_table" => $row['rel_table'] ?:"NULL",
						"action" => $row['action'] ?:"NULL"
					])
				];
				$errors[] = [
					"Table" => [
						"hash" => $rel_table_hash,
						"html" => $row['rel_table'] ?: "(None)",
						"class" => "text-flat",
						"sm" => 4
					],
					"Action" => [
						"hash" => $action_hash,
						"html" => $row['action'] ?: "(None)",
						"class" => "text-flat",
						"sm" => 6
					],
					"Errors" => [
						"hash" => $hash,
						"html" => $row['Errors'],
						"class" => "text-flat",
						"sm" => 2
					]
				];
			}
		}
		return $this->errorBreakdown($a, ["rel_table","action"], $errors, "Unresolved errors by rel_table//action", "table");
	}

	/**
	 * The actual method that builds the errorsBy...() cards.
	 *
	 * @param array      $a
	 * @param            $var
	 * @param array|null $errors
	 * @param string     $title
	 * @param string     $icon
	 *
	 * @return string
	 */
	private function errorBreakdown(array $a, $var, ?array $errors, string $title, string $icon){
		extract($a);

		if(!is_array($vars)){
			$vars = [];
		}

		if($errors){
			$body = Table::generate($errors);
		} else {
			$body = "<div class=\"text-silent align-text-center\">No errors logged</div>";
		}

		foreach(is_array($var) ? $var : [$var] as $v){
			$var_array[$v] = NULL;
		}

		if(!empty(array_intersect(array_keys($vars), array_keys($var_array)))){
			$button = [
				"title" => "Clear",
				"size" => "s",
				"basic" => true,
				"hash" => [
					"rel_table" => $rel_table,
					"action" => $action,
					"vars" => array_merge($vars,$var_array)
				]
			];
		}

		$card = new \App\UI\Card\Card([
			"header" => [
				"icon" => $icon,
				"title" => $title,
				"button" => $button
			],
			"body" => [
				"style" => [
					"max-height" => "50vh",
					"overflow" => "auto"
				],
				"html" => $body
			]
		]);

		return $card->getHTML();
	}

	/**
	 * @param $a
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function errors($a){
		extract($a);

		if($action == "unresolved"){
			//if you're currently seeing the unresolved
			$icon = [
				"type" => "thick",
				"name" => "virus"
			];
			$button[] = [
				"basic" => true,
				"colour" => "blue",
				"alt" => "View resolved errors",
				"icon" => [
					"type" => "light",
					"name" => "virus"
				],
				"hash" => [
					"rel_table" => $rel_table,
					"action" => "resolved",
					"vars" => $vars
				],
				"size" => "s",
				"class" => "float-right"
			];
		} else {
			//if you're currently seeing the resolved
			$icon = [
				"type" => "light",
				"name" => "virus"
			];
			$button[] = [
				"basic" => true,
				"colour" => "blue",
				"alt" => "View unresolved errors",
				"icon" => [
					"type" => "thick",
					"name" => "virus"
				],
				"hash" => [
					"rel_table" => $rel_table,
					"action" => "unresolved",
					"vars" => $vars
				],
				"size" => "s",
				"class" => "float-right"
			];
		}

		$button[] = [
			"alt" => "Resolve all these errors...",
			"basic" => true,
			"icon" => "flag-checkered",
			"size" => "small",
			"colour" => "success",
			"hash" => [
				"rel_table" => $rel_table,
				"action" => "resolve_all",
				"vars" => $vars
			],
			"approve" => [
				"title" => "Batch resolve errors?",
				"message" => "Do you want to batch mark all the errors in this list as resolved?"
			],
			"class" => "float-right"
		];

		$id = str::id("on_demand_table");

		$vars['resolved'] = $action;

		# NULL values have to be translated back to "NULL" strings otherwise they get lost
		foreach($vars as $key => $val){
			if($val === NULL){
				$vars[$key] = "NULL";
			}
		}

		$body = Table::onDemand([
			"id" => $id,
			"hash" => [
				"rel_table" => $rel_table,
				"action" => "get_errors",
				"vars" => $vars
			],
			"length" => 10
		]);

		$countdown = Countdown::generate([
			"modify" => "+5 minutes", //A string modifying the datetime
			"pre" => "Refreshing in ", //Text that goes before the timer
			"post" => ".",
			"callback" => "onDemandReset", //The name of a function to call at zero
			"vars" => $id, //Variables to send to the callback function,
			"restart" => [
				"minutes" => 5
			]
		]);

		$card = new \App\UI\Card\Card([
			"header" => [
				"icon" => $icon,
				"title" => "Errors",
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
}