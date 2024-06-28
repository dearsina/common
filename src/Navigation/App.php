<?php


namespace App\Common\Navigation;


use App\Common\str;
use App\UI\Icon;

/**
 * Class App
 * @package App\Common\Navigation
 */
class App extends Prototype implements NavigationInterface {
	/**
	 * @inheritDoc
	 */
	public function update (): array {
		# All users see the generic brand title if no subdomain is used
		$this->allUsers();

		# If the user is logged in, show the user-specific menu items
		if($this->user->isLoggedIn()){
			// This will also set the global $role and user_id variables

			# Augments the menu as the user is logged in
			$this->loggedInUsers();
		}

		# Returns the levels populated so far
		return $this->levels;
	}

	/**
	 * @inheritDoc
	 */
	public function footer(): array
	{
		$this->allUsersFooter();
		if($this->user->isLoggedIn()){
			//			$this->loggedInUsersFooter();
		}
		return $this->footers;
	}

	private function allUsersFooter(): void
	{
		$year = date("Y");

		$this->footers[1] = [[
			"sm" => "8",
			"class" => "copyright",
			"html" => "© {$year} {$_ENV['title']}, all rights reserved.",
		], [
			"sm" => "4",
			"style" => [
				"text-align" => "right",
			],
			"html" => "Privacy Policy",
		]];
	}

	private function loggedInUsersFooter(): void
	{
		$this->footers[2][] = [
			"title" => "Get started",
			"sm" => 3,
			"items" => [[
				"title" => "Home",
				"hash" => [
					"rel_table" => "home",
				],
			], [
				"title" => "Downloads",
				"hash" => [
					"rel_table" => "download",
				],
			]],
		];

		$this->footers[2][] = [
			"title" => "About us",
			"sm" => 3,
			"items" => [[
				"title" => "Contact Us",
				"hash" => [
					"rel_table" => "contact_us",
				],
			], [
				"title" => "About",
				"hash" => [
					"rel_table" => "about",
				],
			]],
		];

		$this->footers[2][] = [
			"title" => "Information",
			"sm" => 6,
			"html" => "<p>Lorem ipsum dolor amet, consectetur adipiscing elit. Etiam consectetur aliquet aliquet. Interdum et malesuada fames ac ante ipsum primis in faucibus.</p>",
		];
	}

	private function allUsers(): void
	{
		$this->levels[1]['title'] = [
			"title" => $_ENV['title'],
			"uri" => "/",
		];

		//		$this->levels[1]['items'][] = [
		//			"icon" => "user-headset",
		//			"alt" => "Help",
		//			"children" => $children
		//		];
	}

	/**
	 * Menu items that are available for all logged in users,
	 * regardless of their current role.
	 *
	 * @throws \Exception
	 */
	private function loggedInUsers(): void
	{
		global $user_id;

		if(!$user_id){
			throw new \Exception("User ID missing from the <code>App\\Common\\Navigation\\App</code> method. This should not be possible.");
		}

		# Grab the user
		if(!$user = $this->info("user", $user_id)){
			return;
		}

		# User account
		$children[] = [
			"title" => "Account",
			"icon" => "user",
			"hash" => [
				"rel_table" => "user",
				"rel_id" => $user_id,
			],
		];

		# Switch roles
		$children[] = $this->switchRoles($user);

		# Log out
		$children[] = [
			"title" => "Log out",
			"icon" => "power-off",
			"hash" => [
				"rel_table" => "user",
				"action" => "logout",
			],
		];

		global $role;

		$this->levels[1]['items'][] = [
			"icon" => $user['user_role'][array_search($role, array_column($user['user_role'], "role"))]['icon'],
			"alt" => "You are currently in the {$role} role",
			"direction" => "left",
			"children" => $children,
		];
	}

	/**
	 * Produce the dropdown menu item
	 * that allows the user to switch between their
	 * given roles.
	 *
	 * @param $user
	 *
	 * @return array|bool
	 */
	private function switchRoles($user)
	{
		if(!is_array($user['user_role']) || count($user['user_role']) == 1){
			//if the user doesn't have multiple roles
			return false;
		}

		global $role;

		foreach($user['user_role'] as $user_role){
			if($user_role['rel_table'] == $role){
				$disabled = true;
				$badge = [
					"colour" => "success",
					"icon" => "check",
					"pill" => true,
				];
			}
			else {
				$disabled = false;
				$badge = false;
			}

			$url = str::getDomain("app");
			$url .= str::generate_uri([
				"rel_table" => "user_role",
				"action" => "switch",
				"vars" => [
					"user_id" => $user['user_id'],
					"new_role" => $user_role['rel_table'],
					"callback" => $this->hash->getCallback(true),
				],
			]);

			$children[] = [
				"title" => str::title($user_role['rel_table']),
				"badge" => $badge,
				"icon" => $user_role['icon'],
				"url" => $url,
				"disabled" => $disabled,
			];
		}

		return [
			"title" => "Switch role",
			"icon" => "random",
			"direction" => "left",
			"children" => $children,
		];
	}
}