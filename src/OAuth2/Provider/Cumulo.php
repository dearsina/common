<?php

namespace App\Common\OAuth2\Provider;

use App\Common\OAuth2\OAuth2Handler;
use App\Common\SQL\Factory;
use App\Common\str;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\GenericProvider;

class Cumulo extends \App\Common\OAuth2\Prototype implements \App\Common\OAuth2\ProviderInterface {

	/**
	 * @inheritDoc
	 */
	public static function getOAuth2ProviderObject(): object
	{
		$env = str::isDev() ? "dev" : "prod";

		return new GenericProvider([
			'clientId' => $_ENV["cumulo_{$env}_client_id"],
			'clientSecret' => $_ENV["cumulo_{$env}_client_secret"],
			'redirectUri' => "https://app.{$_ENV['domain']}/oauth2.php",
			"accessType" => "offline",
			"prompt" => "consent", // Forces consent (and a refresh token) every time
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
		return $certificateInfos[1]['id'];
		// Not quite sure why it's taking the _second_ certificate ID
	}

	/*
	 * Generate the OTP string from an OTP secret.
	 */
	private function getOtp(): string
	{
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
			$writer = new SetaPDF_Core_Writer_String();

			# Create the document instance
			$document = SetaPDF_Core_Document::loadByFilename($fileToSign, $writer);

			// Create the signer instance
			$signer = new SetaPDF_Signer($document);

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
	public function setMetaData(SetaPDF_Signer &$signer, array $metadata = []): void
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

	public function signLtv(array $file, array $metadata = []): void
	{
		# Get the SetaSign client object
		$client = $this->getClient();

		try {
			# Create the signature module
			$module = new \setasign\SetaPDF\Signer\Module\Cumulo\Module($client);
			$module->setCertificateId($this->getCertificateId($client));
			$module->setOtp($this->getOtp());
			$module->setSigningAlgorithm(self::ALGO);

			// create a writer instance
			$writer = new SetaPDF_Core_Writer_String();
			$tmpWriter = new SetaPDF_Core_Writer_TempFile();
			// create the document instance
			$document = SetaPDF_Core_Document::loadByFilename($fileToSign, $tmpWriter);

			// create the signer instance
			$signer = new SetaPDF_Signer($document);
			$signer->setAllowSignatureContentLengthChange(false);
			$signer->setSignatureContentLength(26000);

			$chainCertificates = SetaPDF_Signer_Pem::extract($client->getCertificateSigningChain($_POST['certificateId']));
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
			$trustedCertificates = new SetaPDF_Signer_X509_Collection($chainCertificates);

			// Create a collector instance
			$collector = new SetaPDF_Signer_ValidationRelatedInfo_Collector($trustedCertificates);

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

			$dss = new SetaPDF_Signer_DocumentSecurityStore($document);
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
			throw new \Exception("An error occurred when trying to sign the {$file['name']} PDF: {$e->getMessage()}");
		}
	}



	/**
	 * The below methods don't apply
	 */

	/**
	 * @inheritDoc
	 */
	public function createFolder(string $folder_name, ?string $parent_folder_id): ?string
	{
		// TODO: Implement createFolder() method.
	}

	/**
	 * @inheritDoc
	 */
	public function uploadFile(array $file, ?string $parent_folder_id): ?string
	{
		// TODO: Implement uploadFile() method.
	}

	/**
	 * @inheritDoc
	 */
	public function folderExists(string $folder_name, ?string $parent_folder_id): ?string
	{
		// TODO: Implement folderExists() method.
	}

	/**
	 * @inheritDoc
	 */
	public function fileExists(string $file_name, ?string $parent_folder_id): ?array
	{
		// TODO: Implement fileExists() method.
	}

	/**
	 * @inheritDoc
	 */
	public function getFolderName(?string $folder_id): ?string
	{
		// TODO: Implement getFolderName() method.
	}

	/**
	 * @inheritDoc
	 */
	public function getFolders(string $parent_id, ?string $folder_id = NULL): ?array
	{
		// TODO: Implement getFolders() method.
	}
}