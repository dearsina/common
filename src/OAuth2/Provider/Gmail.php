<?php

namespace App\Common\OAuth2\Provider;

use App\Common\Email\Email;
use App\Common\Log;
use App\Common\OAuth2\EmailProviderInterface;
use App\Common\OAuth2\OAuth2Handler;
use App\Common\Prototype;
use App\Common\str;

class Gmail extends Prototype implements EmailProviderInterface {
	private \Google_Client $client;
	private \Google_Service_Gmail $gmail;

	const SCOPES = [
		"https://www.googleapis.com/auth/gmail.modify",
		"https://www.googleapis.com/auth/gmail.send"
	];

	public function __construct(array $oauth_token)
	{
		OAuth2Handler::ensureTokenIsFresh($oauth_token);
		$this->client = new \Google_Client();
		$this->client->addScope(self::SCOPES);
		$this->client->setAccessType('offline');
		$this->client->setPrompt('consent');
		$this->client->setAccessToken([
			'access_token' => $oauth_token['token'],
			'expires_in' => $oauth_token['expires'],
		]);

		$this->gmail = new \Google_Service_Gmail($this->client);
	}

	public function getEmailAddress(): ?string
	{
		try {
			$profile = $this->gmail->users->getProfile('me');
			return $profile->emailAddress;
		} catch (\Exception $e) {
			$this->throwError($e, "%s error attempting to get an email address: %s", false);
			return NULL;
		}
	}

	public function sendEmail(Email $message): bool
	{
		// Create Gmail service
		$service = new \Google_Service_Gmail($this->client);

		// Construct email message
		$email = new \Google_Service_Gmail_Message();
		$email->setRaw($this->base64url_encode($message->getEnvelope()->toString()));

		// Send the email
		try {
			$service->users_messages->send('me', $email);
		}

		# Catch exceptions
		catch (\Exception $e) {
			$this->throwError($e, "%s error attempting to send an email: %s");
		}

		 return true;
	}

	/**
	 * Encode data to Base64URL
	 * @param string $data
	 * @return boolean|string
	 * @link https://base64.guru/developers/php/examples/base64url
	 */
	private function base64url_encode(string $data): ?string
	{
		// First of all, you should encode $data to Base64 string
		$b64 = base64_encode($data);

		// Make sure you get a valid result, otherwise, return FALSE, as the base64_encode() function do
		if ($b64 === false) {
			return NULL;
		}

		// Convert Base64 to Base64URL by replacing “+” with “-” and “/” with “_”
		$url = strtr($b64, '+/', '-_');

		// Remove padding character from the end of line and return the Base64URL result
		return rtrim($url, '=');
	}

	/**
	 * Needs to remain public, because it is called from the OAuth2Handler class,
	 * when refreshing a token.
	 *
	 * @return \League\OAuth2\Client\Provider\Google
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object
	{
		return new \League\OAuth2\Client\Provider\Google([
			'clientId' => $_ENV['google_oauth_client_id'],
			'clientSecret' => $_ENV['google_oauth_client_secret'],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			"accessType" => "offline",
			"prompt" => $force_refresh_token ? "consent" : NULL, # Forces consent (and a refresh token) every time
			"scopes" => self::SCOPES,
		]);
	}

	/**
	 * Handles any errors that the file and folder methods may throw.
	 * Will still throw an error at the end, but will first store the
	 * complete message to the log and add optional context to the
	 * error messages that can at times be quite sparse.
	 *
	 * @param \Exception  $e
	 * @param string|null $context
	 *
	 * @throws \Exception
	 */
	private function throwError(\Exception $e, ?string $context = NULL, ?bool $throw_exception = true): void
	{
		$error = $e->getMessage();
		if(str::isJson($error)){
			$error_array = json_decode($error, true);
			$error_code = $error_array['error']['code'];
			$error_message = $error_array['error']['message'];
		}

		if($context){
			$narrative = sprintf($context, $error_code, $error_message);
		}

		else {
			$narrative = $error_message ?: $error;
		}

		Log::getInstance()->error([
			"display" => false,
			"title" => "Google OAuth2 error",
			"message" => $narrative,
			"trace" => $error
		]);

		if(!$throw_exception){
			return;
		}

		throw new \Exception($narrative);
	}
}