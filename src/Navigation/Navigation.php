<?php


namespace App\Common\Navigation;

use App\Common\Output;
use App\Common\str;
use App\Common\User\User;

use App\UI\Navigation\Factory;

class Navigation {
	/**
	 * Public facing method.
	 * All it does is to call the static method.
	 * Allows for AJAX calls to generate/update the navigation.
	 *
	 * @param null $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function generate($a = NULL){
		return Navigation::update();
	}

	/**
	 * Static method to update the navigation
	 * and feed the update to the `$this->output` array.
	 * Doesn't take any variables.
	 *
	 * Expects there to be a \App\Home\Role class
	 *
	 * <code>
	 * Navigation::update();
	 * </code>
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public static function update(){
		$user = new User();
		$output = Output::getInstance();

		/**
		 * At times the session variables may expire,
		 * but the user is still logged in via cookies.
		 * Calling this function will refresh the session
		 * and global variables.
		 */
		$user->isLoggedIn(true);

		$levels = [];

		# Get the generic app levels
		self::getAppLevels($levels);

		# Get any role levels
		self::getRoleLevels($levels);

		# Give the $levels to the factory to make HTML
		$output->navigation(Factory::generate("horizontal", $levels)->getHTML());

		return true;
	}

	private static function getAppLevels(&$levels) : bool
	{
		$levels[1]['title'] = [
			"title" => $_ENV['title']
		];

		$children[]  = [];

		$levels[1]['items'][] = [
			"icon" => "user-headset",
			"alt" => "Help",
			"children" => $children
		];

		return true;
	}

	private static function getRoleLevels(&$levels) : bool
	{
		global $role;

		if(!$role){
			return false;
		}

		if(!$classPath = str::findClass($role, "Navigation")){
			throw new \Exception("A navigation class for the <code>{$role}</code> role does not exist.");
		}

		# Create a new instance of the class
		$classInstance = new $classPath();

		# Set the method (view is the default)
		$method = str::getMethodCase($action) ?: "update";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			throw new \Exception("The <code>{$method}</code> method in the <code>{$classPath}</code> class doesn't exist or is not public.");
			return false;
		}

		# Get the levels for this role
		$role_levels = $classInstance->$method([
			"action" => $method,
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"vars" => $vars
		]);

		# Role levels override app levels, excpet for items, where they're merged
		foreach($levels as $level => $a){
			foreach($a as $key => $val){
				if(!$role_levels[$level][$key]){
					//ignore if there is no corresponding role levels key
					continue;
				}
				if($key == "items"){
					//If they're items, merge them
					$levels[$level][$key] = array_merge($levels[$level][$key], $role_levels[$level][$key]);
				} else {
					//Otherwise, override
					$levels[$level][$key] = $role_levels[$level][$key];
				}
			}
		}


		return true;
	}
}