<?php

namespace App\Common\OAuth2;

use App\Common\Email\Email;

/**
 * An interface for email providers.
 */
interface EmailProviderInterface extends ProviderInterface {
	/**
	 * Sends an email via the email provider.
	 *
	 * @param Email $email
	 *
	 * @return bool
	 */
	public function sendEmail(Email $email): bool;

	/**
	 * Returns the email address of the user, who has given permission to use their account
	 * to send emails.
	 *
	 * @return string|null
	 */
	public function getEmailAddress(): ?string;
	/**
	 * Needs to remain public, because it is called from the OAuth2Handler class,
	 * when refreshing a token.
	 *
	 * @return object
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object;
}