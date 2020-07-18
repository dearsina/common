<?php


namespace App\Common;


use App\UI\Icon;
use App\UI\Table;

/**
 * Class CommonModal
 *
 * An abstract base class to be used if everything for this class
 * is to be done via modals.
 *
 * @package App\Common
 */
abstract class CommonModal extends Common {
	/**
	 * Generic all method.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function all(array $a): bool
	{
		extract($a);

		if (!$this->permission()->get($rel_table, $rel_id, "R")){
			return $this->accessDenied($a);
		}

		$this->output->modal($this->modal()->all($a));

		# Get the latest issue types
		$this->updateRelTable($a);

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * Generic new method.
	 * Assumes there is a Field() class.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function new(array $a) : bool
	{
		extract($a);

		if (!$this->permission()->get($rel_table, NULL, "C")){
			return $this->accessDenied($a);
		}

		$this->output->modal($this->modal()->new($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * Generic insert method.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function insert(array $a) : bool
	{
		extract($a);

		if (!$this->permission()->get($rel_table, NULL, "C")){
			return $this->accessDenied();
		}

		# Optional, gets the next order
		$vars['order'] = $this->getOrder($rel_table);

		# Insert the records
		$this->sql->insert([
			"table" => $rel_table,
			"set" => $vars
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Update the all table
		$this->updateRelTable($a);

		return true;
	}

	/**
	 * Generic edit method.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function edit(array $a) : bool
	{
		extract($a);

		if(!$rel_id){
			throw new \Exception("An edit was requested without an ID.");
		}

		if (!$this->permission()->get($rel_table, $rel_id, "U")){
			return $this->accessDenied($a);
		}

		$this->output->modal($this->modal()->edit($a));

		$this->hash->set(-1);
		$this->hash->silent();

		return true;
	}

	/**
	 * Generic update method.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function update(array $a) : bool
	{
		extract($a);

		if (!$this->permission()->get($rel_table, $rel_id, "U")){
			return $this->accessDenied();
		}

		$this->sql->update([
			"table" => $rel_table,
			"set" => $vars,
			"id" => $rel_id
		]);

		# Closes the (top-most) modal
		$this->output->closeModal();

		# Update the all table
		$this->updateRelTable($a);

		# Return the URL to what it was
		$this->hash->set(-1);

		# As we're updating the relevant table, no need to refresh the whole page
		$this->hash->silent();

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

		if (!$this->permission()->get($rel_table, $rel_id, "D")){
			return $this->accessDenied();
		}

		$this->sql->remove([
			"table" => $rel_table,
			"id" => $rel_id
		]);

		if($silent){
			return true;
		}

		# Update the all table
		$this->updateRelTable($a);

		# Return the URL to what it was
		$this->hash->set(-1);

		# As we're updating the relevant table, no need to refresh the whole page
		$this->hash->silent();

		return true;
	}

	/**
	 * Generic updateRelTable method. Relies on the rowHandler method
	 * to format each line.
	 *
	 * @param array $a
	 *
	 * @throws \Exception
	 */
	protected function updateRelTable(array $a) : void
	{
		extract($a);

		$$rel_table = $this->info($rel_table);

		if($$rel_table){
			foreach($$rel_table as $row){
				$rows[] = $this->rowHandler($row, $a);
			}
		} else {
			$rows[] = Table::emptyTablePlaceholder($rel_table);
		}

		if (in_array("order", array_keys($this->sql->getTableMetadata($rel_table, true)))){
			//if this table has an order-by column
			$this->output->update("all_{$rel_table}", Table::generate($rows, [
				"rel_table" => $rel_table,
				"order" => true
			]));
			return;
		}

		$this->output->update("all_{$rel_table}", Table::generate($rows));
	}

	/**
	 * A _very_ generic rowHandler method, should be switched with a custom method.
	 * Is public so that other classes can access it.
	 *
	 * @param array $cols
	 *
	 * @return array
	 */
	public function rowHandler(array $cols, array $a): array
	{
		extract($a);
		$cols_user_cannot_update = $this->sql->getTableColumnsUsersCannotUpdate($rel_table);

		foreach($cols as $col => $val){
			if(in_array($col, $cols_user_cannot_update)){
				continue;
			}
			if($col == "order"){
				$row["id"] = $cols["{$rel_table}_id"];
				continue;
			}
			$row[str::title($col)] = [
				"html" => $val
			];
			if($col == "title"){
				$row[str::title($col)]['alt'] = str::title("Edit this {$rel_table}");
				$row[str::title($col)]['hash'] = [
					"rel_table" => $rel_table,
					"rel_id" => $cols["{$rel_table}_id"],
					"action" => "edit",
				];
			}
		}

		$row[""] = [
			"sortable" => false,
			"sm" => 2,
			"button" => $this->getRowButtons($cols, $a)
		];

		return $row;
	}

	/**
	 * Generic pair of edit and remove buttons.
	 *
	 * @param array $cols
	 * @param array $a
	 *
	 * @return array
	 */
	protected function getRowButtons(array $cols, array $a): array
	{
		extract($a);
		
		if(key_exists("public", $cols)){
			if($cols['public']){
				$button[] = [
					"alt" => str::title("Remove {$rel_table} from public view"),
					"icon" => "store",
					"colour" => "success",
					"size" => "xs",
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $cols["{$rel_table}_id"],
						"action" => "update",
						"vars" => [
							"public" => 0
						]
					]
				];
			} else {
				$button[] = [
					"alt" => str::title("Make {$rel_table} public"),
					"icon" => "store",
					"colour" => "success",
					"basic" => true,
					"size" => "xs",
					"hash" => [
						"rel_table" => $rel_table,
						"rel_id" => $cols["{$rel_table}_id"],
						"action" => "update",
						"vars" => [
							"public" => 1
						]
					]
				];
			}
		}

		$button[] = [
			"size" => "xs",
			"hash" => [
				"rel_table" => $rel_table,
				"rel_id" => $cols["{$rel_table}_id"],
				"action" => "edit",
			],
			"icon" => Icon::get("edit"),
			"basic" => true,
		];

		$button[] = [
			"size" => "xs",
			"hash" => [
				"rel_table" => $rel_table,
				"rel_id" => $cols["{$rel_table}_id"],
				"action" => "remove",
			],
			"approve" => [
				"icon" => Icon::get("trash"),
				"colour" => "red",
				"title" => str::title("Remove {$rel_table}?"),
				"message" => str::title("Are you sure you want to remove this {$rel_table}?")
			],
			"icon" => Icon::get("trash"),
			"basic" => true,
			"colour" => "danger"
		];

		return $button;
	}
}