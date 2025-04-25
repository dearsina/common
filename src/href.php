<?php


namespace App\Common;

use App\Translation\Translator;

/**
 * Generates the href (and optionally the onClick) attribute and value for the a tag.
 *
 * Class href
 * @package App\common
 */
class href {
	/**
	 * A complete `<a href=url>html</a>` tag.
	 *
	 * Given an array, returns the completed tag.
	 *
	 * @param array $a
	 *
	 * @return string
	 */
	static function a(?array $a): ?string
	{
		if(!$a){
			return NULL;
		}

		if(class_exists("App\\Translation\\Translator")){
			Translator::set($a, [
				"subscription_id" => $a['subscription_id'],
				"rel_table" => "href",
				"to_language_id" => $a['language_id'],
				"parent_rel_id" => $a['parent_rel_id']
			]);
		}

		extract($a);

		# ID (optional)
		$id = str::getAttrTag("id", $id);

		# Class
		$class = str::getAttrTag("class", $class);

		# Style
		$style_array = str::getAttrArray($style, $default_style, $only_style);
		$style = str::getAttrTag("style", $style_array);

		# Target
		$target = str::getAttrTag("target", $target);

		# Alt
		$alt = str::getAttrTag("title", $alt);

		$href = href::generate($a);

		return "{$pre}<a{$id}{$class}{$style}{$href}{$target}{$alt}>{$html}</a>{$post}";
	}

	/**
	 * Distinguishes between onClick, hash or an URL.
	 * Returns HTML to put in a button or an a-tag.
	 *
	 * @param $a array
	 *
	 * @return string|null    Will return <code>href="somevalue"</code> and at times also
	 *                        <code>onClick="somevalue".</code> If it cannot make sense out of the href, it will return
	 *                        FALSE
	 */
	static function generate($a): ?string
	{
		if(!$a){
			return NULL;
		}

		extract($a);

		# Ensure the capitalisation is done in a uniform manner
		$onClick = $onclick ?: $onClick;

		# Treat URI and URL the same
		$url = $uri ?: $url;

		if(is_array($hash)){
			$hash = str::generate_uri($hash);
		}

		/**
		 * onClick and hash cannot both be present.
		 * onClick needs to take over.
		 * However -1 hash and hash+div will overwrite onCLick
		 */
		if($div && $hash){
			$href = "#";
			$hash .= substr($hash, -1) == "/" ? "" : "/";
			$hash .= "div/{$div}";
			$onClick = "hashChange('$hash');";
		}

		else if($hash == -1){
			$href = "#";
			$onClick = "window.history.back();";
		}

		else if($hash){
			$href = "/{$hash}";
			/**
			 * Hashes do NOT have a slash prefixed.
			 * This needs to be added if the hash is to be
			 * used in a URL.
			 */
		}

		# URL will overwrite hash
		if($url){
			if(is_array($url)){
				foreach($url as $attr => $val){
					$return[] = str::getAttrTag($attr, $val);
				}
			}
			else {
				$return[] = str::getAttrTag("href", $url);
			}
		}

		if($href){
			$return[] = str::getAttrTag("href", $href);
		}

		if($onClick){
			$return[] = str::getAttrTag("onClick", $onClick);
		}

		if(!$return){
			return NULL;
		}

		return implode(" ", $return);
	}
}