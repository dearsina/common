<?php


namespace App\Common\Example;


/**
 * Class Common
 * @package App\Common\Example
 */
class Common extends \App\Common\Common {
	public $colours = [
		"primary",
		"secondary",

		"success",
		"warning",
		"danger",
		"info",

		"navy",
		"blue",
		"aqua",
		"teal",
		"olive",
		"green",
		"lime",
		"yellow",
		"orange",
		"red",
		"maroon",
		"fuchsia",
		"purple",

		"black",
		"gray",
		"silver",
	];

	/**
	 * @return string
	 */
	public function getRandomColour(){
		return $this->colours[rand(0,count($this->colours)-1)];
	}
}