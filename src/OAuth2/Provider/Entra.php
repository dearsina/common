<?php

namespace App\Common\OAuth2\Provider;

use App\Common\Log;
use App\Common\OAuth2\OAuth2Handler;
use App\Common\OAuth2\SingleSignOnProviderInterface;
use App\Common\OAuth2\SingleSignOnTrait;
use App\Common\Prototype;
use App\Common\str;

class Entra extends Prototype implements SingleSignOnProviderInterface {
	use SingleSignOnTrait;
	private ?\Microsoft\Graph\Graph $graph = NULL;

	const SCOPES = [
		"openid",
		"User.Read", // Is needed to call the organization endpoint
		//		"profile",
		//		"email",
		"GroupMember.Read.All",
		"User.ReadBasic.All",
		'offline_access',
	];

	public function __construct(array $oauth_token)
	{
		if(!OAuth2Handler::ensureTokenIsFresh($oauth_token)){
			return;
		}
		$this->graph = new \Microsoft\Graph\Graph();
		$this->graph->setAccessToken($oauth_token['token']);
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
		// Doesn't seem to matter, will always return v1 tokens
		// @link https://github.com/TheNetworg/oauth2-azure/issues/124

		return $provider;
	}

	public function getOrganization(array $oauth_token): ?array
	{
		# Get the JWT
		if(!$jwt = OAuth2Handler::parseJwt($oauth_token['token'])){
			// If the token is invalid
			return NULL;
		}

		if(!$jwt['payload']['tid']){
			// If the token doesn't include a tenant ID
			return NULL;
		}

		if(!$this->graph){
			return NULL;
		}

		try {
			$organization = $this->graph->createRequest("GET", "/organization/{$jwt['payload']['tid']}")
				->setReturnType(\Microsoft\Graph\Model\Organization::class)
				->execute();

			# Get the organization properties
			$org = $organization->getProperties();

			# Ensure there is a generic "name" key for the display name of the organization
			$org['name'] = $org['displayName'];

			# Ensure there is a generic "id" key for the tenant ID
			$org['id'] = $org['id'] ?: $jwt['payload']['tid'];

			return $org;
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to get organization: %s");
		}
	}

	/**
	 * <code>
	 * {
	 *    "@odata.context": "https:\/\/graph.microsoft.com\/v1.0\/$metadata#groups\/$entity",
	 *    "id": "03cce4f2-0071-4b54-ae3a-6a4e8e88ae7b",
	 *    "deletedDateTime": null,
	 *    "classification": null,
	 *    "createdDateTime": "2023-12-29T05:29:48Z",
	 *    "creationOptions": [],
	 *    "description": "KYCDD admin group",
	 *    "displayName": "KYCDD admin group",
	 *    "expirationDateTime": null,
	 *    "groupTypes": [],
	 *    "isAssignableToRole": false,
	 *    "mail": null,
	 *    "mailEnabled": false,
	 *    "mailNickname": "00000000-0000-0000-0000-000000000000",
	 *    "membershipRule": null,
	 *    "membershipRuleProcessingState": null,
	 *    "onPremisesDomainName": null,
	 *    "onPremisesLastSyncDateTime": null,
	 *    "onPremisesNetBiosName": null,
	 *    "onPremisesSamAccountName": null,
	 *    "onPremisesSecurityIdentifier": null,
	 *    "onPremisesSyncEnabled": null,
	 *    "preferredDataLocation": null,
	 *    "preferredLanguage": null,
	 *    "proxyAddresses": [],
	 *    "renewedDateTime": "2023-12-29T05:29:48Z",
	 *    "resourceBehaviorOptions": [],
	 *    "resourceProvisioningOptions": [],
	 *    "securityEnabled": true,
	 *    "securityIdentifier": "S-1-12-1-63759602-1263796337-1315584686-2075035790",
	 *    "theme": null,
	 *    "uniqueName": null,
	 *    "visibility": null,
	 *    "onPremisesProvisioningErrors": [],
	 *    "serviceProvisioningErrors": []
	 * }
	 * </code>
	 *
	 * @param string $group_id
	 *
	 * @return array|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getGroup(string $group_id): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		try {
			$response = $this->graph->createRequest("GET", "/groups/{$group_id}")
				->setReturnType(\Microsoft\Graph\Model\Group::class)
				->execute();

			$group = $response->getProperties();
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to get group: %s");
		}

		return $group;
	}

	const DEFAULT_PROPERTIES = [
		"businessPhones",
		"displayName",
		"givenName",
		"id",
		"jobTitle",
		"mail",
		"mobilePhone",
		"officeLocation",
		"preferredLanguage",
		"surname",
		"userPrincipalName",
	];

	/**
	 * The default User object:
	 *
	 * <code>
	 * {
	 * 	"@odata.context": "https://graph.microsoft.com/v1.0/$metadata#users/$entity",
	 * 	"businessPhones": [
	 * 		"+2*********9"
	 * 	],
	 * 	"displayName": "M**********************",
	 * 	"givenName": "G*****a",
	 * 	"id": "0071f4da-9257-4466-879a-ae306a5ec327",
	 * 	"jobTitle": "B**************r",
	 * 	"mail": "g*****n@m*******c.com",
	 * 	"mobilePhone": null,
	 * 	"officeLocation": null,
	 * 	"preferredLanguage": null,
	 * 	"surname": "M****N",
	 * 	"userPrincipalName": "g*****n@m*******c.com"
	 * }
	 * </code>
	 *
	 * A lot more information can be retrieved by using the $select query parameter:
	 * https://learn.microsoft.com/en-us/graph/api/resources/user?view=graph-rest-1.0
 	 *
	 *
	 * @param string     $user_id
	 * @param array|null $additional_properties
	 *
* @return array|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getUser(string $user_id, ?array $additional_properties = []): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		$properties = array_merge(self::DEFAULT_PROPERTIES, $additional_properties);

		try {
			$select = implode(",", $properties);
			$response = $this->graph->createRequest("GET", "/users/{$user_id}?\$select={$select}")
				->setReturnType(\Microsoft\Graph\Model\User::class)
				->execute();

			$user = $response->getProperties();

			# Unify the user array (across all providers)
			Entra::unifyUser($user);
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to get user: %s");
		}

		return $user;
	}

	/**
	 * Should only really be called for personal accounts.
	 * Use getUser() for work/school accounts.
	 *
	 * Expected returned array:
	 * <code>
	 * {
	 *	"@odata.context": "https://graph.microsoft.com/v1.0/$metadata#users/$entity",
	 *	"@microsoft.graph.tips": "Use $select to choose only the properties your app needs, as this can lead to performance improvements. For example: GET me?$select=signInActivity,accountEnabled",
	 *	"userPrincipalName": "dearsina@outlook.com",
	 *	"id": "243d23538f22ba52",
	 *	"displayName": "Sina Bahrami",
	 *	"surname": "Bahrami",
	 *	"givenName": "Sina",
	 *	"preferredLanguage": null,
	 *	"mail": "dearsina@outlook.com",
	 *	"mobilePhone": null,
	 *	"jobTitle": null,
	 *	"officeLocation": null,
	 *	"businessPhones": []
	 * }
	 * </code>
	 * @return array|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getMe(): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		try {
			$response = $this->graph->createRequest("GET", "/me")
				->setReturnType(\Microsoft\Graph\Model\User::class)
				->execute();

			$user = $response->getProperties();

			# Unify the user array (across all providers)
			Entra::unifyUser($user);
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to get user: %s");
		}

		return $user;
	}

	public function getGroups(?string $q = NULL): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		try {
			if($q){
				$q = urlencode($q);
				$response = $this->graph->createRequest("GET", '/groups?$search="displayName:'.$q.'"')
					->addHeaders(["ConsistencyLevel" => "eventual"])
					->setReturnType(\Microsoft\Graph\Model\Group::class)
					->execute();
			}
			else {
				$response = $this->graph->createRequest("GET", "/groups{$search}")
					->setReturnType(\Microsoft\Graph\Model\Group::class)
					->execute();
			}

			$groups = array_map(function($group){
				return $group->getProperties();
			}, $response);
		}

		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to get groups: %s");
		}

		return $groups;
	}

	public function getGroupMembers(string $group_id): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		try {
			$response = $this->graph->createRequest("GET", "/groups/{$group_id}/members")
				->setReturnType(\Microsoft\Graph\Model\DirectoryObject::class)
				->execute();

			$members = array_map(function($member){
				return $member->getProperties();
			}, $response);

			$this->unifyMembers($members);

		}
		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to get group members: %s");
		}

		return $members;
	}

	/**
	 * Expected returned array:
	 * <code>
	 * [
	 *    "@odata.context": "https://graph.microsoft.com/v1.0/$metadata#subscriptions/$entity",
	 *    "applicationId": "7b47653b-650f-4975-8ffc-fff16020d913",
	 *    "changeType": "updated",
	 *    "clientState": "25105f34-f7b4-4bd5-88e8-12bbc1504aa3",
	 *    "creatorId": "2c3692b3-45eb-4e48-9564-38db60ce0269",
	 *    "encryptionCertificate": null,
	 *    "encryptionCertificateId": null,
	 *    "expirationDateTime": "2024-04-22T12:05:48Z",
	 *    "id": "bdacc4b5-408b-46f4-a78c-04bf49ca538d",
	 *    "includeResourceData": null,
	 *    "latestSupportedTlsVersion": "v1_2",
	 *    "lifecycleNotificationUrl": "https://app.askopi.co.uk/webhooks.microsoft.php",
	 *    "notificationQueryOptions": null,
	 *    "notificationUrl": "https://app.askopi.co.uk/webhooks.microsoft.php",
	 *    "notificationUrlAppId": null,
	 *    "resource": "/groups/0fe4b352-eef1-4f09-941a-fa6d218e2aa0/members"
	 * ]
	 * </code>
	 *
	 * @param string $group_id
	 *
	 * @return array|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function subscribeToGroupChanges(string $group_id, string $client_state): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		try {
			$response = $this->graph->createRequest("POST", "/subscriptions")
				->attachBody([
					"changeType" => "updated",
					"notificationUrl" => "https://app.{$_ENV['domain']}/webhooks.microsoft.php",
					"lifecycleNotificationUrl" => "https://app.{$_ENV['domain']}/webhooks.microsoft.php",
					"resource" => "/groups/{$group_id}/members",
					"expirationDateTime" => $this->getMaxExpirationDateTime(),
					"clientState" => json_encode([
						"rel_table" => "subscription_group",
						"client_state" => $client_state,
					]),
				])
				->setReturnType(\Microsoft\Graph\Model\Subscription::class)
				->execute();

			$subscription = $response->getProperties();

		}
		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to subscribe to group changes: %s");
		}

		return $subscription;
	}

	public function subscribeToUserChanges(string $user_id, string $client_state): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		try {
			$response = $this->graph->createRequest("POST", "/subscriptions")
				->attachBody([
					"changeType" => "updated",
					"notificationUrl" => "https://app.{$_ENV['domain']}/webhooks.microsoft.php",
					"lifecycleNotificationUrl" => "https://app.{$_ENV['domain']}/webhooks.microsoft.php",
					"resource" => "/users/{$user_id}",
					"expirationDateTime" => $this->getMaxExpirationDateTime(),
					"clientState" => json_encode([
						"rel_table" => "subscription_seat",
						// To help identify which type of subscription this is
						"client_state" => $client_state,
					]),
				])
				->setReturnType(\Microsoft\Graph\Model\Subscription::class)
				->execute();

			$subscription = $response->getProperties();

		}
		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to subscribe to user changes: %s");
		}

		return $subscription;
	}

	/**
	 * The maximum number of minutes a subscription for a group or user change can last.
	 * This is 29 days from now, as per Microsoft's documentation. For testing, we're using
	 * a far shorter amount of time to not be flooded with notifications.
	 *
	 * @link https://learn.microsoft.com/en-us/graph/change-notifications-overview#supported-resources
	 */
	const PROD_MAX_EXPIRY_MINUTES = 41760;
	const DEV_MAX_EXPIRY_MINUTES = 65; // For testing


	/**
	 * Will return a string of the maximum expiration date and time for a subscription.
	 * That string will be a certain amount in the future. The amount will differ depending
	 * on whether the application is in development or production.
	 *
	 * @return string
	 */
	private function getMaxExpirationDateTime(): string
	{
		$now = new \DateTime();
		$now->setTimezone(new \DateTimeZone("UTC"));
		$now->modify("+".(str::isDev() ? self::DEV_MAX_EXPIRY_MINUTES : self::PROD_MAX_EXPIRY_MINUTES)." minutes");
		return $now->format("c");
	}

	public function unsubscribeFromNotifications(string $subscription_id): ?bool
	{
		if(!$this->graph){
			return false;
		}

		try {
			$this->graph->createRequest("DELETE", "/subscriptions/{$subscription_id}")
				->execute();
		}
		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to delete subscription to group changes: %s");
		}

		return true;
	}

	/**
	 * Renew a subscription to group or user changes.
	 *
	 * @param string $subscription_id
	 *
	 * @return array|null Returns an array of the renewed subscription properties, or NULL on failure.
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function reauthorizeSubscription(string $subscription_id): ?array
	{
		if(!$this->graph){
			return NULL;
		}

		try {
			$response = $this->graph->createRequest("PATCH", "/subscriptions/{$subscription_id}")
				->attachBody([
					"expirationDateTime" => $this->getMaxExpirationDateTime(),
				])
				->setReturnType(\Microsoft\Graph\Model\Subscription::class)
				->execute();

			$subscription = $response->getProperties();
		}
		catch(\Exception $e) {
			$this->throwError($e, "%s error attempting to renew subscription to group changes: %s");
		}

		return $subscription;
	}

	/**
	 * Error handler.
	 * Logs the error and displays a message to the user.
	 * Crucially, doesn't throw an error on execution.
	 *
	 * @param \Exception  $e
	 * @param string|null $context
	 *
	 * @return void
	 */
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
			"display" => str::isDev(),
			"title" => "Microsoft Entra ID OAuth2 error",
			"message" => $narrative,
			"trace" => $error,
		]);
	}

	private function unifyMembers(array &$members): void
	{
		foreach($members as &$member){
			Entra::unifyMember($member);
		}
	}

	public static function getButton(string $group_id, string $client_state): array
	{
		return [
			"hash" => [
				"rel_table" => "subscription_group",
				"rel_id" => $group_id,
				"action" => "subscribe",
			],
			"approve" => [
				"icon" => Icon::get("bell"),
				"colour" => "green",
				"title" => "Subscribe to group changes?",
				"message" => "You will receive notifications when members of this group change.",
			],
			"icon" => Icon::get("bell"),
			"title" => "Subscribe to group changes...",
			"vars" => [
				"client_state" => $client_state,
			],
		];
	}
}