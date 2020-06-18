<?php


namespace App\Common\Navigation;

use App\Common\Common;
use App\UI\Icon;

/**
 * Class User
 * @package App\Common\Navigation
 */
class User extends Common implements NavigationInterface {
	private $levels = [];
	private $footers = [];

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