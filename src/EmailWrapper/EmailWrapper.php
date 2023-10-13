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
	 *
	 * @var array
	 */
	public static array $defaults = [
		"header_background_colour" => "#FFFFFF",
		"header_text_colour" => "#000000",
		"background_colour" => "#f9fafb",
		"text_colour" => "#666666",
		"title_colour" => "primary",
		"button_colour" => "primary",
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
	 * The various colours that can be set in an email.
	 *
	 * @var array
	 */
	public static array $colours = [
		"header_background_colour" => [
			"title" => "Header background colour",
		],
		"header_text_colour" => [
			"title" => "Header text colour",
		],
		"background_colour" => [
			"title" => "Body background colour",
		],
		"text_colour" => [
			"title" => "Body text colour",
		],
		"title_colour" => [
			"title" => "Body title colour",
		],
		"button_colour" => [
			"title" => "Button background colour",
		],
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