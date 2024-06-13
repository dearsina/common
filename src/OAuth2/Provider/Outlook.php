<?php

namespace App\Common\OAuth2\Provider;

use App\Common\Email\Email;
use App\Common\Log;
use App\Common\OAuth2\OAuth2Handler;
use App\Common\str;

/**
 * Provider for sending emails via Microsoft Exchange.
 */
class Outlook extends \App\Common\Prototype implements \App\Common\OAuth2\EmailProviderInterface {
	private \Microsoft\Graph\Graph $graph;

	const SCOPES = [
		"https://graph.microsoft.com/User.Read",
		"https://graph.microsoft.com/Mail.Send",
		'offline_access',
	];

	public function __construct(array $oauth_token)
	{
		OAuth2Handler::ensureTokenIsFresh($oauth_token);
		$this->graph = new \Microsoft\Graph\Graph();
		$this->graph->setAccessToken($oauth_token['token']);
	}

	/**
	 * <code>
	 * {
	 *    "@odata.context": "https:\/\/graph.microsoft.com\/v1.0\/$metadata#users\/$entity",
	 *    "displayName": "Bahrami Sina",
	 *    "surname": "Bahrami",
	 *    "givenName": "Sina",
	 *    "id": "243d23538f22ba52",
	 *    "userPrincipalName": "dearsina@outlook.com",
	 *    "businessPhones": [],
	 *    "jobTitle": null,
	 *    "mail": null,
	 *    "mobilePhone": null,
	 *    "officeLocation": null,
	 *    "preferredLanguage": null
	 * }
	 * </code>
	 * <code>
	 * {
	 *    "@odata.context": "https://graph.microsoft.com/v1.0/$metadata#users/$entity",
	 *    "businessPhones": [],
	 *    "displayName": "KYCDD",
	 *    "givenName": null,
	 *    "jobTitle": null,
	 *    "mail": "info@kycdd.co.za",
	 *    "mobilePhone": "+27677700000",
	 *    "officeLocation": null,
	 *    "preferredLanguage": "en-GB",
	 *    "surname": null,
	 *    "userPrincipalName": "info@kycdd.co.za",
	 *    "id": "2c3692b3-45eb-4e48-9564-38db60ce0269"
	 * }
	 * </code>
	 * @return string|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getEmailAddress(): ?string
	{
		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("GET", "/me")
				->addHeaders([
					"Content-Type" => "application/json",
				])
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to retrieve user details: %s");
			return false;
		}

		if($response_array['mail'] && filter_var($response_array['mail'], FILTER_VALIDATE_EMAIL)){
			return $response_array['mail'];
		}

		return $response_array['userPrincipalName'];
	}

	public function sendEmail(Email $email): bool
	{
		$message["subject"] = $email->getSubject();
		$message["body"] = [
			"contentType" => "html",
			"content" => $email->getHtmlBody(),
		];

		foreach($email->getTo() as $email_address => $recipient_name){
			$message['toRecipients'][] = [
				"emailAddress" => [
					"address" => $email_address,
				],
			];
		}

		if($cc = $email->getCc()){
			foreach($cc as $email_address => $recipient_name){
				$message['ccRecipients'][] = [
					"emailAddress" => [
						"address" => $email_address,
					],
				];
			}
		}

		if($bcc = $email->getBcc()){
			foreach($bcc as $email_address => $recipient_name){
				$message['bccRecipients'][] = [
					"emailAddress" => [
						"address" => $email_address,
					],
				];
			}
		}

		if($headers = $email->getHeaders()){
			foreach($headers as $header_key => $header_val){
				$message['internetMessageHeaders'][] = [
					"name" => str_replace("_", "-", "X-{$header_key}"),
					"value" => $header_val,
				];
			}
		}

		# Inline images
		if($images = $email->envelope->getChildren()){
			foreach ($images as $image) {
				if ($image instanceof \Swift_Image) {
					$message['attachments'][] = [
						"@odata.type" => "#microsoft.graph.fileAttachment",
						"name" => $image->getFilename(),
						"isInline" => true,
						"contentId" => $image->getId(),
						"contentType" => $image->getContentType(),
						"contentBytes" => chunk_split(base64_encode($image->getBody())),
					];
				}
			}
		}

		# Attachments
		if($attachments = $email->getAttachments()){
			foreach($attachments as $attachment){
				$message['attachments'][] = [
					"@odata.type" => "#microsoft.graph.fileAttachment",
					"name" => $attachment['filename'],
					"contentType" => mime_content_type($attachment['path']),
					"contentBytes" => chunk_split(base64_encode(file_get_contents($attachment['path']))),
				];
			}
		}

		try {
			$response = $this->graph->setApiVersion("v1.0")
				->createRequest("POST", "/me/sendMail")
				->addHeaders([
					"Content-Type" => "application/json",
				])
				->attachBody([
					"message" => $message,
				])
				->execute();

			$response_array = $response->getBody();
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to send an email: %s");
			return false;
		}

		return true;
	}

	private function throwError(\Exception $e, ?string $context = NULL): void
	{
		# The best way of getting error responses
		if(method_exists($e, "getResponse") && method_exists($e->getResponse(), "getBody")){
			$error_code = $e->getCode();

			if(method_exists($e->getResponse()->getBody(), "getContents")){
				$error_json = $e->getResponse()->getBody()->getContents();
			}
			else {
				$error_json = $e->getResponse()->getBody();
			}

			$error_array = json_decode($error_json, true);
			$error_message = $error_array['error']['message'];
		}

		# A decent fallback
		else if($error = $e->getMessage() && $pos = strpos($error, "{")){
			$message = substr($error, 0, $pos);
			$error_array = json_decode(substr($error, $pos), true);
			$error_code = $error_array['error']['code'];
			$error_message = "{$message}: {$error_array['error']['message']}";
		}

		# If everything else fails
		else {
			$error_code = $e->getCode() ?: "Got an";
			$error_message = $e->getMessage();
		}

		# If error message contains...

		if($context){
			$narrative = sprintf($context, $error_code, $error_message);
		}

		else {
			$narrative = $error_message ?: $error;
		}

		Log::getInstance()->error([
			"display" => false,
			"title" => "Outlook OAuth2 error",
			"message" => $narrative,
			"trace" => $error,
		]);

		# No exceptions are thrown, because we're reverting to the fallback email solution
	}

	/**
	 * @inheritDoc
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object
	{
		$provider = new \TheNetworg\OAuth2\Client\Provider\Azure([
			'clientId' => $_ENV['microsoft_graph_client_id'],
			'clientSecret' => $_ENV['microsoft_graph_client_secret'],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			"prompt" => $force_refresh_token ? "consent" : NULL,
			'scopes' => self::SCOPES,
			'defaultEndPointVersion' => '2.0',
		]);

		// Set to use v2 API
		$provider->defaultEndPointVersion = \TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0;

		return $provider;
	}
}