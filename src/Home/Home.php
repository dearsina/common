<?php


namespace App\Common\Home;


use App\Common\Common;

class Home extends Common {
	public function view($a){
		extract($a);

		if(!$this->user->isLoggedIn()){
			$this->hash->set([
				"rel_table" => "user",
				"action" => "login"
			]);
			return false;
		}


	}
}