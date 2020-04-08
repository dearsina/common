<?php


namespace App\Common;

/**
 * Class Badge
 * Use:
 * <code>
 * Badge::generate($a);
 * </code>
 * @package App\Common
 */
class Badge {
	/**
	 * Generic badges that can be referenced by name only.
	 * Badges can be localised by including "rel_table" or "rel_id".
	 *
	 */
	const GENERIC = [
		"deleted" => [
			"title" => "DELETED",
			"colour" => "red"
		],
	];

	/**
	 * Generates a badge based on an array of settings.
	 * <code>
	 * $html .= Badge::generate([
	 * 	"hash" => "{$rel_table}/{$rel_id}",
	 * 	"colour" => "grey",
	 * 	"icon" => "chevron-left",
	 * 	"title" => "Return",
	 * 	"pill" => true,
	 * 	"alt" => "Text appears when hover",
	 * ]);
	 * </code>
	 *
	 * Multiple badges can be built at once.
	 * <code>
	 * Badge::generate([$badgeA, $badgeB, ..., $badgeN]);
	 * </code>
	 *
	 * @param $a array|string Array of settings or name of generic badge
	 *
	 * @return string
	 */
	static function generate($array_or_string = null){
		if(!$array_or_string){
			return false;
		}

		if(!is_array($array_or_string)){
			//if the only thing passed is the name of a generic button
			if(!$a = Badge::GENERIC[$array_or_string]) {
				//if a generic version is NOT found
				$a['title'] = strtoupper($array_or_string);
			}
		} else if (str::is_numeric_array($array_or_string)){
			//if there are more than one badge
			foreach($array_or_string as $badge){
				$badge_array[] = Badge::generate($badge);
			}
			return implode("&nbsp;",$badge_array);
		} else {
			$a = $array_or_string;
		}

		# Give it an ID
		$a['id'] = $a['id'] ?: str::id("badge");
		//placed here because IDs are used by the get_approve_script method

		extract($a);

		$id = str::get_attr("id", $id);

		# Is the badge a link?
		if($approve){
			//if an approval dialogue is to prepend the action
			$approve_script = Button::get_approve_script($a);
			$href = "href=\"#\"";
			$tag_type = "a";
		} else if($href = href::generate($a)){
			//if the badge is to be a link
			$tag_type = "a";
		} else {
			$tag_type = "div";
			$style .= "cursor:default;";
		}

		# Is there a tag override?
		if($tag){
			$tag_type = $tag;
		}

		# What colour is the badge?
		if(self::translate_colour($colour)){
			$colour = self::translate_colour($colour);
		} else if(self::is_hex_colour($colour)){
			$style .= "background-color:{$colour};";
		} else {
			$colour = "dark";
			//default is a b&w theme
		}

		if($icon = icon::generate($icon)){
			$icon .= " ";
			//for better spacing between icon and title
		}

		$class = str::get_attr("class", [
			"badge",
			$pill ? "badge-pill" : "", //pill shape
			"badge-{$colour}",
			$right ? "float-right" : "", //legacy shortcut
			"text-light",
			$class
		]);

		$style = str::get_attr("style", $style);
		$script = str::script_tag($script);

		$alt = $alt ? $alt : $desc;
		$title_attr = str::get_attr("title", strip_tags($alt ?: $title));

		return /** @lang HTML */<<<EOF
<{$tag_type}{$href}{$id}{$class}{$style}{$title_attr}>{$icon}{$title}</{$tag_type}>{$script}{$approve_script}
EOF;
	}
}