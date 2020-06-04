<?php


namespace App\Common\IssueType;

use App\Common\Common;
use App\UI\Icon;
use App\UI\Table;

/**
 * Class IssueType
 * @package App\Common\IssueType
 */
class IssueType extends Common {
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

	public function all(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$this->output->modal($this->modal()->all($a));

		# Get the latest issue types
		$this->updateRelTable($a);

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	protected function updateRelTable(array $a) : void
	{
		extract($a);

		$issue_types = $this->sql->select([
			"table" => $rel_table,
			"order_by" => [
				"title" => "ASC"
			]
		]);

		if($issue_types){
			foreach($issue_types as $type){
				$rows[] = [
					"Icon" => [
						"sortable" => false,
						"sm" => 1,
						"style" => [
							"margin" => "0.3rem 0"
						],
						"icon" => $type['icon'],
					],
					"Issue Type" => [

						"html" => $type['title'],
						"hash" => [
							"rel_table" => $rel_table,
							"rel_id" => $type['issue_type_id'],
							"action" => "edit"
						]
					],
					"Action" => [
						"sortable" => false,
						"sm" => 2,
						"class" => "float-right",
						"button" => [[
							"size" => "xs",
							"hash" => [
								"rel_table" => $rel_table,
								"rel_id" => $type['issue_type_id'],
								"action" => "remove",
							],
							"approve" => [
								"icon" => Icon::get("trash"),
								"colour" => "red",
								"title" => "Remove issue type?",
								"message" => "Are you sure you want to remove this issue type?"
							],
							"icon" => Icon::get("trash"),
							"basic" => true,
							"colour" => "danger"
						]]
					]
				];
			}
		} else {
			$rows[] = [
				"Issue Type" => [
					"icon" => Icon::get("new"),
					"html" => "New issue type...",
					"hash" => [
						"rel_table" => $rel_table,
						"action" => "new"
					]
				]
			];
		}

		$this->output->update("all_issue_type", Table::generate($rows));
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

	public function insert(array $a) : bool
	{
		extract($a);

		if(!$this->user->is("admin")){
			//Only admins have access
			return $this->accessDenied();
		}

		$issue_type_id = $this->sql->insert([
			"table" => $rel_table,
			"set" => $vars
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Get the latest issue types
		$this->updateRelTable($a);

		return true;
	}

	/**
	 * Update an issue type.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
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
		$this->updateRelTable($a);

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
		$this->updateRelTable($a);

		return true;
	}
}