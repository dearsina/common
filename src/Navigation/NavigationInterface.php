<?php


namespace App\Common\Navigation;

/**
 * Interface NavigationInterface
 *
 * Use this interface for when making navigation classes
 * for new roles.
 *
 * @package App\Common\Navigation
 */
interface NavigationInterface {
	/**
	 * Returns a $level array with navigation level items.
	 *
	 * @return array
	 */
	public function update() : array;

	/**
	 * Returns a $footers array with footer level items.
	 *
	 * @return array
	 */
	public function footer() : array;
}