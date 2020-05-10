<?php


namespace App\Common\Navigation;


use App\UI\Icon;

class Admin extends \App\Common\Common implements NavigationInterface {
	private $levels = [];
	private $footers = [];

	/**
	 * @inheritDoc
	 */
	public function update (): array
	{
		$children[] = [
			"title" => "All errors",
			"alt" => "All errors",
			"hash" => [
				"rel_table" => "error_log",
				"action" => "all"
			],
		];
		$this->levels[2]['items'][] = [
			"title" => "Errors",
			"alt" => "All unresolved errors",
			"icon" => Icon::get("error"),
			"hash" => [
				"rel_table" => "error_log",
				"action" => "unresolved"
			],
			"children" => $children
		];

		return $this->levels;
	}

	public function footer() : array
	{
		return $this->footers;
	}
}