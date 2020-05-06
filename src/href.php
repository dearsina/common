<?php


namespace App\Common;

/**
 * Generates the href (and optionally the onClick) attribute and value for the a tag.
 *
 * Class href
 * @package app\common
 */
class href {
	/**
	 * Distinguishes between onClick, hash or an URL.
	 * Returns HTML to put in a button or an a-tag.
	 *
	 * @param $a array
	 *
	 * @return string|bool	Will return <code>href="somevalue"</code> and at times also <code>onClick="somevalue".</code>
	 * 						If it cannot make sense out of the href, it will return FALSE
	 */
	static function generate($a){
		if(!$a){
			return false;
		}

		extract($a);

		if($onclick){
			$onClick = $onclick;
		}

		if($uri){
			$url = $uri;
		}

		if(is_array($hash)){
			$hash = str::generate_uri($hash);
		}

		# onClick and hash cannot both be present.
		# onClick needs to take over.
		# However -1 hash and hash+div will overwrite onCLick
		if($div && $hash){
			$href = "#";
			$hash .= substr($hash,-1) == "/" ? "" : "/";
			$hash .=  "div/{$div}";
			$onClick = "hashChange('$hash');";
		} else if($hash == -1){
			$href = "#";
			$onClick = "window.history.back();";
		} else if($hash){
			$href = "/{$hash}";
			/**
			 * Hashes do NOT have a slash prefixed.
			 * This needs to be added if the hash is to be
			 * used in a URL.
			 */
		}

		if($remove){
			$onClick .= "$('#{$id}').{$remove}.remove();";
		}

		# URL will overwrite hash
		if($url){
			if(is_array($url)){
				foreach($url as $attr => $val){
					$return[] = str::getAttrTag($attr, $val);
				}
			} else {
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
			return false;
		}

		return implode(" ", $return);
	}
}