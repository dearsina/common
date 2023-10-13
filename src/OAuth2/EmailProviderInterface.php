<?php

namespace App\Common\OAuth2;

use App\Common\Email\Email;

interface EmailProviderInterface extends ProviderInterface {
	public function sendEmail(Email $email): bool;
}