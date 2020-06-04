<?php


namespace App\Common\Navigation;

use App\Common\Output;
use App\Common\str;
use App\Common\User\User;

use App\UI\Navigation\Factory;

/**
 * Class Navigation
 * @package App\Common\Navigation
 */
class Navigation {
	/**
	 * Public facing method.
	 * All it does is to call the static method.
	 * Allows for AJAX calls to generate/update the navigation.
	 *
	 * @param array|null $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function generate(?array $a = NULL){
		extract($a);
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

		$footers = [];

		# Get the generic app footers
		self::getAppFooters($footers);

		# Get any role footers
		self::getRoleFooters($footers);

		# Generate the navigation
		$navigation = Factory::generate("horizontal", $levels, $footers);

		# Give the $levels to the factory to make HTML
		$output->navigation($navigation->getHTML());
		$output->footer($navigation->getFooterHTML());

		return true;
	}

	/**
	 * @param $levels
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private static function getAppLevels(&$levels) : bool
	{
		# Uses the Common class if it cannot find the App class
		$classPath = str::findClass("App", "Navigation");

		# Create a new instance of the class
		$classInstance = new $classPath();

		# Set the method
		$method = "update";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			throw new \Exception("The <code>{$method}</code> method in the <code>{$classPath}</code> class doesn't exist or is not public.");
		}

		$levels = $classInstance->$method([
			"action" => $method,
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"vars" => $vars
		]);

		return true;
	}

	/**
	 * @param $levels
	 *
	 * @return bool
	 * @throws \Exception
	 */
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

		# Set the method
		$method = "update";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			throw new \Exception("The <code>{$method}</code> method in the <code>{$classPath}</code> class doesn't exist or is not public.");
		}

		# Get the levels for this role
		$role_levels = $classInstance->$method([
			"action" => $method,
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"vars" => $vars
		]);

		# Merge role levels into app levels
		self::mergeLevels($levels, $role_levels);

		return true;
	}

	/**
	 * @param $footers
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private static function getAppFooters(&$footers) : bool
	{
		# Uses the Common class if it cannot find the App class
		$classPath = str::findClass("App", "Navigation");

		# Create a new instance of the class
		$classInstance = new $classPath();

		# Set the method
		$method = "footer";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			throw new \Exception("The <code>{$method}</code> method in the <code>{$classPath}</code> class doesn't exist or is not public.");
		}

		$footers = $classInstance->$method([
			"action" => $method,
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"vars" => $vars
		]);

		return true;
	}

	/**
	 * @param $footers
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private static function getRoleFooters(&$footers) : bool
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

		# Set the method
		$method = "footer";

		# Ensure the method is available
		if(!str::methodAvailable($classInstance, $method)){
			throw new \Exception("The <code>{$method}</code> method in the <code>{$classPath}</code> class doesn't exist or is not public.");
		}

		# Get the footers for this role
		$footers = $classInstance->$method([
			"action" => $method,
			"rel_table" => $rel_table,
			"rel_id" => $rel_id,
			"vars" => $vars
		]);

		# Merge role footers into app footers
//		$footers = array_merge_recursive($footers, $role_footers);

		return true;
	}

	/**
	 * Child levels override parent levels, excpet for items,
	 * where child levels are merged with parent levels.
	 *
	 * @param array $parent
	 * @param array $child
	 */
	private static function mergeLevels(array &$parent, array $child){
		foreach($parent as $level => $a){
			foreach($a as $key => $val){
				if(!$child[$level][$key]){
					//ignore if there is no corresponding role levels key
					continue;
				}
				if($key == "items"){
					//If they're items, merge them
					$parent[$level][$key] = array_merge($parent[$level][$key], $child[$level][$key]);
				} else {
					//Otherwise, override
					$parent[$level][$key] = $child[$level][$key];
				}
			}
		}
		foreach($child as $level => $a){
			if(empty($parent[$level])){
				//if the parent doesn't even have this level
				$parent[$level] = $a;
			}
		}
	}
}