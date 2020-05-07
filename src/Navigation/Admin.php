<?php


namespace App\Common\Navigation;


class Admin extends \App\Common\Common implements NavigationInterface {
	private $levels = [];
	private $footers = [];

	/**
	 * @inheritDoc
	 */
	public function update (): array
	{
		return $this->levels;
	}

	public function footer() : array
	{
		return $this->footers;
	}
}