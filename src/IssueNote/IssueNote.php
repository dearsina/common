<?php


namespace App\Common\IssueNote;


use App\Common\str;
use App\UI\Icon;
use App\UI\Table;

/**
 * Class IssueNote
 * @package App\Common\IssueNote
 */
class IssueNote extends \App\Common\Common {
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

	public function insert(array $a) : bool
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

		# Remove the text that is currently in the note textarea field
		$this->output->update($vars['textarea_id'], "");

		# Get the latest issue types
		$this->updateIssueNoteTable($vars['issue_tracker_id']);

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

		# Get the latest issue types
		$this->updateIssueNoteTable($vars['issue_tracker_id']);

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

		$this->sql->remove([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		if($silent){
			return true;
		}

		# Get the latest issue types
		$this->updateIssueNoteTable($vars['issue_tracker_id']);

		return true;
	}

	/**
	 * @param $issue_tracker_id
	 *
	 * @throws \Exception
	 */
	public function updateIssueNoteTable($issue_tracker_id) : void
	{
		if(!is_string($issue_tracker_id)){
			throw new \Exception("To update the issue note table, you'll need to provide a corresponding issue tracker ID.");
		}
		$issue_notes = $this->info([
			"rel_table" => "issue_note",
			"where" => [
				"issue_tracker_id" => $issue_tracker_id
			],
			"order_by" => [
				"created" => "desc"
			]
		]);

		if($issue_notes){
			global $user_id;

			$Parsedown = new \Parsedown();
			$Parsedown->setSafeMode(true);

			foreach($issue_notes as $note){
//				$desc = str_replace(["\r\n", "\r", "\n"], "<br/>", $note['desc']);

				$desc = $Parsedown->text($note['desc']);

				$created   = str::ago($note['created']);
				$buttons = [];
				if($note['created_by'] == $user_id){
					$buttons[] = [
						"title" => "Edit...",
						"icon" => Icon::get("edit"),
						"hash" => [
							"rel_table" => "issue_note",
							"rel_id" => $note['issue_note_id'],
							"action" => "edit"
						],
					];
					$buttons[] = [
						"title" => "Remove...",
						"icon" => Icon::get("trash"),
						"hash" => [
							"rel_table" => "issue_note",
							"rel_id" => $note['issue_note_id'],
							"action" => "remove",
							"vars" => [
								"issue_tracker_id" => $note['issue_tracker_id']
							]
						],
						"approve" => true,
					];
				}
				$html = "{$desc}<span class=\"author\">by {$note['user']['full_name']} {$created}</span>";

				$rows[] = [
					"Notes" => [
						"value" => $note['created'],
						"html" => $html,
						"buttons" => $buttons
					]
				];
//				$rows[] = [
//					"Icon" => [
//						"sortable" => false,
//						"sm" => 1,
//						"style" => [
//							"margin" => "0.3rem 0"
//						],
//						"icon" => $note['icon'],
//					],
//					"Issue Note" => [
//
//						"html" => $note['title'],
//						"hash" => [
//							"rel_table" => "issue_note",
//							"rel_id" => $note['issue_note_id'],
//							"action" => "edit"
//						]
//					],
//					"Action" => [
//						"sortable" => false,
//						"sm" => 2,
//						"class" => "float-right",
//						"button" => [[
//							"size" => "s",
//							"hash" => [
//								"rel_table" => "issue_note",
//								"rel_id" => $note['issue_note_id'],
//								"action" => "remove",
//							],
//							"approve" => [
//								"icon" => Icon::get("trash"),
//								"colour" => "red",
//								"title" => "Remove issue note?",
//								"message" => "Are you sure you want to remove this issue note?"
//							],
//							"icon" => Icon::get("trash"),
//							"basic" => true,
//							"colour" => "danger"
//						]]
//					]
//				];
			}
		} else {
			$rows[] = [
				"Notes" => [
					"html" => "(No notes found)",
					"class" => "text-silent"
				]
			];
		}

		$this->output->update("#all_issue_note", Table::generate($rows, [
			"style" => [
				"margin-top" => "2rem"
			],
			"class" => "container"
		]));
	}
}