<?php
namespace App\Common;


/**
 * A set of static methods related to commonly used methods.
 *
 * @package App\Common
 */
trait style {

	static function is_hex_colour($colour){
		if(!is_string($colour)){
			return false;
		}
		if(preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $colour)){
			return true;
		}
		return false;
	}

	/**
	 * Get the style of button
	 * @param $a
	 *
	 * @return mixed
	 */
	static function get_style($a){
		extract($a);

		if($style){
			return "style=\"{$style}\"";
		}

		return false;
	}

	/**
	 * Takes a colour and prefixes it with "text-",
	 * to get a class string to use as a colour.
	 * The colour does not have to be translated prior.
	 * Alternatively, it will return nothing, if no colour match can be found.
	 *
	 * @param        $colour
	 * @param string $prefix Default "text", can be anything, "btn", etc.
	 *
	 * @return bool|string
	 */
	static function get_colour($colour, $prefix = 'text'){
		if(!$colour){
			return false;
		}
		if($translated_colour = str::translate_colour($colour)){
			return "{$prefix}-{$translated_colour}";
		} else {
			return false;
			//The default is no colour (black)
		}
	}

	/**
	 * Translates "real" colour names to Bootstrap 4 colour names.
	 * If a "real" colour name cannot be found, FALSE is returned.
	 * @param $colour
	 *
	 * @return bool|string
	 */
	static function translate_colour($colour){
		switch($colour){
		case 'custom'   : return 'custom'; break;
		case 'primary'  : return 'primary'; break;
		case 'blue'     : return 'blue'; break;
		case 'secondary': return 'secondary'; break;
		case 'grey'     : return 'secondary'; break;
		case 'gray'     : return 'secondary'; break;
		case 'green'    : return 'success'; break;
		case 'success'  : return 'success'; break;
		case 'warning'  : return 'warning'; break;
		case 'yellow'   : return 'warning'; break;
		case 'orange'   : return 'warning'; break;
		case 'danger'   : return 'danger'; break;
		case 'red'      : return 'danger'; break;
		case 'info'     : return 'info'; break;
		case 'light'    : return 'light'; break;
		case 'white'    : return 'light'; break;
		case 'dark'     : return 'dark'; break;
		case 'black'    : return 'dark'; break;
		case 'muted'    : return 'muted'; break;
		case 'link'     : return 'link'; break;
			/**
			 * Link isn't strictly speaking a colour,
			 * but is treated like a colour for button
			 * generation.
			 */
		default: return false; break;
		}
	}

	/**
	 * The approval box colours are limited.
	 *
	 * @param $colour
	 *
	 * @return bool|string
	 */
	static function translate_approve_colour($colour){
		switch($colour){
		case 'primary'  : return 'blue'; break;
		case 'blue'     : return 'blue'; break;
		case 'info'     : return 'blue'; break;
		case 'green'    : return 'green'; break;
		case 'success'  : return 'green'; break;
		case 'warning'  : return 'orange'; break;
		case 'yellow'   : return 'orange'; break;
		case 'orange'   : return 'orange'; break;
		case 'danger'   : return 'red'; break;
		case 'red'      : return 'red'; break;
		case 'purple'   : return 'purple '; break;
		case 'dark'     : return 'dark'; break;
		case 'black'    : return 'dark'; break;
		default: return false; break;
		}
	}

	/**
	 * If "approve" => "string" is included in a button config,
	 * create an approval modal based on the button config.
	 *
	 * @param $a string|array Can either be a simple sentence fragment
	 *           describing the action wanted or an array of settings,
	 *           including icons, colour, text, etc.
	 *
	 *
	 * @return bool|string
	 */
	static function get_approve_script($a){
		extract($a);

		if(!$approve){
			//If no approve modal is required
			return false;
		}

		if(is_bool($approve)){
			$message = str::title("Are you sure you want to do this?");
		} else if(is_string($approve)){
			//if just the name of the thing to be removed is given
			$message = str::title("Are you sure you want to {$approve}?");
		} else {
			extract($a['approve']);
			//can override icon/class/etc options
		}

		if(!$title){
			$title = "Are you sure?";
		}
		if(substr($title,-3) == '...'){
			//remove potential ellipsis
			$title = substr($title,0,-3);
		}

		if(is_array($hash)){
			$hash = str::generate_uri($hash, false);
			//Not sure why this was set to true, but could have a real reason
		}

		if($remove && $hash){
			$hash .= substr($hash,-1) == "/" ? "" : "/";
			$hash .=  "div/{$id}";
			$confirm = "$(\"#{$id}\").{$remove}.remove();".$confirm;
			$confirm = "window.location.hash = '#{$hash}';".$confirm;
		} else if($div && $hash){
			$hash .= substr($hash,-1) == "/" ? "" : "/";
			$hash .=  "div/{$div}";
			$confirm = "hashChange('{$hash}');";
		} else if($onclick||$onClick){
			$confirm = $onclick.$onClick;
		} else if ($hash){
			if($hash == -1){
				//only -1 works in a button context
				$confirm = "window.history.back();";
			} else {
				$confirm = "window.location.hash = '#{$hash}';";
			}
		} else if ($url) {
			$confirm = "window.location = '{$url}';";
		} else if ($type=="submit"){
			//if this is confirming a form submission
			$confirm = "$('#{$id}').closest('form').submit();
			let l = Ladda.create( document.querySelector( '#{$id}' ) );
			l.start();";
			//submit the form

		}

		$icon_class = Icon::get_class($icon);
		$type = self::translate_approve_colour($colour);
		$button_colour = self::get_colour($colour, "btn");

		$message = str_replace(["\r\n","\r","\n"], " ", $message);

		return /** @lang HTML */<<<EOF
<script>
$('#{$id}').on('click', function (event) {
    event.stopImmediatePropagation();
	event.preventDefault();
	$.confirm({
		animateFromElement: false,
		escapeKey: true,
		backgroundDismiss: true,
		closeIcon: true,
		type: "{$type}",
		theme: "modern",
		icon: "{$icon_class}",
		title: "{$title}",
		content: "{$message}",
		buttons: {
			confirm: {
				text: "Yes", // text for button
				btnClass: "{$button_colour}", // class for the button
				keys: ["enter"], // keyboard event for button
				action: function(){
					{$confirm}
				}
			},
			cancel: function () {
				//Close
			},
		}
	});
});
</script>
EOF;


	}
}