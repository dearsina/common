<?php


namespace App\Common\Navigation;

use App\UI\Icon;

/**
 * Class User
 * @package App\Common\Navigation
 */
class User extends Prototype implements NavigationInterface {
	public function update() : array
	{
//		$this->levels[2]['title'] = "Optional title";
        $this->home();
        $this->companies();
		return $this->levels;
	}
    private function home() : void
    {
        $children[] = [
            "icon" => Icon::get("grid"),
            "title"=> "Dashboard",
            "alt"=>"Dashboard",
            "hash"=>[
                "rel_table"=>"client",
                "action"=>"../"
            ]
        ];
        $this->levels[2]['items'] [] = [
            "icon" => Icon::get("home"),
            "title"=> "Home",
            "alt"=>"Home",
//            "hash"=>[
//                "rel_table"=>"user",
//                "action"=>"/"
//            ],
            "children"=>$children,
        ];
    }
    private function companies() : void
    {
        $children[] = [
            "icon" => [
                "name" => Icon::get("plus"),
            ],
            "title" => "Search company",
            "alt" => "Search companies",
            "hash" => [
                "rel_table" => "client",
                "action" => "search",
            ],
        ];
        $children[] = [
            "icon" => [
                "name" => Icon::get("list"),
            ],
            "title" => "All companies",
            "alt" => "Manage companies",
            "hash" => [
                "rel_table" => "client",
                "action" => "list",
            ],
        ];
        $this->levels[2]['items'][] = [
            "icon" => Icon::get("building"),
            "title"=> "Companies",
            "alt"=>"Manage Companies",
//            "hash"=>[
//                "rel_table"=>"client",
//                "action"=>"new"
//            ],
            "children"=>$children,
        ];
    }

	public function footer() : array
	{
		return $this->footers;
	}
}