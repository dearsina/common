<?php


namespace App\Common\SQL\Info;


/**
 * Interface InfoInterface
 * @package App\Common\SQL\Info
 */
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
	public static function prepare(array &$a, ?array $joins) : void;

	/**
	 * Format a single result row.
	 * 
	 * @param array $row
	 */
	public static function format(array &$row) : void;
}