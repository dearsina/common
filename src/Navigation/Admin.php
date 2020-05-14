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

		$this->errors();
		$this->issues();
		return $this->levels;
	}

	private function issues() : void
	{
		$children[] = [
			"icon" => Icon::get("new"),
			"title" => "New issue",
			"alt" => "Create a new issue",
			"hash" => [
				"rel_table" => "issue_tracker",
				"action" => "new",
				"vars" => [
					"callback" => [
						"rel_table" => "issue_tracker",
						"action" => "all"
					]
				]
			],
		];

		$children[] = [
			"icon" => [
				"type" => "thick",
				"name" => Icon::get("issue")
			],
			"title" => "All issues",
			"alt" => "See all issues",
			"hash" => [
				"rel_table" => "issue_tracker",
				"action" => "all"
			],
		];

		$children[] = [
			"icon" => [
				"type" => "thick",
				"name" => "cog"
			],
			"title" => "Issue types",
			"alt" => "Manage issue types",
			"hash" => [
				"rel_table" => "issue_type",
				"action" => "all"
			],
		];

		$this->levels[2]['items'][] = [
			"icon" => [
				"type" => "duotone",
				"name" => Icon::get("issue")
			],
			"title" => "Issues",
			"alt" => "Development issues and bugs",
//			"hash" => [
//				"rel_table" => "issue_tracker",
//				"action" => "all"
//			],
			"children" => $children
		];
	}

	private function errors() : void
	{
		$children[] = [
			"icon" => [
				"type" => "thick",
				"name" => Icon::get("error")
			],
			"title" => "Unresolved errors",
			"alt" => "See all errors marked as unresolved only",
			"hash" => [
				"rel_table" => "error_log",
				"action" => "unresolved"
			],
		];
		$children[] = [
			"icon" => [
				"type" => "thin",
				"name" => Icon::get("error")
			],
			"title" => "Resolved errors",
			"alt" => "See all errors marked as resolved only",
			"hash" => [
				"rel_table" => "error_log",
				"action" => "resolved"
			],
		];
		$children[] = [
			"icon" => [
				"type" => "duotone",
				"name" => "viruses",
			],
			"title" => "All errors",
			"alt" => "See all errors",
			"hash" => [
				"rel_table" => "error_log",
				"action" => "all"
			],
		];
		$this->levels[2]['items'][] = [
			"icon" => [
				"type" => "full",
				"name" => Icon::get("error")
			],
			"title" => "Errors",
			"alt" => "All unresolved errors",
//			"hash" => [
//				"rel_table" => "error_log",
//				"action" => "unresolved"
//			],
			"children" => $children
		];
	}

	public function footer() : array
	{
		return $this->footers;
	}
}