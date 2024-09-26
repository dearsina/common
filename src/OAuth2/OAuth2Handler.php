<?php

namespace App\Common\OAuth2;

use App\Common\Exception\BadRequest;
use App\Common\Log;
use App\Common\Process;
use App\Common\Request;
use App\Common\SQL\Factory;
use App\Common\str;
use App\SubscriptionEmail\SubscriptionEmail;
use App\Webhook\Webhook;
use League\OAuth2\Client\Grant\RefreshToken;

/**
 * This class is sort of a junction for all things OAuth2.
 *
 */
class OAuth2Handler extends \App\Common\Prototype {
	/**
	 * The method that is called to start the process
	 * of getting an OAuth2 token. This method will
	 * open a new window where the token is granted
	 * and stored, and subsequently closed.
	 *
	 * @param array $a
	 */
	public function initiateRequest(array $a): void
	{
		# Log the requester
		$a['requester'] = Process::getGlobalVariables(false);
		/**
		 * As we are (potentially) leaving the
		 * current subdomain, no global variables
		 * will be remembered. Thus, we save them
		 * so that we when re return we can
		 * continue as normal.
		 */

		$this->output->function("openNewWindow", [
			"url" => "https://app.{$_ENV['domain']}/oauth2.php?" . http_build_query(array_filter($a)),
			"id" => session_id(),
			"width" => 500,
			"height" => 700,
		]);
		// oauth2.php just calls the processRequest() method below
	}

	/**
	 * Closes the OAuth2 popup window.
	 * Is called once OAuth2 permission are granted,
	 * and the OAuth2 permissions window can be
	 * safely closed.
	 *
	 * @param string|null $session_id
	 * @param array|null  $recipients
	 */
	public function closeWindow(?string $session_id = NULL, ?array $recipients = NULL): void
	{
		if(!$session_id){
			global $session_id;
			/**
			 * If no session ID has been passed, use the one
			 * that was loaded into the Request class on
			 * creation. Thus, the "parent" div who created
			 * the window will be given the instruction
			 * to close the child popup window.
			 */
		}

		if(!$session_id){
			// If there is still no session ID, go nuclear and close all windows
			$this->output->function("closeAllWindows", NULL, $recipients);
			return;
		}

		$this->output->function("closeWindow", [
			"id" => $session_id,
		], $recipients);
	}

	/**
	 * The first step of any OAuth2 process, generate and go
	 * to the auth URL where we will get an auto code once
	 * the client has given us permission to access their
	 * stuff.
	 *
	 * @param string $provider
	 */
	private function goToTheAuthUrl(string $provider): void
	{
		# Get the provider object
		$provider = $provider::getOAuth2ProviderObject();

		# Get an auth URL from the provider
		$authorizationUrl = $provider->getAuthorizationUrl();

		# Get the OAuth2 state to compare once redirected back
		$_SESSION['OAuth2']['OAuth2State'] = $provider->getState();

		# Go to the auth URL
		header('Location: ' . $authorizationUrl);
		//Will push us to a different location

		exit;
	}

	/**
	 * This method is only accessed from the oauth2.php file.
	 * It will be accessed twice:
	 *
	 * 1. When the popup window has first been created
	 * 2. When we are sent back to it as the redirect URI.
	 *
	 * @throws BadRequest
	 */
	public function processRequest(): void
	{
		# 1. If we're setting up the request to get an auth code

		if($provider = OAuth2Handler::getProviderClass($_GET['vars']['provider'])){
			// Only at the start will be sent a get provider key

			# Store the $_GET
			$_SESSION['OAuth2'] = $_GET;
			/**
			 * We're storing the entire GET array as a session variable
			 * so that we can pick it up again once we are returned to
			 * this class from the redirect URI.
			 */

			$this->goToTheAuthUrl($provider);
		}

		# 2. Redirect URI brought us here ($_GET didn't have a vars-provider key)

		# Store the session key acting as our faux $a as a local variable
		if(!$a = $_SESSION['OAuth2']){
			//if there is no session variable stored (because the session has expired)

			# Force close the window
			echo "<script>window.close();</script>";exit;
		}

		# Clear it for future use
		unset($_SESSION['OAuth2']);

		# Extract
		extract($a);

		# Handle errors
		if($_GET['error']){
			$this->handleRedirectErrors($requester);
			return;
		}

		# Handle state errors
		if(!$_GET['state'] || ($_GET['state'] !== $OAuth2State)){
			$this->handleStateErrors($requester, $OAuth2State);
			return;
		}

		# Set the access token, return an ID
		$a['vars']['oauth_token_id'] = $this->setAccessToken($vars['provider']);

		# Continue as normal with the rest of the request
		$request = new Request($requester);
		$request->handler($a);
	}

	/**
	 * Triggered if state is invalid,
	 * and a possible CSRF attack is in progress.
	 * Highly unlikely and very rare.
	 *
	 * @param array  $requester
	 * @param string $OAuth2State
	 */
	private function handleStateErrors(array $requester, string $OAuth2State): void
	{
		$this->log->error([
			"title" => "OAuth2 invalid state",
			"message" => "Was expecting {$OAuth2State}, but got {$_GET['state']}.",
		], $requester);
	}

	/**
	 * The requester array contains information so that the errors
	 * and instructions go *back* to the modal (session) that called
	 * the OAuth2 process.
	 *
	 * @param array $requester
	 */
	private function handleRedirectErrors(array $requester): void
	{
		// If there was an error with our auth request
		switch($_GET['error']) {
		case 'access_denied':
		case 'consent_required':
			# The user did not grant the access requested
			$this->log->error([
				"title" => "Access not granted",
				"message" => "The necessary access permissions were not granted to create the connection.",
			], $requester);

			# Close the popup window
			$this->closeWindow($requester['session_id'], $requester);
			break;
		default:
			$this->log->error([
				"title" => "OAuth2 error",
				"message" => htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'),
			], $requester);
		}
	}

	/**
	 * Stores the access (and refresh) token
	 * received based on the auth code.
	 *
	 * @param string $provider
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function setAccessToken(string $provider): string
	{
		# Get the relevant provider (class)
		$provider_class = OAuth2Handler::getProviderClass($provider);
		/**
		 * We don't have a get-provider key,
		 * but we did store the provider
		 * name as a session variable.
		 */
		$provider_obj = $provider_class::getOAuth2ProviderObject(true);

		// Try to get an access token (using the authorization code grant)
		$token = $provider_obj->getAccessToken('authorization_code', [
			'code' => $_GET['code'],
		]);

		return $this->sql->insert([
			"table" => "oauth_token",
			"set" => [
				"provider" => $provider,
				"scope" => $_GET['scope'],
				"token" => $token->getToken(),
				"expires" => $token->getExpires(),
				"refresh_token" => $token->getRefreshToken(),
			],
		]);
	}

	/**
	 * Will return the relevant provider class.
	 *
	 * @param string|null $provider
	 *
	 * @return string|null
	 * @throws BadRequest
	 */
	public static function getProviderClass(?string $provider): ?string
	{
		if(!$provider){
			return NULL;
		}

		$class = str::getClassCase($provider);
		$class = str::getClassCase("\\App\\Common\\OAuth2\\Provider\\{$class}");

		if(!class_exists($class)){
			throw new BadRequest("Cannot find the {$class} OAuth2 provider.");
		}

		return $class;
	}

	/**
	 * Will return the relevant provider object, fed with the OAuth2 token.
	 *
	 * @param string $provider
	 * @param array  $oauth_token
	 *
	 * @return object|mixed
	 * @throws BadRequest
	 */
	public static function getProviderObject(string $provider, array $oauth_token): object
	{
		if(!$class = self::getProviderClass($provider)){
			throw new \Exception("Could not find the provider class for {$provider}.");
		}
		return new $class($oauth_token);
	}

	/**
	 * Will return the relevant single sign on provider object, fed with the OAuth2 token.
	 *
	 * @param string $provider
	 * @param array  $oauth_token
	 *
	 * @return object|mixed
	 * @throws BadRequest
	 */
	public static function getSingleSignOnProviderObject(string $provider, array $oauth_token): SingleSignOnProviderInterface
	{
		if(!$class = self::getProviderClass($provider)){
			throw new \Exception("Could not find the provider class for {$provider}.");
		}
		return new $class($oauth_token);
	}

	/**
	 * Assuming the OAuth token has a refresh token, ensures
	 * the token is fresh, otherwise, gets a new token using
	 * the refresh token.
	 *
	 * @param array $oauth_token
	 *
	 * @return bool
	 * @throws BadRequest
	 */
	public static function ensureTokenIsFresh(array &$oauth_token): bool
	{
		# If the token has yet to expire, we're good
		if($oauth_token['expires'] > strtotime("now")){
			return true;
		}

		# Ensure we have a refresh token in case the token has expired
		if(!$oauth_token['refresh_token']){
			return true;
		}

		# Get the provider (class)
		$class = OAuth2Handler::getProviderClass($oauth_token['provider']);

		# Get the provider (object)
		$provider = $class::getOAuth2ProviderObject();

		# Get a new token based on the refresh token
		$grant = new RefreshToken();

		try {
			$token = $provider->getAccessToken($grant, ['refresh_token' => $oauth_token['refresh_token']]);
		}
		catch(\Exception $e) {
			if(Webhook::PROVIDERS[$oauth_token['provider']]){
				$provider_title = Webhook::PROVIDERS[$oauth_token['provider']]['title'];
			}
			else if(SubscriptionEmail::PROVIDERS[$oauth_token['provider']]){
				$provider_title = SubscriptionEmail::PROVIDERS[$oauth_token['provider']]['title'];
			}
			else {
				$provider_title = str::title($oauth_token['provider']);
			}

			$title = "Could not connect to {$provider_title}";
			$message = "They gave the following reason:<p style=\"font-size: 75%; margin-top: 1rem;\"><code>{$e->getMessage()}</code></p>Please remove the connection and try again.";
			Log::getInstance()->error([
				"title" => $title,
				"message" => $message,
			]);
			return false;
		}

		# Store the new token for current use
		$oauth_token['token'] = $token->getToken();
		if($refresh_token = $token->getRefreshToken()){
			$oauth_token['refresh_token'] = $refresh_token != $oauth_token['refresh_token'] ? $refresh_token : $oauth_token['refresh_token'];
		}
		$oauth_token['expires'] = $token->getExpires();

		# Store the new token for future use
		Factory::getInstance()->update([
			"table" => "oauth_token",
			"id" => $oauth_token['oauth_token_id'],
			"set" => [
				"token" => $oauth_token['token'],
				"refresh_token" => $oauth_token['refresh_token'],
				"expires" => $oauth_token['expires'],
			],
			"user_id" => false,
		]);

		return true;
	}

	/**
	 * Parses a JWT token into its three parts.
	 *
	 * @param string|null $token
	 *
	 * @return array|null Returns an array with the header, payload and signature, or NULL if the token is invalid.
	 */
	public static function parseJwt(?string $token): ?array
	{
		$base64UrlDecode = function($input){
			return base64_decode(strtr($input, '-_', '+/'));
		};

		[$header, $payload, $signature] = explode('.', $token);

		# If any of the three parts are missing, the token is invalid
		if(!$header || !$payload || !$signature){
			return NULL;
		}

		$decodedHeader = json_decode($base64UrlDecode($header), true);
		$decodedPayload = json_decode($base64UrlDecode($payload), true);
		$decodedSignature = $base64UrlDecode($signature);

		return [
			"header" => $decodedHeader,
			"payload" => $decodedPayload,
			"signature" => $decodedSignature,
		];
	}
}