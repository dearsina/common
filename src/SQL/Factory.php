<?php


namespace App\Common\SQL;

use App\Common\SQL\mySQL\mySQL;

/**
 * Class Factory
 *
 * The SQL factory allows for easy switching
 * between different SQL flavours.
 *
 * @package App\Common\SQL
 */
class Factory {
	/**
	 * @param string $type
	 *
	 * @return mySQL
	 */
	public static function getInstance($type = "mySQL"){
		switch(strtolower($type)){
		case 'mysql':
		default: return mySQL::getInstance();
		}
	}
}