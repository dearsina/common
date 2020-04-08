<?php


namespace App\Common;


class Common {
	/**
	 * @var Log
	 */
	protected $log;

	/**
	 * @var str
	 */
	protected $str;


	function __construct () {
		$this->log = Log::getInstance();
	}
}