<?php


namespace App\Common\Navigation;


use App\Common\Prototype;
use App\Common\str;
use App\UI\Icon;

/**
 * Class Admin
 * @package App\Common\Navigation
 */
class Admin extends Prototype implements NavigationInterface {
	public $levels = [];
	public $footers = [];

	/**
	 * @inheritDoc
	 */
	public function update (): array
	{
		$this->errors();
		$this->issues();
		$this->fieldTypes();
		$this->cron();
		$this->users();
		$this->permissions();
		$this->examples();
		return $this->levels;
	}

	private function users(): void
	{
		$children[] = [
			"icon" => Icon::get("new"),
			"title" => "New user...",
			"alt" => "Create a new user",
			"hash" => [
				"rel_table" => "user",
				"action" => "new"
			]
		];

		$children[] = [
			"icon" => [
				"name" => Icon::get("role"),
			],
			"title" => "User roles",
			"alt" => "Manage a given user's roles",
			"hash" => [
				"rel_table" => "user_role",
				"action" => "all",
			],
		];

		$children[] = [
			"icon" => Icon::get("all"),
			"title" => "All users",
			"alt" => "See all users registered",
			"hash" => [
				"rel_table" => "user",
				"action" => "all"
			]
		];

		$this->levels[2]['items'][] = [
			"icon" => "users",
			"title" => "Users",
			"children" => $children
		];
	}

	private function permissions() : void
	{

		$children[] = [
			"icon" => Icon::get("user_permission"),
			"title" => "User permissions",
			"alt" => "Manage each user's permissions",
			"hash" => [
				"rel_table" => "user_permission",
			],
		];

		if($roles = $this->sql->select([
			"table" => "role",
			"order_by" => [
				"role" => "ASC"
			]
		])){
			foreach($roles as $role){
				$grandchildren[] = [
					"icon" => $role['icon'],
					"title" => str::title($role['role']),
					"hash" => [
						"rel_table" => "role_permission",
						"vars" => [
							"role_id" => $role['role_id']
						]
					]
				];
			}
		}

		$children[] = [
			"icon" => Icon::get("role_permission"),
			"title" => "Role permissions",
			"alt" => "Manage each role's permissions",
//			"hash" => [
//				"rel_table" => "role_permission",
//			],
			"children" => $grandchildren
		];

		$children[] = [
			"icon" => [
				"name" => Icon::get("roles"),
			],
			"title" => "Roles",
			"alt" => "Roles and their icons",
			"hash" => [
				"rel_table" => "role",
				"action" => "all",
			],
		];

		$this->levels[2]['items'][] = [
			"icon" => Icon::get("permission"),
			"title" => "Permissions",
			"children" => $children
		];
	}

	private function cron() : void
	{
		$children[] = [
			"icon" => [
				"name" => Icon::get("cron_job"),
			],
			"title" => "All cron jobs",
			"alt" => "Manage cron jobs",
			"hash" => [
				"rel_table" => "cron_job",
				"action" => "all",
			],
		];

		$children[] = [
			"icon" => Icon::get("log"),
			"title" => "Cron job alert",
			"alt" => "View the cron job alert",
			"hash" => [
				"rel_table" => "cron_log",
				"action" => "all",
			],
		];

		$this->levels[2]['items'][] = [
			"icon" => Icon::get("cron_job"),
			"title" => "Cron jobs",
			"children" => $children
		];
	}

	private function examples() : void
	{
		$this->levels[2]['items'][] = [
			"icon" => Icon::get("example"),
			"title" => "Examples",
			"alt" => "Elements and how to build them",
			"hash" => [
				"rel_table" => "example",
			],
//			"children" => $children
		];
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
			"icon" => Icon::get("issue"),
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
				"name" => "cogs"
			],
			"title" => "Issue types",
			"alt" => "Manage issue types",
			"hash" => [
				"rel_table" => "issue_type",
				"action" => "all"
			],
		];

		$this->levels[2]['items'][] = [
			"icon" => Icon::get("issue"),
			"title" => "Issues",
			"alt" => "Development issues and bugs",
//			"hash" => [
//				"rel_table" => "issue_tracker",
//				"action" => "all"
//			],
			"children" => $children
		];
	}

	private function fieldTypes() : void
	{
		$children[] = [
			"icon" => Icon::get("new"),
			"title" => "New field type",
			"alt" => "Create a new field type",
			"hash" => [
				"rel_table" => "field_type",
				"action" => "new",
			],
		];

		$children[] = [
			"icon" => Icon::get("field_type"),
			"title" => "All field types",
			"alt" => "See all field types",
			"hash" => [
				"rel_table" => "field_type",
				"action" => "all"
			],
		];


		$this->levels[2]['items'][] = [
			"icon" => Icon::get("field_type"),
			"title" => "Field types",
			"alt" => "Field types for custom client fields",
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
				"type" => "light",
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
		$children[] = [
			"icon" => [
				"type" => "thick",
				"name" => "cogs"
			],
			"title" => "Manage error notifications",
			"alt" => "See and alter the frequency of error notifications to admins",
			"hash" => [
				"rel_table" => "admin",
				"action" => "error_notification"
			],
		];
		$this->levels[2]['items'][] = [
			"icon" => Icon::get("error"),
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