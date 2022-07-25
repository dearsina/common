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

	/**
	 * @param bool|null $output_to_email
	 *
	 * @return Card
	 */
	public function card(?bool $output_to_email = NULL){
		return new Card($output_to_email);
	}

	/**
	 * @return Modal
	 */
	public function modal(){
		return new Modal();
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function unresolved($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => "Unresolved errors",
			"icon" => [
				"type" => "thick",
				"name" => Icon::get("error")
			],
		]);

		# UrlDEcode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				$a['vars'][$key] = urldecode($val);
			}
		}

		$page->setGrid([[
			"sm" => 4,
			"html" => $this->card()->errorsByType($a)
		],[
			"sm" => 4,
			"html" => $this->card()->errorsByUser($a)
		],[
			"sm" => 4,
			"html" => $this->card()->errorsByReltable($a)
		]]);

		$page->setGrid([
			"html" => $this->card()->errors($a)
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
	public function resolved($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => "Resolved errors",
			"icon" => [
				"type" => "light",
				"name" => Icon::get("error")
			],
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
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function all($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => "All errors",
			"icon" => [
				"type" => "duotone",
				"name" => Icon::get("errors")
			],
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
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function linkToExistingIssue($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$issue = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		$a['vars'] = array_merge($a['vars'] ?:[], $issue ?:[]);

		$this->output->modal($this->modal()->linkToExistingIssue($a));

		$this->hash->set(-1);
		$this->hash->silent();

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
		str::replaceNullStrings($a);

		extract($a);

		/**
		 * We're stripping this var out because
		 * due to its complexity, we treat it
		 * in the base_query instead of feeding it
		 * to the Table class.
		 */
		unset($a['vars']['resolved']);

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
			"table" => "error_log",
			"left_join" => [[
				"table" => "user",
				"on" => [
					"user_id" => ["error_log", "created_by"]
				]
			]],
			"where" => [
				["resolved", "IS", $vars['resolved'] == 'unresolved' ? NULL : false],
				["resolved", "IS NOT", $vars['resolved'] == 'resolved'? NULL : false]
			],
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
			"col_name" => "created"
		];

		$first_line = str::explode(["\r\n","\n"], $error['message'])[0];
		$first_line = strip_tags($first_line);
		$header = "<b>{$error['title']}</b> ".$first_line;

		$body = html_entity_decode(trim($error['message']));
		$message_len = strlen($body);
		if($message_len > self::MAX_ERROR_BODY_LENGTH){
			$body = substr($body, 0, self::MAX_ERROR_BODY_LENGTH);
			$body = str::pre($body). "<small class=\"text-muted\">(Truncated. Whole error ".str::number($message_len)." characters.)</small>";
		} else {
			$body = str::pre($body);
		}

		$row["Type"] = [
			"accordion" => [
				"header" => [
					"class" => "text-flat",
					"title" => $header,
				],
				"body" => $body
			],
			"sm" => 4,
//			"class" => "text-flat",
			"col_name" => "title"
		];

		$hash = str::generate_uri([
			"rel_table" => $error['rel_table'],
			"rel_id" => $error['rel_id'],
			"action" => $error['action']
		]);

		$error['vars'] = json_decode($error['vars'], true);

		if($error['vars']){
			$row['Action'] = [
				"accordion" => [
					"header" => $hash ?: "<span class=\"text-silent\">(None)</span>",
					"body" => str::pre(json_encode($error['vars'], JSON_PRETTY_PRINT))
				],
			];
		} else {
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
				"action" => "log_in_as"
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
			"button" => ErrorLog::getErrorButtons($error, $button_id)
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
			"id" => $rel_id
		]);

		$error = $this->sql->select([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		$this->output->update("#{$vars['button_id']}", Button::get([
			"button" => ErrorLog::getErrorButtons($error, $vars['button_id'])
		]));

		$this->log->success([
			"icon" => Icon::get("error"),
			"title" => "Error updated",
			"message" => "The error was updated."
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
	public function resolveAll($a){
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

		if($errors_to_resolve = $this->sql->select([
			"table" => "error_log",
			"where" => array_merge($vars ?:[] ,[
				"resolved" => NULL
			])
		])){
			foreach($errors_to_resolve as $error){
				$this->sql->update([
					"table" => $rel_table,
					"set" => [
						"resolved" => "NOW()"
					],
					"id" => $error['error_log_id']
				]);
			}
		}

		$this->log->success([
			"icon" => Icon::get("resolve"),
			"title" => str::pluralise_if($errors_to_resolve, "error", true)." resolved",
			"message" => str::were($errors_to_resolve, "error", true)." marked as resolved."
		]);

		$this->hash->set([
			"rel_table" => $rel_table,
			"action" => "unresolved"
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
	public static function getErrorButtons(array $error, string $button_id) : array
	{
		if($error['resolved']){
			//if this error has been resolved
			$buttons[] = [
				"colour" => "primary",
				"basic" => true,
				"disabled" => true,
				"size" => "s",
				"icon" => "play"
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
						"silent" => true
					]
				]
			];
		} else {
			//If the error is unresolved
			$buttons[] = [
				"colour" => "primary",
				"size" => "s",
				"alt" => "Execute the request that caused the error",
				"hash" => $error,
				"icon" => "play"
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
						"silent" => true
					]
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
					]
				],
				"icon" => "unlink",
				"colour" => "info",
			];
		} else {
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
						"issue_type_id" => "908b20bb-ed0e-405e-858a-83682ba4533c" //bug
					]
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
					]
				],
				"icon" => "link",
				"colour" => "info",
			];
		}

		return $buttons;
	}
}