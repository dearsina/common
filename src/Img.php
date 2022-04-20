<?php


namespace App\Common;


use App\Common\FastImage\FastImage;
use enshrined\svgSanitize\Sanitizer;

class Img {
	/**
	 * Given an array of image data, produces an svg or img tag.
	 * If only a string is passed, assumes it's an img.
	 *
	 * @param string|array $a
	 *
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

		if($a['contents']){
			return self::contents($a);
		}

		return false;
	}

	/**
	 * Given an image path, will return the src="" base64 encoded
	 * string.
	 *
	 * @param string $imagePath
	 *
	 * @return string
	 */
	public static function getDataURI(string $imagePath) {
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$type = $finfo->file($imagePath);
		return 'data:' . $type . ';base64,' . base64_encode(file_get_contents($imagePath));
	}

	/**
	 * Given a contents key with binary image data,
	 * and an optional mime string, will return an img tag
	 * with the image data in the tag as base64 string.
	 *
	 * @param $a
	 *
	 * @return string
	 */
	private static function contents($a): string
	{
		$mime = $a['mime'] ?: "image/jpeg";
		$base64 = base64_encode($a['contents']);
		$a['src'] = 'data:' . $mime . ';base64,' . $base64;
		return self::img($a);
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
		$alt = str::getAttrTag("alt", $alt, $alt_if_null);

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
		0 => 'UNKNOWN',
		1 => 'GIF',
		2 => 'JPEG',
		3 => 'PNG',
		4 => 'SWF',
		5 => 'PSD',
		6 => 'BMP',
		7 => 'TIFF_II',
		8 => 'TIFF_MM',
		9 => 'JPC',
		10 => 'JP2',
		11 => 'JPX',
		12 => 'JB2',
		13 => 'SWC',
		14 => 'IFF',
		15 => 'WBMP',
		16 => 'XBM',
		17 => 'ICO',
		18 => 'COUNT',
	];

	/**
	 * Expands on the PHP function getimagesize, making it useful for SVGs,
	 * and faster for larger image files.
	 *
	 * @param string|null $filename
	 * @param bool|null   $dimensions_only If set, will only return width and height.
	 *
	 * @return array|null
	 * @link https://stackoverflow.com/a/48994280/429071
	 *       https://github.com/tommoor/fastimage
	 */
	public static function getimagesize(?string $filename, ?bool $dimensions_only = NULL): ?array
	{
		if(!$filename){
			return NULL;
		}

		# SVGs
		if(str::isSvg($filename)){
			//Treat SVGs a little different
			$imagine = new \Contao\ImagineSvg\Imagine();
			$size = $imagine->open($filename)->getSize();

			# There is no guarantee we'll get these details, because not all SVGs have them
			return [
				"width" => $size->getWidth(),
				"height" => $size->getHeight(),
				"image_type" => "SVG",
			];

		}

		# Dimensions only (faster), will only return width/height
		else if($dimensions_only){
			# FastImage is a faster way of getting width/height of an image
			$image = new FastImage($filename);
			if(![$width, $height] = $image->getSize()){
				// If we cannot get details, pencils down
				return NULL;
			}

			return [
				"width" => $width,
				"height" => $height,
			];
		}

		# All image data (quite slow)
		else {
			if(!$a = getimagesize($filename)){
				// If we cannot get details, pencils down
				return NULL;
			}
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

	/**
	 * Sanitises an SVG, preventing its use as a potential attack vector.
	 * Can accept SVG XML strings, or base64-encoded strings.
	 *
	 * @param string|null $string
	 * @param bool|null   $isBase64
	 *
	 * @return string|null
	 * @link https://github.com/darylldoyle/svg-sanitizer
	 */
	public static function sanitiseSvg(?string $string, ?bool $isBase64 = NULL): ?string
	{
		if(!$string){
			return $string;
		}

		if($isBase64){
			if(!preg_match("/^data:image\/svg\+xml;base64,/", $string)){
				//if the string doesn't start with the base64 marker, reject it
				return NULL;
			}

			# Decode the base64 string, strip the prefix, replace spaces with plus
			$base64_str = str_replace('data:image/svg+xml;base64,', '', $string);
			$base64_str = str_replace(' ', '+', $base64_str);

			if(!$string = base64_decode($base64_str)){
				//if the string isn't pure base64
				return NULL;
			}
		}

		// Pass it to the sanitizer and get it back clean (and validate it)
		if(!$sanitised = (new Sanitizer())->sanitize($string)){
			//if nothing is returned, the string was not valid
			return NULL;
		}

		if($isBase64){
			//if the string was a base64 string, encode it back as such and return it

			# Encode the string back to base64
			$encoded = base64_encode($sanitised);
			$base64_str = str_replace('+', ' ', $encoded);

			# Return the string with the base64 prefix
			return 'data:image/svg+xml;base64,' . $base64_str;
		}

		# Otherwise just return the sanitised string
		return $sanitised;
	}
}