<?php


namespace App\Common\ErrorLog;

use App\Common\Prototype;
use App\Common\str;
use App\UI\Button;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Table;

/**
 * Class ErrorLog
 * @package App\Common\ErrorLog
 */
class ErrorLog extends Prototype {
	/**
	 * Maximum number of characters to display of an error message.
	 */
	const MAX_ERROR_BODY_LENGTH = 5000;
	const DEFAULT_PAGE_LENGTH = 10;
	const MAX_PAGE_LENGTH = 100;

	/**
	 * @param bool|null $output_to_email
	 *
	 * @return Card
	 */
	public function card(?bool $output_to_email = NULL)
	{
		return new Card($output_to_email);
	}

	/**
	 * @return Modal
	 */
	public function modal()
	{
		return new Modal();
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function unresolved($a)
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => "Unresolved errors",
			"icon" => [
				"type" => "thick",
				"name" => Icon::get("error"),
			],
		]);

		# URL decode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				$a['vars'][$key] = rawurldecode($val);
			}
		}

		$page->setGrid([[
			"sm" => 4,
			"html" => $this->card()->errorsByType($a),
		], [
			"sm" => 4,
			"html" => $this->card()->errorsByUser($a),
		], [
			"sm" => 4,
			"html" => $this->card()->errorsByReltable($a),
		]]);

		$page->setGrid([
			"html" => $this->card()->errors($a),
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
	public function resolved($a)
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => "Resolved errors",
			"icon" => [
				"type" => "light",
				"name" => Icon::get("error"),
			],
		]);

		# UrlDEcode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				$a['vars'][$key] = urldecode($val);
			}
		}

		$page->setGrid([[
			"html" => $this->card()->errorsByType($a),
		], [
			"html" => $this->card()->errorsByUser($a),
		], [
			"html" => $this->card()->errorsByReltable($a),
		]]);

		$page->setGrid([
			"html" => $this->card()->errors($a),
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
	public function all($a)
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => "All errors",
			"icon" => [
				"type" => "duotone",
				"name" => Icon::get("errors"),
			],
		]);

		# UrlDEcode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				$a['vars'][$key] = urldecode($val);
			}
		}

		$page->setGrid([[
			"html" => $this->card()->errorsByType($a),
		], [
			"html" => $this->card()->errorsByUser($a),
		], [
			"html" => $this->card()->errorsByReltable($a),
		]]);

		$page->setGrid([
			"html" => $this->card()->errors($a),
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
	public function linkToExistingIssue($a)
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$issue = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id,
		]);

		$a['vars'] = array_merge($a['vars'] ?: [], $issue ?: []);

		$this->output->modal($this->modal()->linkToExistingIssue($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * Return one bounded batch for the on-demand error table.
	 *
	 * The default order uses a cursor so older pages do not repeatedly scan and
	 * discard every preceding row. Message bodies are deliberately projected to
	 * a small preview; the complete blob is available through message().
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function getErrors($a)
	{
		str::replaceNullStrings($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$vars = str::urldecode($a['vars'] ?: []);
		$request = $this->getErrorsRequest($vars);
		$where = $this->getErrorsWhere($vars);
		$total_results = NULL;

		if(!$request['start']){
			$total_results = (int)$this->sql->select([
				"table" => "error_log",
				"where" => $where,
				"count" => "error_log_id",
			]);

			if(!$total_results){
				return $this->sendEmptyErrorsResponse($request['id']);
			}
		}

		$use_cursor = !$request['order_by_col']
			&& (!$request['start'] || $request['cursor']);
		if($use_cursor && $request['cursor']){
			$where[] = ["error_log_id", "<", $request['cursor']];
		}

		$rows_query = [
			"table" => "error_log",
			"columns" => [
				"error_log_id",
				"resolved",
				"title",
				"message" => ["LEFT(`error_log`.`message`, " . self::MAX_ERROR_BODY_LENGTH . ")"],
				"message_length" => ["OCTET_LENGTH(`error_log`.`message`)"],
				"subdomain",
				"rel_table",
				"rel_id",
				"action",
				"vars",
				"issue_tracker_id",
				"created",
				"created_by",
			],
			"where" => $where,
			"order_by" => $this->getErrorsOrderBy($request),
			"start" => $use_cursor ? 0 : $request['start'],
			"length" => $request['length'],
		];

		if($request['order_by_col'] == "user.first_name"){
			$rows_query['left_join'] = [[
				"table" => "user",
				"columns" => false,
				"on" => [
					"user_id" => ["error_log", "created_by"],
				],
			]];
		}

		$errors = $this->sql->select($rows_query) ?: [];
		if(!$errors){
			return $this->sendExhaustedErrorsResponse($request);
		}

		$this->addUsersToErrors($errors);
		$last_error_log_id = end($errors)['error_log_id'];
		$rows = array_map(function(array $error){
			return $this->rowHandler($error);
		}, $errors);

		$output = [
			"id" => $request['id'],
			"start" => $request['start'] + count($rows),
			"row_count" => count($rows),
			"rows" => Table::generate($rows, $a, $request['start'] > 0, true),
			"order_by_col" => $request['order_by_col'],
			"order_by_dir" => strtolower($request['order_by_dir']),
		];
		if($total_results !== NULL){
			$output['total_results'] = $total_results;
		}
		if($use_cursor){
			$output['cursor'] = $last_error_log_id;
		}

		$this->output->function("onDemandResponse", $output);

		return true;
	}

	/**
	 * Fetch the complete message only after an administrator explicitly requests it.
	 */
	public function message(array $a): bool
	{
		if(!$this->user->is("admin")){
			return $this->accessDenied($a);
		}

		if(!$error = $this->sql->select([
			"table" => "error_log",
			"columns" => ["error_log_id", "title", "message", "created"],
			"id" => $a['rel_id'],
		])){
			throw new \RuntimeException("The requested error log entry could not be found.");
		}

		$this->output->modal($this->modal()->message($error));
		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	private function getErrorsRequest(array $vars): array
	{
		$order_by_col = in_array($vars['order_by_col'] ?? NULL, [
			"created",
			"title",
			"rel_table",
			"user.first_name",
		], true) ? $vars['order_by_col'] : NULL;

		return [
			"id" => (string)($vars['id'] ?? ""),
			"start" => max(0, (int)($vars['start'] ?? 0)),
			"length" => max(1, min(self::MAX_PAGE_LENGTH, (int)($vars['length'] ?? self::DEFAULT_PAGE_LENGTH))),
			"cursor" => is_string($vars['cursor'] ?? NULL) ? $vars['cursor'] : NULL,
			"order_by_col" => $order_by_col,
			"order_by_dir" => strtoupper($vars['order_by_dir'] ?? "ASC") == "DESC" ? "DESC" : "ASC",
		];
	}

	private function getErrorsWhere(array $vars): array
	{
		$where = [];
		switch($vars['resolved'] ?? NULL) {
		case "unresolved":
			$where[] = ["resolved", "IS", NULL];
			break;
		case "resolved":
			$where[] = ["resolved", "IS NOT", NULL];
			break;
		}

		foreach([
			"error_log_id",
			"title",
			"created_by",
			"rel_table",
			"action",
			"subdomain",
			"issue_tracker_id",
			"connection_id",
			"code",
		] as $column){
			if(!array_key_exists($column, $vars)){
				continue;
			}
			$where[$column] = $vars[$column];
		}

		return $where;
	}

	private function getErrorsOrderBy(array $request): array
	{
		if(!$request['order_by_col']){
			return ["error_log_id" => "DESC"];
		}

		if($request['order_by_col'] == "user.first_name"){
			return [
				"`user`.`first_name` {$request['order_by_dir']}",
				"`error_log`.`error_log_id` DESC",
			];
		}

		return [
			$request['order_by_col'] => $request['order_by_dir'],
			"error_log_id" => "DESC",
		];
	}

	private function addUsersToErrors(array &$errors): void
	{
		$user_ids = array_values(array_unique(array_filter(array_column($errors, "created_by"))));
		$users_by_id = [];
		if($user_ids){
			$users = $this->sql->select([
				"table" => "user",
				"columns" => ["user_id", "first_name", "last_name"],
				"where" => [["user_id", "IN", $user_ids]],
			]) ?: [];
			foreach($users as $user){
				$users_by_id[$user['user_id']] = $user;
			}
		}

		foreach($errors as &$error){
			$user = $users_by_id[$error['created_by']] ?? NULL;
			$error['user'] = $user ? [$user] : [];
		}
		unset($error);
	}

	private function sendEmptyErrorsResponse(string $id): bool
	{
		$this->output->function("onDemandResponse", [
			"id" => $id,
			"start" => 1,
			"total_results" => 0,
			"row_count" => 0,
			"rows" => "<i>No rows found</i>",
		]);

		return true;
	}

	private function sendExhaustedErrorsResponse(array $request): bool
	{
		$this->output->function("onDemandResponse", [
			"id" => $request['id'],
			"start" => $request['start'],
			"total_results" => $request['start'],
			"row_count" => 0,
			"rows" => "",
		]);

		return true;
	}

	/**
	 * @param $error
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function rowHandler(array $error, ?array $a = []): array
	{
		$row["Date"] = [
			"html" => str::ago($error['created']),
			"class" => "text-flat",
			"sm" => 1,
			//			"header_style" => [
			//				"min-width" => "100px",
			//			],
			//			"style" => [
			//				"min-width" => "100px",
			//			],
			"col_name" => "created",
		];

		$first_line = str::explode(["\r\n", "\n"], $error['message'])[0];
		$first_line = strip_tags($first_line);
		$header = "<b>{$error['title']}</b> " . $first_line;

		$body = html_entity_decode(trim($error['message']));
		$message_len = (int)($error['message_length'] ?? strlen($body));
		if($message_len > self::MAX_ERROR_BODY_LENGTH){
			$body = substr($body, 0, self::MAX_ERROR_BODY_LENGTH);
			$body = str::pre($body)
				. "<small class=\"text-muted\">(Truncated. Stored message is " . str::number($message_len) . " bytes.)</small>"
				. Button::generate([
					"title" => "View full message",
					"icon" => "expand",
					"size" => "s",
					"basic" => true,
					"hash" => [
						"rel_table" => "error_log",
						"rel_id" => $error['error_log_id'],
						"action" => "message",
					],
				]);
		}
		else {
			$body = str::pre($body);
		}

		$row["Type"] = [
			"accordion" => [
				"header" => [
					"class" => "text-flat",
					"title" => $header,
				],
				"body" => $body,
			],
			"sm" => 4,
			//			"class" => "text-flat",
			"col_name" => "title",
		];

		$hash = str::generate_uri([
			"rel_table" => $error['rel_table'],
			"rel_id" => $error['rel_id'],
			"action" => $error['action'],
		]);

		$error['vars'] = json_decode($error['vars'], true);

		if($error['vars']){
			$row['Action'] = [
				"accordion" => [
					"header" => $hash ?: "<span class=\"text-silent\">(None)</span>",
					"body" => str::pre(json_encode($error['vars'], JSON_PRETTY_PRINT)),
				],
			];
		}
		else {
			$row['Action'] = [
				"html" => $hash,
			];
		}
		$row['Action']['col_name'] = "rel_table";
		$row['Action']['class'] = "text-flat";
		//		$row['Action']['sm'] = 3;

		str::addNames($error['user']);
		$button_id = str::id("buttons");
		$row['User'] = [
			"sm" => 1,
			//			"header_style" => [
			//				"min-width" => "100px",
			//			],
			//			"style" => [
			//				"min-width" => "100px",
			//			],
			"class" => "text-flat",
			"col_name" => "user.first_name",
			"html" => $error['user'][0]['full_name'] ?: "(Not logged in)",
			"hash" => $error['user'][0]['user_id'] ? [
				"rel_table" => "user",
				"rel_id" => $error['user'][0]['user_id'],
			] : NULL,
		];

		$row['Actions'] = [
			"sortable" => false,
			"sm" => "auto",
			"header_style" => [
				"min-width" => "180px",
			],
			"style" => [
				"min-width" => "180px",
			],
			"id" => $button_id,
			"button" => ErrorLog::getErrorButtons($error, $button_id),
		];

		return $row;
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function update($a): bool
	{
		# Replace "NULL" with NULL values
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				if($val == "NULL"){
					$a['vars'][$key] = NULL;
				}
			}
		}

		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->update([
			"table" => $rel_table,
			"set" => $vars,
			"id" => $rel_id,
		]);

		$error = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id,
		]);

		$this->output->update("#{$vars['button_id']}", Button::get([
			"button" => ErrorLog::getErrorButtons($error, $vars['button_id']),
		]));

		$this->log->success([
			"icon" => Icon::get("error"),
			"title" => "Error updated",
			"message" => "The error was updated.",
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function resolveAll($a)
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

		$result = $this->sql->update([
			"table" => $rel_table,
			"set" => [
				"resolved" => "NOW()",
			],
			"where" => array_merge($vars ?: [], [
				"resolved" => NULL,
			]),
		]);

		$this->log->success([
			"icon" => Icon::get("resolve"),
			"title" => str::pluralise_if($result['affected_rows'], "error", true) . " resolved",
			"message" => str::were($result['affected_rows'], "error", true) . " marked as resolved.",
		]);

		$this->hash->set([
			"rel_table" => $rel_table,
			"action" => "unresolved",
		]);

		return true;
	}

	/**
	 * Generates buttons for a given error.
	 *
	 * @param array  $error
	 * @param string $button_id
	 *
	 * @return array
	 */
	public static function getErrorButtons(array $error, string $button_id): array
	{
		if($error['resolved']){
			//if this error has been resolved
			$buttons[] = [
				"colour" => "primary",
				"basic" => true,
				"disabled" => true,
				"size" => "s",
				"icon" => "play",
			];

			$buttons[] = [
				"size" => "s",
				"alt" => "Mark error as unresolved again",
				"icon" => "flag-alt",
				"basic" => true,
				"colour" => "success",
				"class" => "resolved float-right",
				"hash" => [
					"rel_table" => "error_log",
					"rel_id" => $error['error_log_id'],
					"action" => "update",
					"vars" => [
						"button_id" => $button_id,
						"resolved" => "NULL",
						"silent" => true,
					],
				],
			];
		}
		else {
			//If the error is unresolved
			$buttons[] = [
				"colour" => "primary",
				"size" => "s",
				"alt" => "Execute the request that caused the error",
				"hash" => $error,
				"icon" => "play",
			];

			$buttons[] = [
				"basic" => true,
				"size" => "s",
				"alt" => "Mark error as resolved",
				"icon" => "flag-checkered",
				"colour" => "success",
				"class" => "unresolved",
				"hash" => [
					"rel_table" => "error_log",
					"rel_id" => $error['error_log_id'],
					"action" => "update",
					"vars" => [
						"button_id" => $button_id,
						"resolved" => "NOW()",
						"silent" => true,
					],
				],
			];
		}

		if($error['issue_tracker_id']){
			$buttons[] = [
				"basic" => true,
				"size" => "s",
				"alt" => "Unlink or link to different existing issue",
				"hash" => [
					"rel_table" => "error_log",
					"rel_id" => $error['error_log_id'],
					"action" => "link_to_existing_issue",
					"vars" => [
						"button_id" => $button_id,
					],
				],
				"icon" => "unlink",
				"colour" => "info",
			];
		}
		else {
			$buttons[] = [
				"basic" => true,
				"size" => "s",
				"alt" => "Create an issue from this error",
				"hash" => [
					"rel_table" => "issue_tracker",
					"action" => "new",
					"vars" => [
						"button_id" => $button_id,
						"error_log_id" => $error['error_log_id'],
						"issue_type_id" => "908b20bb-ed0e-405e-858a-83682ba4533c", //bug
					],
				],
				"icon" => "bug",
				"colour" => "warning",
			];

			$buttons[] = [
				"basic" => true,
				"size" => "s",
				"alt" => "Add to existing issue",
				"hash" => [
					"rel_table" => "error_log",
					"rel_id" => $error['error_log_id'],
					"action" => "link_to_existing_issue",
					"vars" => [
						"button_id" => $button_id,
					],
				],
				"icon" => "link",
				"colour" => "info",
			];
		}

		return $buttons;
	}
}
