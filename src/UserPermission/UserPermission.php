<?php


namespace App\Common\UserPermission;


use App\Common\Permission\Permission;
use App\Common\str;
use App\UI\Icon;
use App\UI\Page;

/**
 * Class UserPermission
 * @package App\Common\UserPermission
 */
class UserPermission extends Permission {
	/**
	 * @return Card
	 */
	public function card() : object
	{
		return new Card();
	}

	/**
	 * @return Modal
	 */
	public function modal() : object
	{
		return new Modal();
	}

	/**
	 * @param $a
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function view($a) : bool
	{
		extract($a);

		if (!$this->user->is("admin")) {
			//Only admins have access
			return $this->accessDenied($a);
		}

		if($vars['user_id']){
			if(!$user = $this->info("user", $vars['user_id'])){
				throw new \Exception("Invalid user ID provided for permissions management.");
			}
			$page = new Page([
				"title" => "User permissions",
				"icon" => Icon::get("permission"),
				"subtitle" => "All the permissions belonging to {$user['name']}."
			]);
			$user_permissions = $this->card()->userPermission($user);
		} else {
			$page = new Page([
				"title" => str::title("User permissions"),
				"icon" => Icon::get("user"),
				"subtitle" => "Select a user to see their permissions."
			]);
		}

		# Update the URL depending on which user we're watching
		$this->hash->set($a);

		# Set to silent to avoid the whole page refreshing
		$this->hash->silent();

		$page->setGrid([[
			"sm" => 3,
			"html" => $this->card()->selectUser($user)
		],[
			"html" => $user_permissions
		]]);

		$this->output->html($page->getHTML());

		# Closes the (top-most) modal
		$this->output->closeModal();

		return true;
	}
}