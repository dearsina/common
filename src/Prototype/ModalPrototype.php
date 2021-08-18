<?php


namespace App\Common\Prototype;


use App\Common\Prototype;

/**
 * Class ModalPrototype
 *
 * An abstract base class to be used if everything for this class
 * is to be done via modals.
 *
 * @package App\Common
 */
abstract class ModalPrototype extends Prototype {
	/**
	 * The fields names that by default are treated as HTML,
	 * where the tags are not stripped away.
	 */
	const html = [
		"html",
		"body",
	];

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

		if(!$this->permission()->get($rel_table, $rel_id, "R")){
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
	public function new(array $a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, NULL, "C")){
			return $this->accessDenied($a);
		}

		$this->output->modal($this->modal()->new($a));

		# Return the URL to what it was
		$this->hash->set(-1);

		# Don't refresh the whole page
		$this->hash->silent();

		return true;
	}

	/**
	 * Generic insert method.
	 *
	 * Does NOT handle permissions.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function insert(array $a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, NULL, "C")){
			return $this->accessDenied();
		}

		# Optional, gets the next order
		$vars['order'] = $this->getOrder($rel_table);

		# Insert the records
		$this->sql->insert([
			"db" => $this->db,
			"table" => $rel_table,
			"html" => self::html,
			"set" => $vars,
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
	public function edit(array $a): bool
	{
		extract($a);

		if(!$rel_id){
			throw new \Exception("An edit was requested without an ID.");
		}

		if(!$this->permission()->get($rel_table, $rel_id, "U")){
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
	public function update(array $a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "U")){
			return $this->accessDenied();
		}

		$this->sql->update([
			"db" => $this->db,
			"table" => $rel_table,
			"set" => $vars,
			"html" => self::html,
			"id" => $rel_id,
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
	public function remove(array $a, ?bool $silent = NULL): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "D")){
			return $this->accessDenied();
		}

		$this->sql->remove([
			"db" => $this->db,
			"table" => $rel_table,
			"id" => $rel_id,
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
}