<?php


namespace App\Common;


use App\Common\SQL\Factory;

/**
 * Class ExceptionHandler
 *
 * Handles exceptions for the sanctions lists.
 * Will need to be re-written to work with the Exceptions class.
 *
 * @package App\Common
 */
class ExceptionHandler {
	/**
	 * Given the exception object,
	 * returns the type, formatted.
	 * <code>
	 * $type = ExceptionHandler::getType($e);
	 * </code>
	 *
	 * @param object $e
	 *
	 * @return string
	 */
	public static function getType (object $e): string
	{
		$type = end(explode("\\", get_class($e)));
		$type = trim(preg_replace("/([A-Z])/", " $1", $type));
		return $type;
	}

	/**
	 * Given the exception object,
	 * returns the request that was requested.
	 * <code>
	 * $request = ExceptionHandler::getRequest($e);
	 * </code>
	 * <code>
	 * GET /notices/v1/red?arrestWarrantCountryId=AU&resultPerPage=160&page=1 HTTP/1.1
	 * User-Agent: GuzzleHttp/6.5.3 curl/7.64.0 PHP/7.2.24-0ubuntu0.19.04.2
	 * Host: ws-public.interpol.int
	 * </code>
	 *
	 *
	 * @param object $e
	 *
	 * @return string
	 */
	public static function getRequest (object $e): string
	{
		if(!method_exists($e, "getRequest")){
			return false;
		}
		$request = \GuzzleHttp\Psr7\str($e->getRequest());
		return $request;
	}

	/**
	 * Given the exception object,
	 * if there is a response from the server,
	 * return it.
	 *
	 * <code>
	 * HTTP/1.1 504 Gateway Time-out
	 * Content-Type: text/html
	 * Content-Length: 160
	 * Expires: Sun, 07 Jun 2020 08:55:03 GMT
	 * Cache-Control: max-age=0, no-cache, no-store
	 * Pragma: no-cache
	 * Date: Sun, 07 Jun 2020 08:55:03 GMT
	 * Connection: keep-alive
	 *
	 * <html>
	 * <head><title>504 Gateway Time-out</title></head>
	 * <body>
	 * <center><h1>504 Gateway Time-out</h1></center>
	 * <hr><center>nginx</center>
	 * </body>
	 * </html>
	 * </code>
	 *
	 * @param object $e
	 *
	 * @return bool|string
	 */
	public static function getResponse (object $e): string
	{
		if (method_exists($e, "hasResponse") && $e->hasResponse()) {
			return false;
		}

		if(!method_exists($e, "getResponse")){
			return false;
		}

		$log = Log::getInstance();

		$log->info($e->getResponse()->getBody()->getContents(), true);

		$response = \GuzzleHttp\Psr7\str($e->getResponse());
		return $response;
	}

	/**
	 * Given the exception object,
	 * finds the code, either given by the server,
	 * or by cURL if the request didn't get a response.
	 *
	 * @param object $e
	 *
	 * @return string
	 */
	public static function getCode (object $e): string
	{
		# Get server error code
		if($code = $e->getCode()){
			return $code;
		}

		# Get cURL error code
		if(method_exists($e, "getHandlerContext") && $handler_context = $e->getHandlerContext()) {
			if($code = $handler_context['errno']){
				return $code;
			}
		}

		# If all else fails
		return "";
	}

	/**
	 * Given the exception object,
	 * returns the exception message.
	 * May be superfluous if it's not expanded.
	 *
	 * @param object $e
	 *
	 * @return string
	 */
	public static function getMessage (object $e): string
	{
		$message = $e->getMessage();
		return $message;
	}

	/**
	 * Given the exception object,
	 * logs it in the api_error_log table.
	 *
	 * @param string $origin
	 * @param        $e
	 *
	 * @return bool
	 */
	public static function logException (string $origin, $e): bool
	{
		$sql = Factory::getInstance();
		$sql->insert([
			"table" => "api_error_log",
			"grow" => true,
			"set" => [
				"origin" => $origin,
				"catch" => self::getType($e),
				"file" => $e->getFile(),
				"line" => $e->getLine(),
				"trace" => $e->getTraceAsString(),
				"code" => self::getCode($e),
				"message" => self::getMessage($e),
				"request" => self::getRequest($e),
				"response" => self::getResponse($e)
			]
		]);
		return true;
	}

	/**
	 * Given an exception and its origin,
	 * will produce an error alert and display it to the user
	 * immediately thru WebSockets.
	 *
	 * @param string     $origin
	 * @param            $e
	 * @param array|null $alert
	 * @param mixed      $immediately
	 *
	 * @return bool
	 */
	public static function alertUser(string $origin, $e, ?array $alert = [], $immediately = NULL): bool
	{
		$log = Log::getInstance();

		# Get the kind of exception, based on the class name
		$type = ExceptionHandler::getType($e);

		# Get the request
		$request = ExceptionHandler::getRequest($e);

		# If there is a response get it
		$response = ExceptionHandler::getResponse($e);

		# If there is a code, get it
		$code = ExceptionHandler::getCode($e);

		# Get the message
		$error = ExceptionHandler::getMessage($e);

		$origin = str::title($origin);

		# The below values will overwrite existing values in the potential alert array
		$alert['title'] = "{$origin} {$type} [{$code}]";
//		$alert['message'] = str::pre($response);
		$alert['message'] = $error;

		# Type (default is error)
		$type = $alert['type'] ?: "error";

		$log->log($alert, $type, $immediately);

		if($immediately && str::runFromCLI()){
			//if this command is run from the command line (most probably as a cron job)
			$log->log($alert, $type);
			//this way, it will be logged in the cron job logs also
		}

		return true;
	}
}