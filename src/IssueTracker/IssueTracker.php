<?php


namespace App\Common\IssueTracker;


use App\Common\Common;
use App\Common\ErrorLog\ErrorLog;
use App\Common\str;
use App\UI\Button;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Progress;
use App\UI\Table;

/**
 * Class IssueTracker
 * @package App\Common\IssueTracker
 */
class IssueTracker extends Common {
	/**
	 * @return Card
	 */
	public function card(){
		return new Card();
	}

	/**
	 * @return Modal
	 */
	public function modal(){
		return new Modal();
	}

	public function new(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->new($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	public function edit(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->edit($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	public function insert(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$issue_tracker_id = $this->sql->insert([
			"table" => $rel_table,
			"set" => $vars
		]);

		# Tie to error
		if($vars['error_log_id']){
			//If the issue was created on the back of an error

			# Update the error
			$this->sql->update([
				"table" => "error_log",
				"set" => [
					"issue_tracker_id" => $issue_tracker_id
				],
				"id" => $vars['error_log_id']
			]);
			// Link the two together

			# Get the updated error
			$error = $this->sql->select([
				"table" => "error_log",
				"id" => $vars['error_log_id']
			]);

			# Update the buttons
			$this->output->update("#{$vars['button_id']}", Button::get([
				"button" => ErrorLog::getErrorButtons($error, $vars['button_id'])
			]));
		}

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Give notice to user
		$this->log->success([
			"title" => "Issue created",
			"message" => "A new issue has been created successfully."
		]);

		# Get the latest issues
		$this->updateIssueTrackerTable();

		return true;
	}

	public function update(array $a) : bool
	{
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

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Get the latest issues
		$this->updateIssueTrackerTable();

		return true;
	}

	/**
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function remove(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		if($notes = $this->sql->select([
			"table" => "issue_note",
			"where" => [
				"issue_tracker_id" => $rel_id
			]
		])){
			foreach($notes as $note){
				$this->sql->remove([
					"table" => "issue_note",
					"id" => $note['issue_note_id']
				]);
			}
		}

		$this->sql->remove([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		if($silent){
			return true;
		}

		if($notes){
			$message = "The issue and its ".str::pluralise_if($notes, "note", true)." were removed.";
		} else {
			$message = "The issue was removed.";
		}

		$this->log->info([
			"title" => "Issue removed",
			"message" => $message
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		$this->hash->set(-1);
		$this->hash->silent();

		# Get the latest issue types
		$this->updateIssueTrackerTable();

		return true;
	}

	private function updateIssueTrackerTable() : void
	{
		$script = str::getScriptTag("onDemandReset(\"all_issue_tracker\");");
		$this->output->append("#all_issue_tracker > .table-container",$script);
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
			"title" => "All issues",
			"icon" => Icon::get("issue")
		]);

		# UrlDEcode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				if(is_array($val)){
					$a['vars'][$key] = $val;
				} else {
					$a['vars'][$key] = urldecode($val);
				}

			}
		}

		$page->setGrid([
			"html" => $this->card()->filters($a)
		]);

		$page->setGrid([
			"html" => $this->card()->issues($a)
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
	public function getIssues($a){
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
				"table" => "issue_type",
				"on" => "issue_type_id"
			],[
				"table" => "issue_priority",
				"on" => "issue_priority_id"
			]],
			"left_join" => [[
				"table" => "user",
				"on" => "user_id"
			],[
				"table" => "error_log",
				"on" => "issue_tracker_id"
			]],
			"order_by" => [
				"issue_priority_id" => "desc",
				"created" => "DESC",
			]
		];

		/**
		 * The row handler gets one row of data from SQL,
		 * and its job is to format the row and return
		 * an array of metadata in addition to the column
		 * values to feed to the Grid() class.
		 *
		 * @param array $issue
		 *
		 * @return array
		 */
		$row_handler = function(array $issue){
			return $this->rowHandler($issue);
		};

		# This line is all that is required to respond to the page request
		Table::managePageRequest($a, $base_query, $row_handler);

		return true;
	}

	/**
	 * How to handle (the presentation/formatting of) a row
	 * of issue data.
	 *
	 * @param $cols
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function rowHandler(array $cols, ?array $a = []): array
	{
		Info::format($cols);

		$row["Type"] = [
			"icon" => [
				"name" => $cols['issue_type']['icon'],
				"alt" => $cols['issue_type']['title']
			],
			"style" => ["margin" => "0.3rem 0"],
			"sm" => 1,
			"col_name" => "issue_type.title"
		];

		$row["Created"] = [
			"html" => str::ago($cols['created']),
			"class" => "text-flat",
			"col_name" => "created"
		];

		$row["Issue"] = [
			"hash" => [
				"rel_table" => "issue_tracker",
				"rel_id" => $cols['issue_tracker_id'],
				"action" => "edit"
			],
			"html" => $cols['title'],
			"style" => ["font-weight" => 500],
			"sm" => 4,
			"col_name" => "title"
		];

		$row["Assigned to"] = [
			"html" => $cols['user']['full_name'] ?: "(Not assigned)",
			"class" => "text-flat",
			"col_name" => "user.first_name"
		];

		$row["Priority"] = [
			"html" => $cols['issue_priority']['title'],
			"alt" => $cols['issue_priority']['desc'],
			"col_name" => "issue_priority.title"
		];

		switch($cols['progress']){
		case 1: $colour = "success"; break;
		default; $colour = "primary"; break;
		}

		$row["Completed"] = [
			"html" => Progress::generate([
				"width" => $cols['progress_percent'],
				"colour" => $colour
			]),
			"style" => ["margin" => "0.2rem 0"],
			"col_name" => "progress"
		];

		return $row;
	}
}