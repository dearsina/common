<?php

namespace App\Common\OAuth2;


/**
 * An interface for single sign on providers.
 */
interface SingleSignOnProviderInterface extends ProviderInterface {
	/**
	 * Get an OAuth2 provider object.
	 *
	 * @param bool|null $force_refresh_token
	 *
	 * @return object
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object;

	/**
	 * Get an array containing the organization's properties.
	 * Must include two generic properties: id and name.
	 *
	 * @param array $oauth_token
	 *
	 * @return array|null
	 */
	public function getOrganization(array $oauth_token): ?array;

	/**
	 * Get an array of groups the tenant has access to.
	 *
	 *
	 * @return array|null
	 */
	public function getGroups(): ?array;

	/**
	 * Get an array containing the group's properties.
	 *
	 * @param string $group_id
	 *
	 * @return array|null
	 */
	public function getGroup(string $group_id): ?array;

	/**
	 * @param string $user_id
	 *
	 * @return array|null
	 */
	public function getUser(string $user_id): ?array;

	/**
	 * Get an array of members of a group.
	 * Each member must include a generic property: id
	 *
	 * @param string $group_id
	 *
	 * @return array|null
	 */
	public function getGroupMembers(string $group_id): ?array;

	/**
	 * Subscribe to changes in a group.
	 *
	 * @param string $group_id
	 * @param string $client_state A unique, secret identifier for the client
	 *
	 * @return void
	 */
	public function subscribeToGroupChanges(string $group_id, string $client_state): ?array;

	/**
	 * Subscribe to changes in a user.
	 *
	 * @param string $user_id
	 * @param string $client_state
	 *
	 * @return array|null
	 */
	public function subscribeToUserChanges(string $user_id, string $client_state): ?array;

	/**
	 * Unsubscribe from changes in a group or user.
	 *
	 * @param string $subscription_id
	 *
	 * @return bool|null
	 */
	public function unsubscribeFromNotifications(string $subscription_id): ?bool;
}