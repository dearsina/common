<?php


namespace App\Common\ErrorLog;


use App\UI\Icon;
use App\UI\Table;

class Card extends \App\Common\Common {
	public function __construct ($output_to_email)
	{
		parent::__construct();
		$this->output_to_email = $output_to_email;
	}

	public function errorsByType($a){
		$results = $this->sql->select([
			"columns" => [
				"Type" => "title",
				"Errors" => "count(error_log_id)",
			],
			"table" => "error_log",
			"where" => array_merge($a['vars']?:[], [
				"resolved" => "NULL"
			]),
			"order_by" => [
				"Errors" => "DESC"
			]
		]);

		if($results){
			foreach($results as $row){
				$hash = [
					"rel_table" => "error_log",
					"action" => "unresolved",
					"vars" => array_merge($a['vars']?:[], [
						"title" => urlencode($row['Type'])
					])
				];
				$errors[] = [
					"Type" => [
						"hash" => $hash,
						"html" => $row['Type']
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

	public function errorsByUser($a){
		$results = $this->sql->select([
			"flat" => true,
			"columns" => [
				"Errors" => "count(error_log_id)",
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
					"user_id" => "`error_log`.created_by"
				]
			]],
			"where" => array_merge($a['vars']?:[], [
				"resolved" => "NULL"
			]),
			"order_by" => [
				"error_log_id_count" => "DESC"
			]
		]);

		if($results){
			foreach($results as $row){
				$hash = [
					"rel_table" => "error_log",
					"action" => "unresolved",
					"vars" => array_merge($a['vars']?:[],[
						"created_by" => urlencode($row['user.user_id']) ?: "NULL"
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

	public function errorsByReltable($a){
		$results = $this->sql->select([
			"columns" => [
				"rel_table",
				"action",
				"Errors" => "count(error_log_id)",
			],
			"table" => "error_log",
			"where" => array_merge($a['vars']?:[], [
				"resolved" => "NULL"
			]),
			"order_by" => [
				"Errors" => "DESC"
			]
		]);

		if($results){
			foreach($results as $row){
				$rel_table_hash = [
					"rel_table" => "error_log",
					"action" => "unresolved",
					"vars" => array_merge($a['vars']?:[], [
						"rel_table" => $row['rel_table']
					])
				];
				$action_hash = [
					"rel_table" => "error_log",
					"action" => "unresolved",
					"vars" => array_merge($a['vars']?:[], [
						"action" => $row['action']
					])
				];
				$hash = [
					"rel_table" => "error_log",
					"action" => "unresolved",
					"vars" => array_merge($a['vars']?:[], [
						"rel_table" => $row['rel_table'],
						"action" => $row['action']
					])
				];
				$errors[] = [
					"Table" => [
						"hash" => $rel_table_hash,
						"html" => $row['rel_table'],
						"sm" => 4
					],
					"Action" => [
						"hash" => $action_hash,
						"html" => $row['action'],
						"sm" => 6
					],
					"Errors" => [
						"hash" => $hash,
						"html" => $row['Errors'],
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
				"size" => "xs",
				"basic" => true,
				"hash" => [
					"rel_table" => "error_log",
					"action" => "unresolved",
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

	public function errors($a){
		extract($a);

		$button[] = [
			"colour" => "blue",
			"alt" => "View resolved errors",
			"icon" => Icon::get("view"),
			"hash" => [
				"rel_table" => "error_log",
				"action" => "resolved"
			],
			"size" => "xs",
			"class" => "float-right"
		];

		$button[] = [
			"alt" => "Resolve all these errors...",
			"basic" => true,
			"icon" => "flag-checkered",
			"size" => "small",
			"colour" => "success",
			"hash" => [
				"rel_table" => "error_log",
				"action" => "resolve_all",
				"vars" => $vars
			],
			"approve" => [
				"title" => "Batch resolve errors?",
				"message" => "Do you want to batch mark all the errors in this list as resolved?"
			],
			"class" => "float-right"
		];

		$body = Table::onDemand([
			"hash" => [
				"rel_table" => "error_log",
				"action" => "get_errors",
				"vars" => $vars
			],
			"length" => 10
		]);

		$card = new \App\UI\Card([
			"header" => [
				"icon" => Icon::get("error"),
				"title" => "Errors",
				"button" => $button
			],
			"body" => $body,
			"footer" => true
		]);

		return $card->getHTML();
	}
}