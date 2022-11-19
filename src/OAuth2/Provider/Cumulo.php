<?php

namespace App\Common\OAuth2\Provider;

use App\Common\OAuth2\OAuth2Handler;
use App\Common\SQL\Factory;
use App\Common\str;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\GenericProvider;

class Cumulo extends \App\Common\OAuth2\Prototype implements \App\Common\OAuth2\ProviderInterface {
	/**
	 * Hacky way to ensure the token is always fresh.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function keepTokenAlive(): bool
	{
		# Get the OAuth2 token
		if(!$oauth_token = self::getOauthToken()){
			$this->log->error([
				"title" => "No token found",
				"message" => "No token found to refresh."
			]);

			return false;
		}

		# Get the provider (object)
		$provider = self::getOAuth2ProviderObject();

		# Get a new token based on the refresh token
		$grant = new RefreshToken();

		try{
			$token = $provider->getAccessToken($grant, ['refresh_token' => $oauth_token['refresh_token']]);
		}

		catch(\Exception $e){
			$this->log->error([
				"title" => "Token not refreshed",
				"message" => "Failed to refresh the access token: {$e->getMessage()}"
			]);

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
			"user_id" => false
		]);

		$this->log->success([
			"title" => "Refreshed token",
			"message" => "Successfully refreshed the access token."
		]);

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public static function getOAuth2ProviderObject(?bool $force_refresh_token = NULL): object
	{
		$env = str::isDev() ? "dev" : "prod";

		return new GenericProvider([
			'clientId' => $_ENV["cumulo_{$env}_client_id"],
			'clientSecret' => $_ENV["cumulo_{$env}_client_secret"],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			"accessType" => "offline",
			"prompt" => $force_refresh_token ? "consent" : NULL, // Forces consent (and a refresh token) every time
			"scopes" => "offline ck.key.get ck.key.sign",
			'urlAuthorize' => $_ENV["cumulo_{$env}_auth_url"],
			'urlAccessToken' => $_ENV["cumulo_{$env}_token_url"],
			"urlResourceOwnerDetails" => $_ENV["cumulo_{$env}_resource_url"],
		], [
			'optionProvider' => new HttpBasicAuthOptionProvider(),
		]);
	}

	/**
	 * Get the one Cumulo OAuth2 token.
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	static public function getOauthToken(): ?array
	{
		return Factory::getInstance()->select([
			"table" => "oauth_token",
			"where" => [
				"provider" => "cumulo",
			],
			"limit" => 1,
		]);
	}

	private function getEnv(): string
	{
		return str::isDev() ? "dev" : "prod";
	}

	/**
	 * Returns the SetaSign Cumulo Client.
	 *
	 * @return object
	 */
	private function getClient(): object
	{
		# Get the OAuth2 token
		$oauth_token = self::getOauthToken();

		# Ensure it's fresh
		OAuth2Handler::ensureTokenIsFresh($oauth_token);

		return new \setasign\SetaPDF\Signer\Module\Cumulo\Client(
			$_ENV["cumulo_{$this->getEnv()}_api_url"],
			$oauth_token['token'],
			new \GuzzleHttp\Client([
				'allow_redirects' => true,
			]),
			new \Http\Factory\Guzzle\RequestFactory(),
			new \Http\Factory\Guzzle\StreamFactory()
		);
	}

	/**
	 * Given a list of certificates from a client,
	 * return the second certificate ID.
	 *
	 * @param object $client
	 *
	 * @return string
	 */
	private function getCertificateId(object $client): string
	{
		$certificateInfos = $client->listCertificates();
		//		return $certificateInfos[1]['id'];
		//		// Not quite sure why it's taking the _second_ certificate ID
		$certificate = end($certificateInfos);
		return $certificate['id'];
	}

	/*
	 * Generate the OTP string from an OTP secret.
	 * This must be a TOTP not a HTOP. And the period cannot be altered.
	 */
	private function getOtp(): string
	{
		//		$hotp = \OTPHP\HOTP::create($_ENV["cumulo_{$this->getEnv()}_otp_secret"]);
		//		return $hotp->at(time());

		$totp = \OTPHP\TOTP::create($_ENV["cumulo_{$this->getEnv()}_otp_secret"]);
		return $totp->now();
	}

	/**
	 * The algorithm used to generate the certificate.
	 *
	 * Can also be RS384 and RS512, but RS256 (SHA-256) is sufficient
	 * @link https://cryptobook.nakov.com/cryptographic-hash-functions/secure-hash-algorithms
	 */
	const ALGO = "RS256";

	/**
	 * The metadata array can contain:
	 * - `reason`
	 * - `location`
	 * - `contact_info`
	 * - `name`
	 * - `time_of_signing`
	 *
	 * @param array $file
	 * @param array $metadata
	 *
	 * @throws \Exception
	 */
	public function sign(array $file, array $metadata = []): void
	{
		# Get the SetaSign client object
		$client = $this->getClient();

		try {
			# Create the signature module
			$module = new \setasign\SetaPDF\Signer\Module\Cumulo\Module($client);
			$module->setCertificateId($this->getCertificateId($client));
			$module->setOtp($this->getOtp());
			$module->setSigningAlgorithm(self::ALGO);

			# Create a writer instance
			$writer = new \SetaPDF_Core_Writer_String();

			# Create the document instance
			$document = \SetaPDF_Core_Document::loadByFilename($file['tmp_name'], $writer);

			// Create the signer instance
			$signer = new \SetaPDF_Signer($document);

			$this->setMetaData($signer, $metadata);

			# Sign the document and send the final document to the initial writer
			$signer->sign($module);

			file_put_contents($file['tmp_name'], $writer->getBuffer());
		}

		catch(\Exception $e) {
			throw new \Exception("An error occurred when trying to sign the {$file['name']} PDF: {$e->getMessage()}");
		}
	}

	/**
	 * Sets the document metadata, if applicable.
	 *
	 * @param SetaPDF_Signer $signer
	 * @param array          $metadata
	 */
	public function setMetaData(\SetaPDF_Signer &$signer, array $metadata = []): void
	{
		if(!$metadata){
			return;
		}

		if($metadata['reason']){
			$signer->setReason($metadata['reason']);
		}

		if($metadata['location']){
			$signer->setLocation($metadata['location']);
		}

		# These two don't seem to show up in the final PDF
		if($metadata['contact_info']){
			$signer->setContactInfo($metadata['contact_info']);
		}
		if($metadata['name']){
			$signer->setName($metadata['name']);
		}

		# Needs to be a timestamp?
		if($metadata['time_of_signing']){
			$signer->setTimeOfSigning($metadata['time_of_signing']);
		}
	}

	public function signLtv(array $file, array $metadata = [], ?int $rerun = 0): void
	{
		# Get the SetaSign client object
		$client = $this->getClient();

		try {
			$certificate_id = $this->getCertificateId($client);

			# Create the signature module
			$module = new \setasign\SetaPDF\Signer\Module\Cumulo\Module($client);
			$module->setCertificateId($certificate_id);
			$module->setOtp($this->getOtp());
			$module->setSigningAlgorithm(self::ALGO);

			// create a writer instance
			$writer = new \SetaPDF_Core_Writer_String();
			$tmpWriter = new \SetaPDF_Core_Writer_TempFile();
			// create the document instance
			$document = \SetaPDF_Core_Document::loadByFilename($file['tmp_name'], $tmpWriter);

			// create the signer instance
			$signer = new \SetaPDF_Signer($document);
			$signer->setAllowSignatureContentLengthChange(false);
			$signer->setSignatureContentLength(26000);

			$chainCertificates = \SetaPDF_Signer_Pem::extract($client->getCertificateSigningChain($certificate_id));
			//    var_dump('<pre>');
			//    var_dump($chainCertificates);
			//    /**
			//     * @var SetaPDF_Signer_X509_Certificate $chainCertificate
			//     */
			//    foreach ($chainCertificates as $chainCertificate) {
			//        var_dump($chainCertificate->getSubjectName(), $chainCertificate->getIssuerName(), '<hr/>');
			//    }
			//    die();
			$module->setExtraCertificates($chainCertificates);

			# Set metadata
			$this->setMetaData($signer, $metadata);

			$field = $signer->getSignatureField();
			$fieldName = $field->getQualifiedName();
			$signer->setSignatureFieldName($fieldName);

			$signer->sign($module);

			$document = \SetaPDF_Core_Document::loadByFilename($tmpWriter->getPath(), $writer);

			// Create a collection of trusted certificats:
			$trustedCertificates = new \SetaPDF_Signer_X509_Collection($chainCertificates);

			// Create a collector instance
			$collector = new \SetaPDF_Signer_ValidationRelatedInfo_Collector($trustedCertificates);

			try {
				// Collect revocation information for this field
				$vriData = $collector->getByFieldName($document, $fieldName);
			} catch (Throwable $e) {
				// Debug process for resolving verification related information
				echo '<h1>Error on collecting the ValidationRelatedInfo</h1><pre>';
				foreach ($collector->getLogger()->getLogs() as $log) {
					echo str_repeat(' ', $log->getDepth() * 4) . $log . "\n";
				}
				die();
			}

			$dss = new \SetaPDF_Signer_DocumentSecurityStore($document);
			$dss->addValidationRelatedInfoByFieldName(
				$fieldName,
				$vriData->getCrls(),
				$vriData->getOcspResponses(),
				$vriData->getCertificates()
			);

			$document->save()->finish();

			file_put_contents($file['tmp_name'], $writer->getBuffer());
		}

		catch(\Exception $e) {
			$error_message = $e->getMessage();

			# If the issue is that we're signing too many documents in a single window, wait a second and try again
			if(strpos($error_message, "2fa-totp-reuse")){
				$rerun++;
				sleep($rerun);
				$this->signLtv($file, $metadata, $rerun);
				return;
			}

			# Admin message
			$this->log->error([
				"title" => "Error signing document",
				"message" => "An error occurred when trying to sign the {$file['name']} PDF: $error_message
				The document has still been produced, but the signatures have not been sealed. The signing was
				attempted re-run {$rerun} times.",
				"display" => false,
			]);

			# Public message
			$this->log->error([
				"log" => false,
				"title" => "Error signing document",
				"message" => "An error occurred when trying to sign the {$file['name']} PDF.
				The document has still been produced, but the signatures have not been sealed. Please re-produce the
				document for the seals to be attached properly. Apologies for the inconvenience."
			]);

			return;
		}

		if($rerun){
			$this->log->error([
				"title" => "Reran signing",
				"message" => "Had to re-run singing {$rerun} times to sign the {$file['name']} PDF.",
				"display" => false,
			]);
		}
	}
}