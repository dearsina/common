<?php

namespace App\Common\OAuth2;

interface ProviderInterface {
	/**
	 * Returns a given provider's OAuth2 client so that the
	 * OAuth2 token can be collected.
	 *
	 * @return object
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object;

}