<?php


namespace App\Common\IssueTracker;


use App\Common\Common;
use App\Common\str;
use App\UI\Icon;
use App\UI\Page;
use App\UI\Progress;
use App\UI\Table;

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

	public function insert(array $a, $silent = NULL) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->sql->insert([
			"table" => $rel_table,
			"set" => $vars
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

//		if($vars['callback']){
//			$this->hash->set($vars['callback']);
//		}

		# Give notice to user
		$this->log->success([
			"title" => "Issue created",
			"message" => "A new issue has been created successfully."
		]);

		# Get the latest issues
		$this->updateIssueTrackerTable();

		return true;
	}

	public function update(array $a, $silent = NULL) : bool
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

	private function updateIssueTrackerTable() : void
	{
		$script = /** @lang JavaScript */
			<<<EOF
onDemandReset("all_issue_tracker");
EOF;

		$this->output->append("all_issue_tracker > .table-container",str::getScriptTag($script));

//		$issues = $this->info("issue_tracker");
//
//		if($issues){
//			foreach($issues as $issue){
//				$rows[] = IssueTracker::rowHandler($issue);
//			}
//		} else {
//			$rows[] = [
//				"Issue Type" => [
//					"icon" => Icon::get("new"),
//					"html" => "New issue...",
//					"hash" => [
//						"rel_table" => "issue_tracker",
//						"action" => "new"
//					]
//				]
//			];
//		}
//
//		$this->output->update("all_issue_tracker", Table::generate($rows));
	}

	public function all($a){
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$page = new Page([
			"title" => "All issues",
			"icon" => [
				"type" => "duotone",
				"name" => Icon::get("issue")
			],
		]);

		# UrlDEcode the variables
		if($a['vars']){
			foreach($a['vars'] as $key => $val){
				$a['vars'][$key] = urldecode($val);
			}
		}

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
				"created" => "ASC"
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
			return IssueTracker::rowHandler($issue);
		};

		# This line is all that is required to respond to the page request
		Table::managePageRequest($a, $base_query, $row_handler);

		return true;
	}

	/**
	 * How to handle (the presentation/formatting of) a row
	 * of issue data.
	 *
	 * @param $issue
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function rowHandler($issue){
		Info::format($issue);

		$row["Type"] = [
			"icon" => [
				"name" => $issue['issue_type']['icon'],
				"alt" => $issue['issue_type']['title']
			],
			"style" => ["margin" => "0.3rem 0"],
			"sm" => 1,
			"col_name" => "`issue_type`.`title`"
		];

		$row["Created"] = [
			"html" => str::ago($issue['created']),
			"class" => "text-flat",
			"col_name" => "created"
		];

		$row["Issue"] = [
			"hash" => [
				"rel_table" => "issue_tracker",
				"rel_id" => $issue['issue_tracker_id'],
				"action" => "edit"
			],
			"html" => $issue['title'],
			"style" => ["font-weight" => 500],
			"sm" => 4,
			"col_name" => "title"
		];


		$row["Assigned to"] = [
			"html" => $issue['user']['full_name'] ?: "(Not assigned)",
			"class" => "text-flat",
			"col_name" => "`user`.`first_name`"
		];

		$row["Priority"] = [
			"html" => $issue['issue_priority']['title'],
			"alt" => $issue['issue_priority']['desc'],
			"col_name" => "`issue_priority`.`title`"
		];

		switch($issue['progress']){
		case 1: $colour = "success"; break;
		default; $colour = "primary"; break;
		}

		$row["Completed"] = [
			"html" => Progress::generate([
				"width" => $issue['progress_percent'],
				"colour" => $colour
			]),
			"style" => ["margin" => "0.2rem 0"],
			"col_name" => "progress"
		];

		return $row;
	}
}