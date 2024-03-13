<?php

namespace App\Common\EmailWrapper;

/**
 * This generic EmailWrapper class contains instructions
 * about formatting emails, and some basic structural arrays.
 *
 * An App\EmailWrapper should be created and extend this method.
 */
class EmailWrapper extends \App\Common\Prototype {
	/**
	 * The default values when formatting an email.
	 * Also includes the default colour names
	 * and their default colours.
	 *
	 * @var array
	 */
	public static array $defaults = [
		"colour" => [
			"header_text" => "#000000",
			"header_background" => "#ffffff",
			"body_title" => "primary",

			"body_text" => "#666666",
			"button_text" => "#ffffff",
			"footer_text" => "#999999",

			"body_background" => "#f9fafb",
			"button_background" => "primary",
			"footer_background" => "#ffffff",
		],
		"logo_position" => "left",
		"logo_width" => 100,
		"font_family" => "Arial, Helvetica, sans-serif",
	];

	/**
	 * The various positions a header logo can be placed.
	 *
	 * @var array
	 */
	public static array $logo_position_options = [
		"left" => [
			"title" => "Left",
			"desc" => "To the left, with optional text to the right."
		],
		"right" => [
			"title" => "Right",
			"desc" => "To the right, with optional text to the left."
		],
		"centre" => [
			"title" => "Centre",
			"desc" => "Covering the entire header width."
		]
	];

	/**
	 * Web-safe fonts to choose from when formatting an email.
	 *
	 * @var array
	 */
	public static array $fonts = [
		"Arial, Helvetica, sans-serif" => "Arial",
		"Barlow, Helvetica, Arial, sans-serif" => "Barlow",
		"Calibri, Helvetica, Arial, sans-serif" => "Calibri",
		"Courier New, monospace" => "Courier New",
		"Georgia" => "Georgia",
		"Helvetica, Arial, sans-serif" => "Helvetica",
		"Lucida Sans Unicode, sans-serif" => "Lucida Sans Unicode",
		"Lucida Console, monospace" => "Lucida Console",
		"system-ui" => "System UI",
		"Tahoma" => "Tahoma",
		"Times New Roman, Roman, serif" => "Times New Roman",
		"Trebuchet MS, sans-serif" => "Trebuchet MS",
		"Verdana, sans-serif" => "Verdana",
	];

	/**
	 * A dummy sample email body with a button.
	 *
	 * @var array
	 */
	protected static array $dummy_body = [[
		'copy' => [
			'title' => [
				'align' => 'left',
				'title' => 'This is the email subject line and title',
			],
			'body' => [
				'body' => 'This is a sample email body.
				You can define and style it as you like,
				however only the font family is set here,
				everything else is done per template.',
				'align' => 'left',
			],
		],
		'button' => [
			'title' => 'Optional button',
			'url' => '#',
		],
	]];
}