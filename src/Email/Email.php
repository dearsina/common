<?php


namespace App\Common\Email;

use App\Common\EmailWrapper\EmailWrapper;
use App\Common\Exception\BadRequest;
use App\Common\Log;
use App\Common\OAuth2\OAuth2Handler;
use App\Common\Prototype;
use App\Common\str;

/**
 * Class Email
 * A wrapper for the Swift_Message() class.
 * @package App\Common
 */
class Email extends Prototype {
	/**
	 * New-line symbol to use in text emails.
	 */
	const CRLF = "\n";

	/**
	 * Contains the swift message envelope
	 * @var \Swift_Message
	 */
	public $envelope;

	/**
	 * @var bool
	 */
	private ?bool $silent = NULL;

	/**
	 * Contains the format.
	 *
	 * @var array
	 */
	private ?array $format = NULL;
	private ?array $oauth_token = NULL;

	/**
	 * Any custom headers.
	 *
	 * @var array|null
	 */
	private ?array $headers = NULL;
	/**
	 * The subject string
	 * @var string
	 */
	private ?string $subject = NULL;
	private ?string $htmlBody = NULL;
	private ?string $textBody = NULL;
	private ?array $attachments = NULL;
	/**
	 * Will contain an array of
	 * email => name pairs of
	 * email addresses.
	 *
	 * @var array
	 */
	private array $to = [];
	private ?array $cc = [];
	private ?array $bcc = [];

	/**
	 * Email constructor.
	 *
	 * @param null $a
	 */
	public function __construct(?string $oauth_token_id = NULL)
	{
		# If an OAuth2 token has been passed and can be loaded, load it
		if($oauth_token_id){
			$this->oauth_token = $this->info("oauth_token", $oauth_token_id);
			// We're still going to load the SwiftMessage as a fallback
		}

		# Create the envelope that will contain the email metadata and message
		$this->envelope = new \Swift_Message();

		# Set a unique message ID
		$headers = $this->envelope->getHeaders();
		$headers->addIdHeader('Message-ID', str::uuid() . "@" . $_ENV['domain']);
		// To avoid the "-0.001	MSGID_FROM_MTA_HEADER	Message-Id was added by a relay" error from SpamAssassin

		# Set the From address with an associative array
		$this->envelope->setFrom([$_ENV['email_username'] => $_ENV['email_name']]);

		# Set the default email format (include the OAuth token if one is set)
		$this->format = array_merge(
			EmailWrapper::$defaults,
			["oauth_token" => $this->oauth_token]);
	}

	public function format(?array $format = NULL): object
	{
		# Set the email format
		$this->format = $format ?: EmailWrapper::$defaults;

		return $this;
	}

	/**
	 * @param string $from
	 *
	 * @return $this
	 */
	public function from(string $from): object
	{
		$this->envelope->setFrom([$_ENV['email_username'] => "{$from} via {$_ENV['email_name']}"]);

		return $this;
	}

	/**
	 * The reply to is useful so that replies to emails sent on behalf of our clients
	 * gets sent directly back to our clients.
	 *
	 * @param string|null $email
	 * @param string|null $name
	 *
	 * @return $this
	 */
	public function replyTo(?string $email, ?string $name = NULL): object
	{
		# Ensure an email address is included
		if(!$email){
			//We don't check for validity because it seems to error?
			return $this;
		}

		$this->envelope->setReplyTo($email, $name);

		return $this;
	}

	/**
	 * By passing a template name and an array of variables,
	 * populate both the subject and the message body
	 * with a populated template.
	 *
	 * @param string     $template_name
	 * @param array|null $variables The entire email_template table row
	 *
	 * @return $this|object
	 * @throws \Exception
	 */
	public function template(string $template_name, ?array $variables = NULL): object
	{
		# Get the right template factory (App, failing that, Common)
		$template_factory_class = str::findClass("Template");

		# Set up the factory
		$template_factory = new $template_factory_class($variables);

		# Set the template
		$template_factory->setTemplate($template_name);

		# Get (and set) the subject
		if(!$this->subject($template_factory->generateSubject())){
			throw new \Exception("No subject generated using the {$template_name} template.");
		}

		# Add the (potential) oauth_token to the format variables
		$this->format['oauth_token'] = $this->oauth_token;

		# Get (and set) the message
		if(!$this->message($template_factory->generateMessage($this->format))){
			throw new \Exception("No message generated using the {$template_name} template.");
		}

		return $this;
	}

	/**
	 * Set the subject
	 *
	 * @param string $subject
	 *
	 * @return $this|bool
	 * @throws \Exception
	 */
	public function subject(string $subject)
	{
		if(!$subject){
			throw new \Exception("An email was attempted sent without a subject line.");
		}

		# Set the subject line for the Swift Message
		$this->envelope->setSubject($subject);

		# Set the subject class variable
		$this->subject = $subject;

		return $this;
	}

	public function getSubject(): ?string
	{
		return $this->subject;
	}

	public function getHtmlBody(): ?string
	{
		return $this->htmlBody;
	}

	public function getTo(): array
	{
		return $this->to;
	}

	public function getCc(): ?array
	{
		return $this->cc;
	}

	public function getBcc(): ?array
	{
		return $this->bcc;
	}

	/**
	 * Set the message
	 *
	 * @param string $message
	 *
	 * @return $this|bool
	 * @throws \Exception
	 * @throws \Exception
	 */
	public function message(string $message)
	{
		if(!$message){
			throw new \Exception("An email was attempted sent without a message body.");
		}

		$message = $this->removeHTMLComments($message);

		# Convert images to CIDs
		$message = $this->convertImages($message);

		$this->envelope->setContentType('text/html');
		$this->envelope->setCharset('utf-8');

		# Set the HTML body
		$this->envelope->setBody($message, 'text/html');

		# Create and set the text version
		$this->envelope->addPart($this->generateTextVersion($message), 'text/plain');

		$this->htmlBody = $message;
		$this->textBody = $this->generateTextVersion($message);

		return $this;
	}

	/**
	 * Given a fully formatted HTML email,
	 * returns the text version of the body tag (only).
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	private function generateTextVersion(string $html): string
	{
		$html = preg_replace('/<head>(.*)<\/head>/Uis', '', $html);
		$text = str_replace(["<br>", "<br/>", "<p>", "</p>"], self::CRLF, strip_tags($html, '<br><br/><p>'));
		return $text;
	}

	/**
	 * Strips away HTML comments from the final output so that the
	 * HTML header and footer sections can be richly commented on.
	 * Details on the modifiers:
	 *  - `U` makes it un-greedy and so goes only to the first close comment.
	 *  - `i` makes it case insensitive (Not sure why this is needed here).
	 *  - `s` means that newlines are allowed inside the comments too.
	 *
	 * Excludes [if] comment hacks.
	 *
	 * @param $message
	 *
	 * @return string
	 * @link https://stackoverflow.com/a/3235781/429071
	 */
	private function removeHTMLComments($message): string
	{
		return preg_replace('/<!--([^\[].*)-->/Uis', '', $message);
	}

	/**
	 * Given a complete HTML body message,
	 * extracts and converts both local
	 * and external image links, and
	 * embedded base64 images to CIDs.
	 * Assumes the img tags are in a
	 * `<img src="" [...]>` format.
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	private function convertImages(string $message): string
	{
		# Magic to translate linked images to CID (inline) images.
		$pattern = /** @lang PhpRegExp */
			"/(<img[^>]+src=\"([^\"]+)\"([^>]+)>)/";

		$message = preg_replace_callback($pattern, function($img_tag){
			//for each image link
			$image_path = $img_tag[2];

			# Local file
			if(substr($image_path, 0, 1) == "/"){
				//If this is a local file
				$image_file = \Swift_Image::fromPath($image_path);
			}

			# Base64 image data
			else if(preg_match("/data:([a-z]+\/[a-z]+);base64,(.*)/i", $image_path, $image_data)){
				//Image path is base64 image data

				$content_type = $image_data[1];
				[$type, $ext] = explode("/", $content_type);

				$base64 = $image_data[2];
				$filename = str::id("image") . ".{$ext}";

				$image_file = new \Swift_Image(base64_decode($base64), $filename, $content_type);
			}

			# External file
			else {
				//if this is an external file (even if it's hosted locally)

				# Get the image content
				if(!$data = @file_get_contents($image_path)){
					Log::getInstance()->error([
						"title" => "Email image inaccessible",
						"message" => "An image in the email template with the source link <code>{$image_path}</code> could not be accessed. The email was still sent, but without the image.",
					]);
					// As we're inside a massive preg replace callback, we can't use $this
					return "";
				}

				# Get the filename
				$filename = explode("?", basename($image_path))[0];

				# Get the mime type
				$finfo = new \finfo(FILEINFO_MIME_TYPE);
				$mime_type = $finfo->buffer($data);

				# Create image file
				$image_file = new \Swift_Image($data, $filename, $mime_type);

				# Set the disposition to be inline (not sure if we need this)
				$image_file->setDisposition('inline');
			}

			# Create a CID
			$cid = $this->envelope->embed($image_file);

			# Return the img tag with the CID instead of the file reference
			return "<img src=\"{$cid}\" {$img_tag[3]}>";
		}, $message);

		# Hack to ensure that base64 embedded photos are converted to CIDs so that Gmail will display them
		$pattern = /** @lang PhpRegExp */
			'/<img[^>]+src="data:([^;]+);base64,([^"]+)">/m';

		if(preg_match_all($pattern, $message, $matches)){
			//if there are embedded images
			foreach($matches[2] as $id => $base64){
				//for each base64 encoded image

				# The image content (converted from base64)
				$data = base64_decode($base64);

				# (Random) filename
				$filename = "img_" . rand();

				# Mime type
				$mime_type = $matches[1][$id];

				# Create image file
				$image_file = new \Swift_Image($data, $filename, $mime_type);

				# Create a CID
				$cid = $this->envelope->embed($image_file);

				# Replace the entire src tag with the CID
				$message = str_replace("data:{$matches[1][$id]};base64,{$base64}", $cid, $message);
			}
		}

		return $message;
	}

	public function headers(?array $a = NULL): object
	{
		$this->headers = $a;

		return $this;
	}

	public function getHeaders(): ?array
	{
		return $this->headers;
	}

	public function getAttachments(): ?array
	{
		return $this->attachments;
	}

	/**
	 * Can be a string to a path, or an array of paths,
	 * or an array of `path` and `filename` key/value pairs.
	 *
	 * @param array|string|null $a
	 *
	 * @return $this
	 */
	public function attachments($a = NULL)
	{
		if(!$a){
			return $this;
		}

		if(is_string($a)){
			$attachments[] = [
				"path" => $a,
				"filename" => pathinfo($a)['basename'],
			];
		}

		if(str::isAssociativeArray($a)){
			$attachments[] = $a;
		}

		if(str::isNumericArray($a)){
			$attachments = $a;
		}

		foreach($attachments as $attachment){

			$this->envelope->attach(\Swift_Attachment::fromPath($attachment['path'])->setFilename($attachment['filename']));
		}

		$this->attachments = $attachments;

		return $this;
	}

	/**
	 * @param        $recipient
	 * @param string $type
	 *
	 * @return object
	 * @throws \Exception
	 */
	private function recipient($recipient, string $type = "to"): object
	{
		# If it's just an email address string
		if(is_string($recipient) && !empty($recipient)){
			// Convert it to a numeric array
			$recipient = [[$recipient => $recipient]];
		}

		# If it's a single email => name key-value
		if(str::isAssociativeArray($recipient)){
			// Convert it to a numeric array
			$recipient = [$recipient];
		}

		# Ensure there are recipients
		if(!$recipient){
			// If there are no recipients
			if($type == "to"){
				// Emails *must* have at least a single to-recipient
				throw new \Exception("An email was attempted sent without a recipient.");
			}

			# If there is no CC/BCC, no worries
			return $this;
		}

		# Go through all the email addresses
		foreach($recipient as $t){
			# If the entire row is just a string
			if(is_string($t)){
				$recipients[$t] = $t;
				continue;
			}

			# If the row is both email and name
			foreach($t as $email => $name){
				$recipients[$email] = $name;
			}
		}

		$method = "set" . ucwords($type);
		$this->envelope->{$method}($recipients);
		$this->{$type} = $recipients;

		return $this;
	}

	/**
	 * Set the TO recipients, in one of the following formats:
	 * - Just a single email address
	 * - An array of email addresses
	 * - An array where the *key* is the email address, and the value is the name
	 *
	 * @param array|string $to
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function to($to): object
	{
		return $this->recipient($to, "to");
	}

	/**
	 * Set the CC recipients
	 *
	 * @param array|string $cc
	 *
	 * @return $this
	 */
	public function cc($cc): object
	{
		return $this->recipient($cc, "cc");
	}

	/**
	 * Set the BCC recipients
	 *
	 * @param array|string $bcc
	 *
	 * @return $this
	 */
	public function bcc($bcc): object
	{
		return $this->recipient($bcc, "bcc");
	}

	public function silent()
	{
		$this->silent = true;
	}

	/**
	 * All the email data can be sent
	 * in the send method as an array
	 * also.
	 *
	 * @param null $a
	 *
	 * @return bool Whether the email was sent successfully
	 * @throws BadRequest
	 */
	public function send($a = NULL): bool
	{
		if(is_array($a)){
			foreach($a as $key => $val){
				$this->{$key}($val);
			}
		}

		# Ensure no emails are sent from the dev environment
		if(str::isDev()){
			Log::getInstance()->warning([
				"icon" => "ban",
				"title" => "Email not sent",
				"message" => "Emails will not be sent from the development environment [{$_ENV['dev_ip']}].",
			]);
			Log::getInstance()->info([
				"icon" => "email",
				"title" => $this->envelope->getSubject(),
				"message" => $this->envelope->getBody(),
			]);
			return true;
		}

		# If we're attempting to send from an external Exchange server
		if($this->oauth_token){
			# Load the email sending provider
			$class = OAuth2Handler::getProviderClass($this->oauth_token['provider']);
			$provider = new $class($this->oauth_token);

			# Attempt to send the email
			if($provider->sendEmail($this)){
				// If the email is sent, our job is done
				return true;
			}
			// If that didn't work, send it the old-fashioned way
		}

		# Attempt to send the message
		return $this->sender(1);
	}

	/**
	 * Sends the actual email. Separated out
	 * so that it can be called recursively
	 * if the first attempt fails.
	 *
	 * @param int $tries
	 *
	 * @return bool Whether the email was sent successfully
	 */
	private function sender(int $tries): bool
	{
		# Reuse the mailer if it has already been initiated
		global $mailer;

		if(!$mailer || $tries > 1){
			// If the mailer doesn't exist yet, or if we're on our second or third tries

			# Get a (new) mailer
			$mailer = $this->getMailer();
		}

		try {
			# Send the email
			$mailer->send($this->envelope);
		}

		catch(\Exception $e) {
			# Expected response code 354 but got code "250", with message "250 2.1.0 Sender OK"
			if(strpos($e->getMessage(), "250") !== false){
				\App\Email\Email::notifyAdmins([
					"subject" => "250 2.1.0 Sender OK email error",
					"body" => "Got the following {$e->getCode()} error, after trying " . str::pluralise_if($tries, "time", true) . ": {$e->getMessage()}. The email will be attempted re-sent now.",
					"backtrace" => str::backtrace(true),
				]);
			}

			# Expected response code 250 but got code "432", with message "432 4.3.2 Concurrent connections limit exceeded. Visit https://aka.ms/concurrent_sending for more information.
			else if(strpos($e->getMessage(), "432") !== false){
				\App\Email\Email::notifyAdmins([
					"subject" => "432 4.3.2 Concurrent connections limit exceeded email error",
					"body" => "Got the following {$e->getCode()} error, after trying " . str::pluralise_if($tries, "time", true) . ": {$e->getMessage()}. The email will be attempted re-sent now.",
					"backtrace" => str::backtrace(true),
				]);
			}

			# Failed to authenticate on SMTP server with username "info@kycdd.co.za" using 2 possible authenticators. Authenticator LOGIN returned Connection to tcp://smtp.office365.com:587 Timed Out. Authenticator XOAUTH2 returned Connection to tcp://smtp.office365.com:587 Timed Out
			else if(strpos($e->getMessage(), "Failed to authenticate") !== false){
				\App\Email\Email::notifyAdmins([
					"subject" => "Failed to authenticate email error",
					"body" => "Got the following {$e->getCode()} error, after trying " . str::pluralise_if($tries, "time", true) . ": {$e->getMessage()}. The email will be attempted re-sent now.",
					"backtrace" => str::backtrace(true),
				]);
			}

			# Connection to tcp://smtp.office365.com:587 Timed Out
			else if(strpos($e->getMessage(), "Timed Out") !== false){
				\App\Email\Email::notifyAdmins([
					"subject" => "Timed out email error",
					"body" => "Got the following {$e->getCode()} error, after trying " . str::pluralise_if($tries, "time", true) . ": {$e->getMessage()}. The email will be attempted re-sent now.",
					"backtrace" => str::backtrace(true),
				]);
			}

			else {
				# Collect recipients
				$recipients = array_filter(array_merge($this->envelope->getTo(), $this->envelope->getCc(), $this->envelope->getBcc()));
				if(is_array($recipients)){
					$recipients = implode(", ", $recipients);
				}

				# Notify the admins of any unknown errors
				\App\Email\Email::notifyAdmins([
					"subject" => "Unknown email error",
					"body" => "Got the following {$e->getCode()} error, after trying " . str::pluralise_if($tries, "time", true) . " to send the email to {$recipients}: {$e->getMessage()}",
					"backtrace" => str::backtrace(true),
				]);
			}

			if($tries == 5){
				// Don't try more than 5 times
				Log::getInstance()->error([
					"title" => "Unable to send email",
					"message" => "The system was unable to send your email after {$tries} tries. The following error message has been logged with our engineers: {$e->getMessage()}. Please await their response.",
				]);
				return false;
			}

			# Try again
			sleep($tries);
			$tries++;

			return $this->sender($tries);
		}

		if($tries > 1){
			// If the email was sent successfully on a second/third try, notify admins again
			\App\Email\Email::notifyAdmins([
				"subject" => "Email sent successfully on try {$tries}",
				"body" => "The email was successfully sent after {$tries} tries.",
			]);
		}

		return true;
	}

	private function getMailer(): \Swift_Mailer
	{
		# Create the Transport
		$transport = new \Swift_SmtpTransport();
		$transport->setHost($_ENV['email_smtp_host']);
		$transport->setPort($_ENV['email_smtp_port']);
		$transport->setEncryption("TLS");
		$transport->setUsername($_ENV['email_username']);
		$transport->setPassword($_ENV['email_password']);

		// Create the Mailer using your created Transport
		$mailer = new \Swift_Mailer($transport);

		# Add the DKIM key (if it exists)
		if(file_exists($_ENV['dkim_private_key'])){
			$privateKey = file_get_contents($_ENV['dkim_private_key']);
			$domainName = $_ENV['domain'];
			$selector = 'default';
			$signer = new \Swift_Signers_DKIMSigner($privateKey, $domainName, $selector);
			$this->envelope->attachSigner($signer);
		}

		return $mailer;
	}
}