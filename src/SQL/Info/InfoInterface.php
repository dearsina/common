<?php


namespace App\Common\SQL\Info;


interface InfoInterface {
	/**
	 * Add joins, order, column definitions, etc.
	 * to the $a array:
	 * <code>
	 * $a['join'][] = [
	 * 	"table" => "",
	 * 	"on" => "",
	 * ];
	 * </code>
	 *
	 * @param array $a
	 */
	public function prepare(array &$a) : void;

	/**
	 * Format a single result row.
	 * 
	 * @param array $row
	 */
	public static function format(array &$row) : void;
}