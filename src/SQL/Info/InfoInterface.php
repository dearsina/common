<?php


namespace App\Common\SQL\Info;


interface InfoInterface {
	/**
	 * Add joins, order, column definitions, etc.
	 *
	 * @param array $a
	 */
	public function prepare(array &$a) : void;

	/**
	 * Format a single result row.
	 * 
	 * @param array $row
	 */
	public function format(array &$row) : void;
}