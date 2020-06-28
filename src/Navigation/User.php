<?php


namespace App\Common\Navigation;

/**
 * Class User
 * @package App\Common\Navigation
 */
class User extends \App\Common\Navigation\Common implements NavigationInterface {
	public function update() : array
	{
		$this->levels[2]['title'] = "Optional title";
		return $this->levels;
	}

	public function footer() : array
	{
		return $this->footers;
	}
}