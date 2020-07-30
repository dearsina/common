<?php


namespace App\Common;

use App\UI\Icon;
use App\UI\Page;

/**
 * Class CommonCard
 * An abstract base class to be used if most things for this class
 * is to be done via cards.
 * @package App\Common
 */
abstract class CommonCard extends Common {
	/**
	 * Generic new card page.
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

		$page = new Page([
			"title" => str::title("{$action} {$rel_table}"),
			"icon" => Icon::get($action),
		]);

		$page->setGrid([
			"html" => $this->card()->{$action}($a),
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Generic edit card page.
	 *
	 * @param array $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function edit(array $a): bool
	{
		extract($a);

		if(!$this->permission()->get($rel_table, $rel_id, "U")){
			return $this->accessDenied($a);
		}

		$page = new Page([
			"title" => str::title("{$action} {$rel_table}"),
			"icon" => Icon::get($action),
		]);

		$page->setGrid([
			"html" => $this->card()->{$action}($a),
		]);

		$this->output->html($page->getHTML());

		return true;
	}

	/**
	 * Generic remove method.
	 *
	 * @param array $a
	 * @param null  $silent
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function remove(array $a, $silent = NULL): bool
	{
		extract($a);

		if (!$this->permission()->get($rel_table, $rel_id, "D")){
			return $this->accessDenied();
		}

		$this->sql->remove([
			"table" => $rel_table,
			"id" => $rel_id,
		]);

		if ($silent){
			return true;
		}

		# Return (and refresh) the last AJAX call
		$this->hash->set(-1);

		return true;
	}
}