<?php


namespace App\Common;


class Img
{
    /**
     * Given an array of image data, produces an svg or img tag.
     * If only a string is passed, assumes it's an img.
     *
     * @param string|array $a
     * @return bool|string
     */
    public static function generate($a)
    {
        if(is_string($a)){
            $a = ["src" => $a];
        }

        if(!is_array($a)){
            return false;
        }

        if($a['svg']){
            return self::svg($a);
        }

        if($a['img']){
            return self::img($a);
        }

        if($a['src']){
            return self::img($a);
        }

        return false;
    }

    private static function svg($a)
    {
        if(is_string($a)){
            $a = ["svg" => $a];
        }

        if(!is_array($a)){
            return false;
        }

        extract($a);

        $id = str::getAttrTag("id", $id);
        $class = str::getAttrTag("class", $class);
        $style = str::getAttrTag("style", $style);
        $alt = str::getAttrTag("title", $alt);
        $data = str::getDataAttr($data);

        return "<object data=\"{$svg}\"{$type}{$id}{$class}{$style}{$alt}{$data}></object>";
    }

    private static function img($a)
    {
        if(is_string($a)){
            $a = ["src" => $a];
        }

        if(!is_array($a)){
            return false;
        }

        extract($a);

        $id = str::getAttrTag("id", $id);
        $class = str::getAttrTag("class", $class);
        $style = str::getAttrTag("style", $style);
        $alt = str::getAttrTag("title", $alt);
        $data = str::getDataAttr($data);

        return "<img src=\"{$src}\"{$type}{$id}{$class}{$style}{$alt}{$data}/>";
    }
}