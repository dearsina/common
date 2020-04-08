<?php


namespace App\Common;

/**
 * Static class to generate buttons
 * <code>
 * Button::generate($a);
 * </code>
 * @package App\Common
 */
class Button {

	/**
	 * Generic buttons that can be referenced by name only.
	 * Buttons can be localised by including "rel_table" or "rel_id".
	 *
	 */
	const GENERIC = [
		"save" => [
			"colour" => "green",
			"icon" => [
				"name" => "save",
				"type" => "light"
			],
			"title" => "Save",
			"type" => "submit",
		],
		"next" => [
			"colour" => "primary",
			"icon" => [
				"name" => "save",
				"type" => "light"
			],
			"title" => "Next",
			"type" => "submit",
		],
		"update" => [
			"colour" => "green",
			"icon" => [
				"name" => "save",
				"type" => "light"
			],
			"title" => "Update",
			"type" => "submit",
		],
		"update_md" => [
			"colour" => "blue",
			"icon" => [
				"name" => "save",
				"type" => "light"
			],
			"title" => "Update",
			"onClick" => "$(this).closest('form').submit();"
		],
		"return" => [
			"hash" => -1,
			"icon" => "chevron-left",
			"title" => "Return",
			"class" => "reset",
			"basic" => true,
			"style" => "margin-top:1rem;"
		],
		"cancel" => [
			"onClick" => "window.history.back();",
			"title" => "Cancel",
			"colour" => "grey",
			"basic" => true
		],
		"cancel_md" => [
			"title" => "Cancel",
			"colour" => "grey",
			"basic" => true,
			"data" => [
				"dismiss" => "modal"
			],
			"class" => "float-right"
		],
		"close_md" => [
			"title" => "Close",
			"colour" => "grey",
			"basic" => true,
			"data" => [
				"dismiss" => "modal"
			]
		],
		"remove_md" => [
			"alt" => "Remove rel_table",
			"basic" => true,
			"colour" => "red",
			"icon" => "trash",
			"approve" => "remove this rel_table",
			"hash" => "rel_table/rel_id/remove/callback/",
			"class" => "float-right",
			"data" => [
				"dismiss" => "modal"
			]
		],
		"match" => [
			"alt" => "In sync",
			"colour" => "green",
			"icon" => "check",
			"disabled" => true,
			"style" => "float:right;margin-top:0;",
			"class" => "btn-sm"
		],
		"view_removed" => [
			"title" => "View removed",
			"icon" => "trash-alt",
			"hash" => "rel_table//removed",
		],
		"remove" => [
			"title" => "Remove rel_table...",
			"alt" => "Remove rel_table",
			"basic" => true,
			"colour" => "red",
			"icon" => "trash",
			"approve" => "remove this rel_table",
			"hash" => "rel_table/rel_id/remove/callback/",
			"class" => "btn-sm",
			"remove" => 'closest(".container")'
		],
		"remove_all" => [
			"title" => "Remove all...",
			"alt" => "Remove all instances of rel_table",
			"basic" => true,
			"colour" => "red",
			"icon" => "trash",
			"approve" => "empty the rel_table table",
			"hash" => "rel_table//remove_all/callback/",
			"class" => "btn-sm",
		],
		"remove_sm" => [
			"alt" => "Remove rel_table",
			"basic" => true,
			"colour" => "red",
			"icon" => "trash",
			"approve" => "remove this rel_table",
			"hash" => "rel_table/rel_id/remove/callback/",
			"class" => "btn-sm",
			"remove" => 'closest(".container")'
		],
		"new" => [
			"icon" => "plus",
			"colour" => "blue",
			"title" => "New rel_table...",
			"hash" => "rel_table//new"
		],
		"edit" => [
			"icon" => "pencil",
			"title" => "Edit rel_table...",
			"hash" => "rel_table/rel_id/edit"
		],
		"edit_sm" => [
			"icon" => "pencil",
			"basic" => true,
			"alt" => "Edit rel_table",
			"hash" => "rel_table/rel_id/edit",
			"class" => "btn-sm",
		]
	];

	/**
	 * Localises a button if rel_table/id has been included in the call to the button generator.
	 *
	 * @param      $a
	 * @param bool|string $rel_table
	 * @param bool|string $rel_id
	 * @param bool|string $callback
	 *
	 * @return bool
	 */
	static function localise(&$a, $rel_table = false, $rel_id = false, $callback = false){
		if(!$rel_table) {
			return true;
		}

		foreach($a as $key => $val){
			if(is_array($val)){
				continue;
			}

			$a[$key] = $val;
			$a[$key] = str_replace("rel_table",	 $rel_table, $a[$key]);
			$a[$key] = str_replace("rel_id", $rel_id, $a[$key]);
			if($callback){
				$callback = strpos($callback,'/') !== false ? urlencode($callback) : $callback;
				$a[$key] = str_replace("callback/",	"callback/".$callback, 	$a[$key]);
			} else {
				$a[$key] = str_replace("callback/",	"", $a[$key]);
			}

			if(in_array($key,["title","alt"])){
				$a[$key] = str::title($a[$key]);
			}
		}

		return true;
	}

	/**
	 * Checks to see the kind of vars submitted to the button generation method.
	 * Allows for wider use of the pre-made buttons by simply submitting
	 * name, rel_table, rel_id in an array, instead of writing out the whole button each time.
	 *
	 * @param string|array $a
	 * @param bool $rel_table
	 * @param bool $rel_id
	 *
	 * @return bool|array
	 */
	static function get_array($a, $rel_table = false, $rel_id = false, $callback = false){
		if(is_array($a) && $a[0]){
			return self::get_array($a[0],$a[1],$a[2],$a[3]);
		}

		if(!is_array($a)){
			//if the only thing passed is the name of a generic button
			if(!$a = Button::GENERIC[$a]){
				//if a generic version is not found
				return false;
			}
		}

		# Infuse with relevant locally relevant vars
		self::localise($a, $rel_table, $rel_id, $callback);

		return $a;
	}

	static function multi($a){
		if(!$a){
			return false;
		}

		if(is_array($a) && !str::is_numeric_array($a)){
			$buttons[] = $a;
		} else if(str::is_numeric_array($a)){
			$buttons = $a;
		} else if(is_array($a)){
			return Button::generate($a);
		} else if(is_string($a)){
			return $a;
		} else {
			return false;
		}

		foreach($buttons as $id => $button){
			if(is_array($button)){
				if($id){
//					$button['style'] .= "margin-left:.5rem;";
					//margin between multiple buttons
				}
				$html .= Button::generate($button);
			} else {
				$html .= $button;
			}
		}

		return $html;
	}

	/**
	 * Generates a button based on an array of settings
	 * <code>
	 * $html .= Button::generate([
	 * 	"hash" => "{$rel_table}/{$rel_id}",
	 * 	"basic" => true,
	 * 	"colour" => "grey",
	 * 	"icon" => "chevron-left",
	 * 	"title" => "Return",
	 * 	"subtitle" => "Go back",
	 * 	"alt" => "Text appears when hover",
	 * ]);
	 * </code>
	 * @param array|string $a Array of settings or name of generic button
	 * @param string|bool $rel_table Optional, if a generic button with localisation has been chosen.
	 * @param string|bool $rel_id  Optional, if a generic button with localisation has been chosen.
	 *
	 * @return string
	 */
	static function generate($a, $rel_table = false, $rel_id = false, $callback = false){
		if(!$a){
			//if no data is submitted to the method, ignore it
			return false;
		}

		$a = self::get_array($a, $rel_table, $rel_id, $callback);

		if(!$a['id']){
			$a['id'] = "button_".rand();
		}

		extract($a);

		# Who is directing the button?
		if($approve){
			//if an approval dialogue is to prepend the action
			$approve_script = Button::get_approve_script($a);
			$href = "href=\"#\"";
		} else {
			$href = href::generate($a);
		}

		# OnClicks aren't treated as true buttons, fix it
		if($onClick){
			$style .= "cursor:pointer;";
		}

		# Is it a basic button?
		if($basic || $outline){
			$outline = "-outline";
		}

		# What colour is the button?
		if($colour){
			$colour = self::translate_colour($colour);
		} else {
			$colour = "dark";
			//default is a b&w theme
		}

		# Does it have an icon?
		if($svg = svg::generate($svg, "
		height: 1rem;
		position: relative;
		top: .2rem;
		left: -0.2rem;")){
			$icon = false;
		} else if($icon){
			$icon = icon::generate($icon);
		}

		# Does it have an badge?
		if($badge){
			$badge = Badge::generate($badge);
		}

		# is it to be placed to the right?
		if($right){
			$right = 'float-right';
		}

		# What tag-type is it?
		if($tag_type){
			//a tag type can be forced
		} else if($type == 'file') {
			$tag_type = "input";
			$name = "name=\"{$name}\"";
			if($multiple){
				$multiple = "multiple";
			}
			# Prevent vars array to be sent as [Object object]
			foreach($data as $key => $val){
				if(is_array($val)){
					foreach($val as $sub_key => $sub_val){
						$flat_data["{$key}[{$sub_key}]"] = $sub_val;
					}
				} else {
					$flat_data[$key] = $val;
				}
			}
			$json_data = json_encode($flat_data);
			$script .= /**@lang JavaScript*/"
			$(function () {
				$('#{$id}').fileupload({
					url: 'ajax.php',
					formData: {$json_data},
					dataType: 'json',
					add: function (e, data) {
					    $('.spinner').show();
						data.submit();
					},
					done: function (e, data) {
					    connectionSuccess(data.result);
					}
				});
			});
			";
		} else if($name && $value) {
			//if the button has a value that needs to be collected
			$type = "submit";
			$name = "name=\"{$name}\"";
			$value = "value=\"{$value}\"";
			$tag_type = "button";
		} else if($type == 'submit') {
			//for most buttons, this is the type
			$tag_type = "button";
		} else if($onClick||$onclick) {
			$tag_type = 'a';
		} else {
			$tag_type = 'a';
		}

		# Is it disabled?
		if($disabled){
			$outline = "-outline";
			$disabled = "disabled=\"disabled\"";
			$tag_type = "button";
			$style .= "cursor: default;";
		}

		# Size
		if($size){
			switch($size){
			case 'xs' 	: $size = "sm"; break;
			case 'small': $size = "sm"; break;
			case 'large': $size = "lg"; break;
			}
			$class = "btn-{$size} {$class}";
		}

		# Class override
		if(is_string($only_class)){
			$class_string = $only_class;
		} else {
			$class_string = "btn btn{$outline}-{$colour} {$right} {$class}";
		}

		# Style override
		if($style === false){
			$style_string = "";
		} else if(is_string($only_style)){
			$style_string = $only_style;
		} else {
			$style_string = $style;
		}

		# Pulsating
		if($pulsating){
			list($wrapper_pre, $wrapper_post) = self::pulsating($pulsating);
		}

		# Script
		$script = str::script_tag($script);

		# Data attributes
		if(is_array($data)){
			foreach($data as $attr => $val){
				$data_attributes .= "data-{$attr}=\"$val\" ";
			}
		}

		if($ladda !== false && !$url){
			//if Ladda has not explicitly been set to false and
			//if this not a button-link to an external site
			$class_string .= " ladda-button";
			$span_pre = "<span class=\"ladda-label\">";
			$span_post = "</span>";
		}

		$html_title = $alt.$desc ? $alt.$desc : strip_tags($title);

		$button_html = /** @lang HTML */<<<EOF
{$wrapper_pre}<{$tag_type}
	id="{$id}"
	{$href}
	{$name}
	{$value}
	type="{$type}"
	class="{$class_string}"
	data-style="slide-left"
	style="{$style_string}"
	title="{$html_title}"
	{$disabled}
	{$multiple}
	{$data_attributes}
>	{$span_pre}
	{$icon}{$svg}
	{$title}
	{$sub_title}
	{$span_post}
	{$badge}
</{$tag_type}>{$wrapper_post}
{$script}
{$approve_script}
EOF;

		if($dropdown || is_array($children)){
			$child['ladda'] = false;
			foreach($children as $child){
//				$child_html .= "<li class=\"dropdown-item\">";
				$child['only_class'] = "dropdown-item";
				$child['ladda'] = false;
				$child_html .= Button::generate($child, true);
//				$child_html .= "</li>";
			}
			return /** @lang HTML */<<<EOF
			
<li class="dropdown-submenu">
	<a
		class="dropdown-item dropdown-toggle"
		href="#"		
	>
	{$icon}
	{$title}
	{$sub_title}
	</a>
	<div class="dropdown-menu">
		{$child_html}
	</div>
</li>
<script>
enableSubmenu();
</script>
EOF;
		}

		if($type == 'file'){
			$button_html = /** @lang HTML */<<<EOF
<{$tag_type}
	id="{$id}"
	{$href}
	{$name}
	{$value}
	type="{$type}"
	data-style="slide-left"
	style="display:none;"
	title="{$html_title}"
	{$disabled}
	{$multiple}>
<label for="{$id}" class="{$class_string} ladda-button" style="margin: -3px 0 0 0;">
	<span class="ladda-label">
		{$icon}{$svg}
		{$title}
		{$sub_title}
	</span>
</label>
{$script}
{$approve_script}
EOF;

		}

		return $button_html;
	}

	static function pulsating($a){
		if(!$a){
			return false;
		} else	if(is_bool($a)){
			$pulsating['colour'] = "black";
		} else if (is_string($a)){
			$pulsating['colour'] = $a;
		} else if (is_array($a)){
			$pulsating = $a;
		} else {
			return false;
		}

		# Class
		$class[] = "pulsating-{$pulsating['colour']}";
		$class[] = $pulsating['class'];
		$class = str::get_attr("class", $class);

		# Style
		$style = str::get_attr("style", $pulsating['style']);

		return ["<div{$class}{$style}>", "</div>"];
	}
}