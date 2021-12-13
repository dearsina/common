<?php

namespace App\Common\Prototype;

use App\Common\Prototype;
use App\Common\str;

abstract class ApiPrototype extends Prototype {
	static function setIcon(array &$data): void
	{
		switch(true) {
		case is_null($data['score']):
			//no score set because one of the parties was missing
			$data['icon'] = [
				"name" => "times",
				"colour" => "silent",
				"alt" => "Value was not compared as none was submitted",
			];
			break;
		case $data['score'] == 1:
			$data['icon'] = [
				"name" => "check",
				"colour" => "success",
				"alt" => "A perfect match",
			];
			break;
		case $data['score'] == 0:
			$data['icon'] = [
				"name" => "times",
				"colour" => "danger",
				"alt" => $data['desc'] ? "{$data['status']}\r\n{$data['desc']}" : "No match",
			];
			break;
		default:
			$data['icon'] = [
				"name" => "tilde",
				"colour" => "warning",
				"alt" => "A " . str::percent($data['score']) . " match",
			];
			break;
		}
	}
}