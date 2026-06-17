<?php

namespace App\Common\OAuth2\Provider;

use App\Common\Country\Country;
use App\Common\OAuth2\OAuth2Handler;
use App\Common\OAuth2\Prototype;
use App\Common\OAuth2\ProviderInterface;
use App\Common\str;
use App\Subscription\SubscriptionHandler;
use GuzzleHttp\Client;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use XeroAPI\XeroPHP\Api\IdentityApi;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Configuration;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\Contacts;

class Xero extends Prototype implements ProviderInterface {
	const SCOPES = "openid email profile offline_access accounting.settings accounting.transactions accounting.contacts accounting.journals.read accounting.reports.read accounting.attachments";

	/**
	 * @inheritDoc
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object
	{
		return new \League\OAuth2\Client\Provider\GenericProvider([
			'clientId' => $_ENV["XERO_CLIENT_ID"],
			'clientSecret' => $_ENV["XERO_CLIENT_SECRET"],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			'urlAuthorize' => 'https://login.xero.com/identity/connect/authorize',
			'urlAccessToken' => 'https://identity.xero.com/connect/token',
			'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation',
			'scopes' => self::SCOPES,
			"prompt" => $force_refresh_token ? "consent" : NULL, // Forces consent (and a refresh token) every time
		]);
	}

	/**
	 * Xero doesn't return the tenant ID with the access token,
	 * so we have to fetch it separately.
	 *
	 * This method will set the tenant ID in the oauth_token array
	 * and update the database record if it's not already set.
	 *
	 * @param array $oauth_token
	 *
	 * @return void
	 * @throws \App\Common\Exception\BadRequest
	 * @throws \XeroAPI\XeroPHP\ApiException
	 */
	public function setTenantId(array &$oauth_token): void
	{
		if($oauth_token['tenant_id']){
			return;
		}

		# Ensure the token is fresh
		OAuth2Handler::ensureTokenIsFresh($oauth_token);

		# Build config with the access token
		$config = Configuration::getDefaultConfiguration()
			->setAccessToken($oauth_token['token']);

		// 3) Use IdentityApi to fetch connections (tenants)
		$identityApi = new IdentityApi(
			new Client(),
			$config
		);

		$connections = $identityApi->getConnections();

		if(empty($connections)){
			throw new \RuntimeException('No Xero connections found for this token.');
		}

		// If you only have one org per user, just take the first:
		$connection = $connections[0];

		$tenantId = $connection->getTenantId();   // <- this is your xero-tenant-id

		$this->sql->update([
			"table" => "oauth_token",
			"id" => $oauth_token['oauth_token_id'],
			"set" => [
				"tenant_id" => $tenantId,
			],
		]);

		$oauth_token['tenant_id'] = $tenantId;
	}

	/**
	 * Given a valid OAuth2 token, fetch the organisation name.
	 *
	 * @param array $oauth_token
	 *
	 * @return string|null
	 * @throws \App\Common\Exception\BadRequest
	 * @throws \XeroAPI\XeroPHP\ApiException
	 */
	public function getOrganisationName(array $oauth_token): ?string
	{
		# Ensure the token is fresh
		OAuth2Handler::ensureTokenIsFresh($oauth_token);

		# Ensure tenant ID is set
		$this->setTenantId($oauth_token);

		# Build config with the access token
		$config = Configuration::getDefaultConfiguration()
			->setAccessToken($oauth_token['token']);

		try {
			# Call Accounting API
			$accountingApi = new AccountingApi(
				new Client(),
				$config
			);

			$orgs = $accountingApi->getOrganisations($oauth_token['tenant_id']);
			$org = $orgs->getOrganisations()[0];
		}

			# Return NULL if we can't create the API object, which means the token is invalid
		catch(\Exception $e) {
			return NULL;
		}

		return $org->getName();
	}

	/**
	 * Searches for a contact by subscription ID.
	 * If found, returns the contact ID, otherwise NULL.
	 *
	 * @param array               $oauth_token
	 * @param SubscriptionHandler $subscription
	 *
	 * @return string|null
	 */
	public function contactExists(array $oauth_token, SubscriptionHandler $subscription): ?string
	{
		# Check if the contact ID is already set for this specific tenant
		if($subscription->get("xero_contact_id")
		&& $subscription->get("xero_tenant_id")
		&& $subscription->get("xero_tenant_id") == $oauth_token['tenant_id']){
			return $subscription->get("xero_contact_id");
		}

		# Ensure tenant ID is set
		$this->setTenantId($oauth_token);

		# Build config with the access token
		$config = Configuration::getDefaultConfiguration()
			->setAccessToken($oauth_token['token']);

		# Call Accounting API
		$accountingApi = new AccountingApi(
			new Client(),
			$config
		);

		$response = $accountingApi->getContacts(
			$oauth_token['tenant_id'],
			NULL,
			"ContactNumber==\"{$subscription->getId()}\""
		);

		if(!$response->getContacts()){
			// Contact does not exist (search by subscription ID failed)

			# Try searching by company name instead
			$response = $accountingApi->getContacts(
				$oauth_token['tenant_id'],
				NULL,
				"Name==\"{$subscription->getCompany("name")}\""
			);

			if(!$response->getContacts()){
				// Contact does not exist (search by company name failed)

				# Try searching by email address instead
				$response = $accountingApi->getContacts(
					$oauth_token['tenant_id'],
					NULL,
					"EmailAddress==\"{$subscription->getCompany("email")}\""
				);

				if(!$response->getContacts()){
					// Contact does not exist (search by email address failed)
					return NULL;
				}

				# Failsafe, in case more than one company uses the same email address
				if(count($response->getContacts()) > 1){
					// Multiple contacts found, cannot determine which one to use
					return NULL;
				}
			}
		}

		$contact = $response->getContacts()[0];
		$contact_id = $contact->getContactId();

		$this->sql->update([
			"table" => "subscription",
			"id" => $subscription->getId(),
			"set" => [
				"xero_contact_id" => $contact_id,
				"xero_tenant_id" => $oauth_token['tenant_id'],
			],
		]);

		$subscription->set("xero_contact_id", $contact_id);
		$subscription->set("xero_tenant_id", $oauth_token['tenant_id']);

		return $contact_id;
	}

	public function updateContact(array $oauth_token, SubscriptionHandler $subscription): bool
	{
		# Ensure the token is fresh
		OAuth2Handler::ensureTokenIsFresh($oauth_token);

		# Ensure tenant ID is set
		$this->setTenantId($oauth_token);

		# Ensure contact ID has been set
		if(!$contactId = $this->contactExists($oauth_token, $subscription)){
			// If not, assume contact doesn't exist and create it
			return $this->setContact($oauth_token, $subscription);
		}

		// Build Xero config & API client
		$config = Configuration::getDefaultConfiguration()
			->setAccessToken($oauth_token['token']);

		$accountingApi = new AccountingApi(new Client(), $config);

		// Build contact payload from current subscription state
		$payload = $this->getContactPayload($subscription);

		// IMPORTANT: tell Xero which contact to update
		$payload['contact_id'] = $contactId;

		$contact = new Contact($payload);
		$contacts = new Contacts();
		$contacts->setContacts([$contact]);

		try {
			// Update in Xero (or create if it somehow vanished)
			$result = $accountingApi->updateOrCreateContacts($oauth_token['tenant_id'], $contacts);
			$updateds = $result->getContacts();

			if(empty($updateds)){
				// Nothing came back – treat as failure
				return false;
			}

			$updated = $updateds[0];
			$updatedId = $updated->getContactId();

			// Very rare edge case: Xero may merge contacts and return a different ContactID
			if($updatedId && $updatedId !== $contactId){
				$this->sql->update([
					"table" => "subscription",
					"id" => $subscription->getId(),
					"set" => [
						"xero_contact_id" => $updatedId,
						"xero_tenant_id" => $oauth_token['tenant_id'],
					],
				]);

				$subscription->set("xero_contact_id", $updatedId);
				$subscription->set("xero_tenant_id", $oauth_token['tenant_id']);
			}

			return true;
		}
		catch(\Throwable $e) {
			// Log / handle however you usually do
			$this->log->error([
				"title" => "Xero updateContact failed",
				"message" => "Xero updateContact failed for subscription "
					. $subscription->getCompany("name") . ': ' . $e->getMessage(),
				"exception" => $e,
			]);
			return false;
		}
	}

	/**
	 * Creates or updates a contact in Xero for the given subscription.
	 *
	 * @param array               $oauth_token
	 * @param SubscriptionHandler $subscription
	 *
	 * @return bool
	 * @throws \App\Common\Exception\BadRequest
	 * @throws \XeroAPI\XeroPHP\ApiException
	 */
	public function setContact(array $oauth_token, SubscriptionHandler &$subscription): bool
	{
		# Ensure the token is fresh
		OAuth2Handler::ensureTokenIsFresh($oauth_token);

		# Ensure tenant ID is set
		$this->setTenantId($oauth_token);

		// If we already have a contact in Xero, just update it instead
		if ($this->contactExists($oauth_token, $subscription)) {
			return $this->updateContact($oauth_token, $subscription);
		}

		if (!$oauth_token['tenant_id']) {
			// Hard failure: we cannot talk to Xero without a tenant ID
			error_log('Xero setContact failed: missing tenantId for subscription ' . $subscription->getId());
			return false;
		}

		// Build config with the access token
		$config = Configuration::getDefaultConfiguration()
			->setAccessToken($oauth_token['token']);

		// Call Accounting API
		$accountingApi = new AccountingApi(
			new Client(),
			$config
		);

		// Build the Contact model from your payload helper
		$payload = $this->getContactPayload($subscription);
		$contact = new Contact($payload);

		$contacts_object = new Contacts();
		$contacts_object->setContacts([$contact]);

		try {
			$result   = $accountingApi->createContacts($oauth_token['tenant_id'], $contacts_object);
			$contacts = $result->getContacts();

			if (empty($contacts)) {
				$this->log->error([
					"title" => "Xero setContact failed",
					"message" => "Xero createContacts returned no contacts for the {$subscription->getCompany("name")} subscription."
				]);
				return false;
			}

			$created    = $contacts[0];
			$contact_id = $created->getContactId();   // Xero GUID

		} catch (\Throwable $e) {
			$this->log->error([
				"title" => "Xero new contact exception",
				"message" => "Xero new contact insert failed for the {$subscription->getCompany("name")} subscription."
					. $e->getMessage(),
			]);
			return false;
		}

		// Persist ContactID on your subscription
		$this->sql->update([
			"table" => "subscription",
			"id"    => $subscription->getId(),
			"set"   => [
				"xero_contact_id" => $contact_id,
				"xero_tenant_id" => $oauth_token['tenant_id'],
			],
		]);

		$subscription->set("xero_contact_id", $contact_id);
		$subscription->set("xero_tenant_id", $oauth_token['tenant_id']);

		return true;
	}


	private function getContactPersons(SubscriptionHandler $subscription): array
	{
		$contact_persons = [];

		if($invoice_email_string = $subscription->getSettings("invoice_email")){
			$invoice_email_array = str::explode([",", ";"], $invoice_email_string);

			foreach($invoice_email_array as $invoice_email){
				$invoice_email = trim($invoice_email);
				if(!filter_var($invoice_email, FILTER_VALIDATE_EMAIL)){
					continue;
				}

				$contact_persons[] = [
					"email_address" => $invoice_email,
					"include_in_email" => true,
				];
			}
		}

		$contact_person_name = $subscription->getCompany("person");
		$contact_person_name_array = preg_split("/\\s+/", $contact_person_name, 2);
		$contact_person_first_name = $contact_person_name_array[0] ?? '';
		$contact_person_last_name = $contact_person_name_array[1] ?? '';

		$contact_persons[] = [
			"first_name" => substr($contact_person_first_name, 0, 255),
			"last_name" => substr($contact_person_last_name, 0, 255),
			"email_address" => substr((string)$subscription->getCompany("email"), 0, 255),
			"include_in_email" => !$contact_persons,
			// If there are no invoice emails, include the main contact person in emails
		];

		return $contact_persons;
	}

	private function getContactPayload(SubscriptionHandler $subscription): array
	{
		return [
			"is_customer" => true,
			"name" => substr($subscription->getCompany("name"), 0, 255),
			"contact_number" => $subscription->getId(),
			"email_address" => substr((string)$subscription->getCompany("email"), 0, 255),
			"addresses" => [
				[
					"address_type" => "STREET",
					"address_line1" => substr((string)$subscription->getCompany("address_line1"), 0, 500),
					"address_line2" => substr((string)$subscription->getCompany("address_line2"), 0, 500),
					"city" => substr((string)$subscription->getCompany("address_level_2"), 0, 255),
					"region" => substr((string)$subscription->getCompany("address_level_1"), 0, 255),
					"postal_code" => substr((string)$subscription->getCompany("post_code"), 0, 50),
					"country" => substr((string)$subscription->getCompany("country")['name'], 0, 255),
				],
			],
			"phones" => [
				[
					"phone_type" => "DEFAULT",
					"phone_number" => substr((string)$subscription->getCompany("phone"), 0, 50),
				],
			],
			"contact_persons" => $this->getContactPersons($subscription),
		];
	}
}
