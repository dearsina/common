<?php

namespace App\Common\OAuth2;

class Prototype extends \App\Common\Prototype {
	/**
	 * The provider is the name of the provider, e.g. "google",
	 * and it's used to identify the provider in the oauth_token
	 * table.
	 */
	const PROVIDER = NULL;

	/**
	 * The oauth_token is the token that is used to authenticate
	 * the user with the provider. It's an object that contains
	 * the access token, the refresh token, and the expiry time.
	 *
	 * We store it in this class global in the event that it
	 * expires during a call, and we need to refresh it.
	 *
	 * @var array
	 */
	public array $oauth_token;
}