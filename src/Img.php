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
        $width = str::getAttrTag("width", $width);
        $height = str::getAttrTag("height", $height);
        $data = str::getDataAttr($data);

        return "<object data=\"{$svg}\"{$type}{$id}{$class}{$style}{$alt}{$data}{$width}{$height}></object>";
    }

	/**
	 * A complete image tag method.
	 *
	 * @param $a
	 *
	 * @return bool|string
	 */
    private static function img($a)
    {
        if(is_string($a)){
            $a = ["src" => $a];
        }

        if(!is_array($a)){
            return false;
        }

        extract($a);

        # ID
        $id = str::getAttrTag("id", $id);

        # SRC
		$src = str::getAttrTag("src", $src);
        
        # Class
        $class = str::getAttrTag("class", $class);
        
        # Style
        $style_array = str::getAttrArray($style, $default_style, $only_style);
        $style = str::getAttrTag("style", $style_array);
        
        # Alt (alt specifies text to be displayed if the image can't be displayed)
        $alt = str::getAttrTag("alt", $alt);

        # Title (the title attribute will provide a tooltip)
        $title = str::getAttrTag("title", $title);

        # Dimensions
		$width = str::getAttrTag("width", $width);
		$height = str::getAttrTag("height", $height);

		# Border
		$border = str::getAttrTag("height", $border);
        
        # Data
        $data = str::getDataAttr($data);

        return "<img{$id}{$type}{$src}{$class}{$style}{$width}{$height}{$alt}{$title}{$data}{$border}>";
    }

	/**
	 * A list of image types and their key.
	 *
	 * @var array
	 */
    private static array $images_types = [
		0=>'UNKNOWN',
		1=>'GIF',
		2=>'JPEG',
		3=>'PNG',
		4=>'SWF',
		5=>'PSD',
		6=>'BMP',
		7=>'TIFF_II',
		8=>'TIFF_MM',
		9=>'JPC',
		10=>'JP2',
		11=>'JPX',
		12=>'JB2',
		13=>'SWC',
		14=>'IFF',
		15=>'WBMP',
		16=>'XBM',
		17=>'ICO',
		18=>'COUNT'
	];

	/**
	 * The PHP getimagesize method on crack.
	 *
	 * @param string|null $filename
	 *
	 * @return array|null
	 */
	public static function getimagesize(?string $filename): ?array
	{
		if(!$filename){
			return NULL;
		}

		if(!$a = getimagesize($filename)){
			return NULL;
		}

		$size['width'] = $a[0];
		$size['height'] = $a[1];
		$size['image_type'] = self::$images_types[$a[2]];
		$size['width_height_string'] = $a[3];
		$size['mime'] = $a[4];
		$size['channels'] = $a[5];
		$size['bits'] = $a['6'];

		return $size;
    }
}