<?php


namespace App\Common\Prototype;


use App\Common\Img;
use App\Common\Prototype;
use App\Common\SQL\mySQL\mySQL;

/**
 * Class PrintPrototype
 *
 * Should be used as the prototype for all classes that will produce
 * grid HTML pages to be printed.
 *
 * @package App\Common\Prototype
 */
abstract class PrintPrototype extends Prototype {
	/**
	 * Returns the first page header with the KYCDD logo to the left,
	 * and the document title to the right.
	 *
	 * The text to the right is set direction RTL, so any badges
	 * must be placed BEFORE the text, not after.
	 *
	 * @param string $title
	 *
	 * @return array
	 */
	protected static function getHeader(string $title): array
	{
		$grid[] = [[
			"html" => Img::generate([
				"src" => "https://{$_ENV['app_subdomain']}.{$_ENV['domain']}/img/kycdd_logo_v4_black.svg",
				"width" => "200",
				"height" => "60",
			]),
			"sm" => "auto"
		], [
			"html" => $title,
			"style" => [
				"font-size" => "24pt",
				"font-weight" => "600",
				"text-transform" => "uppercase",
				"border-bottom" => "3px solid black",
				"text-align" => "right",
				"white-space" => "nowrap",
				"overflow" => "hidden",
				"direction" => "rtl"
			],
		]];

		$grid[] = [[
			"html" => "KYC DD (Pty) Limited, Office 203, 139 Greenway, Greenside, Johannesburg, 2193<br>Incorporated in South Africa, 2020/181847/07",
			"style" => [
				"margin-top" => "1rem",
				"margin-bottom" => "3rem",
				"text-align" => "right",
				"font-size" => "8pt",
			],
		]];

		return $grid;
	}

	protected static function invoiceHeader(): array
	{
		return [
			"row_class" => "invoice-header",
			"html" => [[
				"html" => "Service",
				"sm" => 6,
			], [
				"html" => "Qty",
				"sm" => 1,
			], [
				"html" => "Rate",
				"sm" => 2,
			], [
				"html" => "Amount",
				"sm" => 3,
				"style" => [
					"text-align" => "right",
				],
			]],
		];
	}

	protected static function invoiceSummaryHeader(): array
	{
		return [
			"row_class" => "invoice-header",
			"html" => [[
				"html" => "Service",
				"sm" => 9,
			], [
				"html" => "Amount",
				"sm" => 3,
				"style" => [
					"text-align" => "right",
				],
			]],
		];
	}
}