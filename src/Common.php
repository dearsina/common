<?php


namespace App\Common;


class Common {
	/**
	 * @var Log
	 */
	protected $log;

	/**
	 * @var Output
	 */
	protected $output;

	/**
	 * @var str
	 */
	protected $str;


	function __construct () {
		$this->log = Log::getInstance();
		$this->output = Output::getInstance();
	}
}