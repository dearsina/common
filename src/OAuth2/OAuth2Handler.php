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
	const PROVIDERS = [
		"entra" => [
			"type" => "sso",
			"title" => "Entra ID (Azure Active Directory)",
			"icon" => [
				"svg" => "/img/EntraLogoColour.svg",
				"style" => [
					"margin-right" => "0.5rem",
				],
				"tooltip" => "Entra ID (Azure Active Directory)",
			],
		],
		"google_drive" => [
			"type" => "cloud",
			"title" => "Google Drive",
			"desc" => "Send your client files to your Google Drive.",
			"icon" => [
				"type" => "brand",
				"name" => "google-drive",
			],
		],
		"one_drive" => [
			"type" => "cloud",
			"title" => "Microsoft OneDrive",
			"desc" => "Send your client files to your OneDrive.",
			"icon" => [
				"type" => "brand",
				"name" => "microsoft",
			],
		],
		"share_point" => [
			"type" => "cloud",
			"title" => "SharePoint",
			"desc" => "Send your client files to your SharePoint.",
			"icon" => "circle-nodes",
		],
		"gmail" => [
			"type" => "email",
			"title" => "Google Mail",
			"desc" => "Send emails from your Gmail email address.",
			"icon" => [
				"type" => "brand",
				"name" => "google",
				"tooltip" => "Gmail",
			],
		],
		"outlook" => [
			"type" => "email",
			"title" => "Microsoft Exchange",
			"desc" => "Send emails from your Outlook email address.",
			"icon" => [
				"type" => "brand",
				"name" => "microsoft",
				"tooltip" => "Outlook",
			],
		],
		"smtp" => [
			"type" => "email",
			"title" => "SMTP",
			"desc" => "Send emails from your own mail server.",
			"icon" => [
				"name" => "inbox",
				"tooltip" => "SMTP",
			],
		],
	];

	const USER_SSO_DATA_FIELDS = [
		"id" => [
			"title" => "ID",
			"icon" => "fingerprint",
			"provider" => [
				"entra" => "id",
			],
			"field_type" => [
				"name" => "input",
			],
		],
		"email" => [
			"title" => "Email address",
			"icon" => "at",
			"provider" => [
				"entra" => "mail",
			],
			"field_type" => [
				"name" => "email",
			],
		],
		"phone" => [
			"title" => "Phone number",
			"icon" => "mobile",
			"provider" => [
				"entra" => ["number", "mobilePhone"],
			],
			"field_type" => [
				"name" => "tel",
			],
		],
		"first_name" => [
			"title" => "First name",
			"icon" => "input-text",
			"provider" => [
				"entra" => ["givenName"],
			],
			"field_type" => [
				"name" => "input",
			],
		],
		"last_name" => [
			"title" => "Last name",
			"icon" => "input-text",
			"provider" => [
				"entra" => ["surname"],
			],
			"field_type" => [
				"name" => "input",
			],
		],
		"name" => [
			"title" => "Full name",
			"icon" => "id-badge",
			"provider" => [
				"entra" => ["displayName", "userPrincipalName"],
			],
			"field_type" => [
				"name" => "input",
			],
		],
		"department" => [
			"title" => "Department",
			"icon" => "people-group",
			"provider" => [
				"entra" => ["department"],
			],
			"sso_only" => true,
			"field_type" => [
				"name" => "input",
			],
		],
		"office_location" => [
			"title" => "Office location",
			"icon" => "building",
			"provider" => [
				"entra" => ["officeLocation", "streetAddress"],
			],
			"sso_only" => true,
			"field_type" => [
				"name" => "input",
			],
		],
		"job_title" => [
			"title" => "Job title",
			"icon" => "briefcase",
			"provider" => [
				"entra" => "jobTitle",
			],
			"sso_only" => true,
			"field_type" => [
				"name" => "input",
			],
		],
		"language" => [
			"title" => "Language",
			"icon" => "language",
			"provider" => [
				"entra" => "preferredLanguage",
			],
			"sso_only" => true,
			"field_type" => [
				"name" => "input",
			],
		],
		"business_phone" => [
			"title" => "Business phone",
			"icon" => "phone-office",
			"provider" => [
				"entra" => "businessPhones",
			],
			"sso_only" => true,
			"field_type" => [
				"name" => "tel",
			],
		],
		"street_address" => [
			"title" => "Street address",
			"icon" => "road",
			"provider" => [
				"entra" => "streetAddress",
			],
			"sso_only" => true,
			"field_type" => [
				"name" => "input",
			],
		],
	];

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
			echo "<script>window.close();</script>";
			exit;
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
	 * @return bool|string|null
	 * @throws BadRequest
	 */
	public static function ensureTokenIsFresh(array &$oauth_token, ?bool $silent = NULL, ?bool $return_error = NULL)
	{
		# If the token has yet to expire, we're good
		if($oauth_token['expires'] > strtotime("now")){
			if($return_error){
				return NULL;
			}
			return true;
		}

		# Ensure we have a refresh token in case the token has expired
		if(!$oauth_token['refresh_token']){
			if($return_error){
				return NULL;
			}
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
			# If we're NOT silent, display the error
			if(!$silent){
				$provider_title = OAuth2Handler::getProviderTitle($oauth_token['provider']);
				$title = "Could not connect to {$provider_title}";
				$message = "They gave the following reason:<p style=\"font-size: 75%; margin-top: 1rem;\"><code>{$e->getMessage()}</code></p>Please remove the connection and try again.";
				Log::getInstance()->error([
					"title" => $title,
					"message" => $message,
				]);
			}

			# If we are to return the error, do so
			if($return_error){
				return $e->getMessage();
			}

			# Otherwise, return false
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

		# If we were to return the error, and there is none, return NULL
		if($return_error){
			return NULL;
		}

		# Otherwise, return true
		return true;
	}

	/**
	 * Parses a JWT token into its three parts:
	 * header, payload and signature.
	 *
	 * Header:
	 * {
	 * "typ": "JWT",
	 * "nonce": "HkIDnF0tjQQcj2wUMvriMN0meqk-CO3AMfuq2ForZ6A",
	 * "alg": "RS256",
	 * "x5t": "Mc7l3Iz93g7uwgNeEmmw_WYGPko",
	 * "kid": "Mc7l3Iz93g7uwgNeEmmw_WYGPko"
	 * }
	 *
	 * Payload:
	 * {
	 * "aud": "00000003-0000-0000-c000-000000000000",
	 * "iss": "https://sts.windows.net/94b3f1b2-8b3a-49e3-ba33-8b8fb6d18361/",
	 * "iat": 1728099239,
	 * "nbf": 1728099239,
	 * "exp": 1728103603,
	 * "acct": 0,
	 * "acr": "1",
	 * "aio": "AVQAq***iBpkxpFs=",
	 * "amr": [
	 * "pwd",
	 * "rsa"
	 * ],
	 * "app_displayname": "KYCDD",
	 * "appid": "7b47653b-***-fff16020d913",
	 * "appidacr": "1",
	 * "deviceid": "772fa84d-***-c4d8272be052",
	 * "family_name": "AL H******I",
	 * "given_name": "E**d",
	 * "idtyp": "user",
	 * "ipaddr": "2.49.168.167",
	 * "name": "AL H******I E***",
	 * "oid": "ca211be3-***-043cb49afaf2", # The user's ID
	 * "onprem_sid": "S-1-5-21-2256439942-1328414369-864950462-645343",
	 * "platf": "2",
	 * "puid": "100320004768EF87",
	 * "rh": "0.AQIAsvGzlDqL40m6M4uPttGDYQMAAAAAAAAAwAAAAAAAAADcAKI.",
	 * "scp": "GroupMember.Read.All openid User.Read User.ReadBasic.All profile email",
	 * "signin_state": [
	 * "kmsi"
	 * ],
	 * "sub": "4DZyxoBPsRhmtPPra3Ax-1A-eLJm0JoEvVdVUFfGA1E",
	 * "tenant_region_scope": "EU",
	 * "tid": "94b3f1b2-8b3a-49e3-ba33-8b8fb6d18361",
	 * "unique_name": "e***i@c***r.com",
	 * "upn": "e***i@c***r.com",
	 * "uti": "8svcpjktgECfKJrFf0UWAA",
	 * "ver": "1.0",
	 * "wids": [
	 * "b79fbf4d-***-76b194e85509"
	 * ],
	 * "xms_idrel": "1 10",
	 * "xms_st": {
	 * "sub": "ICdiZMPZmM27M8oYWSVxOqSb_M-w-Vg_x2N-zTKFXoo"
	 * },
	 * "xms_tcdt": 1329796979,
	 * "xms_tdbr": "EU"
	 * }
	 *
	 * Signature:
	 * (Can be used to verify the token)
	 *
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

	public static function getProviderTitle(?string $provider): ?string
	{
		if(!$provider){
			return NULL;
		}

		if(OAuth2Handler::PROVIDERS[$provider]){
			return OAuth2Handler::PROVIDERS[$provider]['title'];
		}

		return str::title($provider);
	}
}