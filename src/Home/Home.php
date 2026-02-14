<?php


namespace App\Common\Home;


use App\Common\Hash;
use App\Common\Navigation\App;
use App\Common\Prototype;
use App\Common\str;

use App\UI\Card\Card;
use App\UI\Icon;
use App\UI\Page;

/**
 * Class Home
 * @package App\Common\Home
 */
class Home extends Prototype {

	/**
	 * This is the junction method that is called
	 * on both "/home" and "/". It will look for
	 * custom role home pages in App\Home, if none are found
	 * will display the generic home.
	 *
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \Exception
	 */
	public function view(array $a): bool
	{
		extract($a);

		# Check to see if the user is logged in
		if($this->user->isLoggedIn()){
			global $role;

			# If a user with elevated permissions is accessing a subdomain, route them to app
			if(!Home::rightSubdomainForRole($a)){
				return true;
			}

			if($role != "user" && $subdomain && $subdomain != "app"){
				$url = "https://app.{$_ENV['domain']}/";
				$url .= str::generate_uri([
					"subdomain" => "app",
					"rel_table" => $rel_table,
					"rel_id" => $rel_id,
					"action" => $action,
					"vars" => $vars,
				]);
				$this->hash->set($url);
				return true;
			}

			return $this->viewRole($a, $role);
		}

		# If they're on a particular subdomain, send them as users to that subdomain
		if($subdomain && $subdomain != "app"){
			$role = "user";
			return $this->viewRole($a, $role);
		}
		// This allows for non-logged in users to see a home page on their selected subdomain

		# User needs to be logged in
		return $this->accessDenied();
	}

	/**
	 * Will check if the current subdomain is appropriate
	 * for the current user's role.
	 *
	 * @param array|null $a
	 *
	 * @return bool Returns true if the subdomain is appropriate, false if a redirect is needed.
	 */
	public static function rightSubdomainForRole(?array $a = NULL): bool
	{
		# Get the role
		global $role;

		# If there is no role, we don't care about subdomains
		if(!$role){
			return true;
		}

		# Users can access anything
		if($role == "user"){
			return true;
		}

		if($a){
			extract($a);
		}
		else {
			return true;
		}

		# App subdomain is always allowed
		if($subdomain == "app"){
			return true;
		}

		/**
		 * But if a user with a different role than user
		 * is trying to access a different subdomain than app
		 * push them to the app subdomain.
		 */
		$url = "https://app.{$_ENV['domain']}/";
		// The URL ends with a slash

		# If a pathname is specified, use that
		if($vars['pathname']){
			# Ensure the pathname doesn't start with a slash, because the URL already ends with a slash
			if(strpos($vars['pathname'], "/") === 0){
				$vars['pathname'] = substr($vars['pathname'], 1);
			}
			$url .= $vars['pathname'];
		}

		# Otherwise, reconstruct the URL
		else {
			$url .= str::generate_uri([
				"subdomain" => "app",
				"rel_table" => $rel_table,
				"rel_id" => $rel_id,
				"action" => $action,
				"vars" => $vars,
			]);
		}

		# Update the URL
		Hash::getInstance()->set($url);

		# Return false, to trigger the change
		return false;
	}


	/**
	 * Once we have a role, we can try to find a specific
	 * home page for that role.
	 *
	 * @param array  $a
	 * @param string $role
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function viewRole(array $a, string $role): bool
	{
		extract($a);

		# Ideally there is an app method for this role
		if($classPath = str::findClass($role, "Home")){
			//if an App class for this role exists, use it

			# Create a new instance of the class
			$classInstance = new $classPath();

			# Set the method (view is the default)
			$method = str::getMethodCase($action) ?: "view";

			# Ensure the method is available
			if(!str::methodAvailable($classInstance, $method)){
				if(str::isDev()){
					throw new \Exception("The <code>" . htmlentities(str::generate_uri($a)) . "</code> method doesn't exist or is not public.");
				}
				else {
					throw new \Exception("The requested resource was not found.", 404);
				}
			}

			# Use the app method
			return $classInstance->$method([
				"subdomain" => $subdomain,
				"action" => $method,
				"rel_table" => $rel_table,
				"rel_id" => $rel_id,
				"vars" => $vars,
			]);
		}

		# Otherwise, use the generic view
		return $this->genericView($a);
	}


	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function genericView($a): bool
	{
		extract($a);

		global $role;

		$role = $this->sql->select(["table" => "role", "where" => ["role" => $role], "limit" => 1]);

		$page = new Page([
			"title" => "Generic {$role['role']} home",
			"subtitle" => "Create a <code>" . str::getClassCase("\\App\\Home\\{$role['role']}") . "</code> class to avoid this screen.",
			"icon" => $role['icon'],
		]);

		# Make sure the app has at least one admin
		if(!$this->info("admin")){
			//if the app has no admins
			$page->setGrid([
				"html" => $this->user->card()->newAdmin(),
			]);
		}

		$rows["Subdomain"] = strtoupper($subdomain);
		$rows["User IP address"] = $_SERVER['REMOTE_ADDR'];
		$rows["User Agent"] = $_SERVER['HTTP_USER_AGENT'];
		$rows["Cookies"] = str::pre(print_r($_COOKIE, true));

		$card = new Card([
			"header" => "This is the generic home for " . str::A($role['role']),
			"rows" => [
				"sm" => 3,
				"rows" => $rows,
			],
			"body" => $body,
		]);

		$page->setGrid($card->getHTML());

		$this->output->html($page->getHTML());

		return true;
	}
}