<?php


namespace App\Common\Navigation;

use App\Common\Common;

/**
 * Class User
 * @package App\Common\Navigation
 */
class User extends Common implements NavigationInterface {
	private $levels = [];
	private $footers = [];

	public function update() : array
	{
		$this->levels[2]['title'] = "Level 2 title";
		return $this->levels;
	}

	public function footer() : array
	{
		return $this->footers;
	}
}