<?php


namespace App\Common\Email;

use App\Common\Common;
use App\Common\str;

/**
 * Class Email
 * A wrapper for the Swift_Message() class.
 * @package App\Common
 */
class Email extends Common {
	/**
	 * New-line symbol to use in text emails.
	 */
	const CRLF = "\n";

	/**
	 * Contains the swift message envelope
	 * @var \Swift_Message
	 */
	private $envelope;
	/**
	 * @var bool
	 */
	private $silent;

	/**
	 * Email constructor.
	 *
	 * @param null $a
	 */
	public function __construct($a = NULL)
	{
		//		parent::__construct();
		# We don't actually need this. And enabled, it will cause a problem if there ever is a mySQL outage.

		# Create the envelope that will contain the email metadata and message
		$this->envelope = new \Swift_Message();

		# Set the From address with an associative array
		$this->envelope->setFrom([$_ENV['email_username'] => $_ENV['email_name']]);

		if(is_array($a)){
			foreach($a as $key => $val){
				$this->{$key}($val);
			}
		}

		return true;
	}

	public function from(string $from): object
	{
		$this->envelope->setFrom([$_ENV['email_username'] => "{$from} via {$_ENV['email_name']}"]);

		return $this;
	}


	/**
	 * By passing a template name and an array of variables,
	 * populate both the subject and the message body
	 * with a populated template.
	 *
	 * @param string     $template_name
	 * @param array|null $variables
	 *
	 * @return $this|object
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

		# Get (and set) the message
		if(!$this->message($template_factory->generateMessage())){
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

		# Set the subject line
		$this->envelope->setSubject($subject);

		return $this;
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

		# Converts images to CIDs
		$message = $this->convertImages($message);

		$this->envelope->setContentType('text/html');
		$this->envelope->setCharset('utf-8');

		# Set the HTML body
		$this->envelope->setBody($message, 'text/html');

		# Create and set the text version
		$this->envelope->addPart($this->generateTextVersion($message), 'text/plain');

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
	 * @param $message
	 *
	 * @return string
	 * @link https://stackoverflow.com/a/3235781/429071
	 */
	private function removeHTMLComments($message): string
	{
		return preg_replace('/<!--(.*)-->/Uis', '', $message);
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
			if(substr($image_path, 0, 1) == "/"){
				//If this is a local file
				$image_file = \Swift_Image::fromPath($image_path);
			} else {
				//if this is an external file (even if it's hosted locally)

				# Get the image content
				if(!$data = @file_get_contents($image_path)){
					throw new \Exception("An image in the email template with the source link <code>{$image_path}</code> could not be accessed. The email has not been sent.");
				}

				# Get the filename
				$filename = explode("?", basename($image_path))[0];

				# Get the mime type
				$finfo = new \finfo(FILEINFO_MIME_TYPE);
				$mime_type = $finfo->buffer($buffer);

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

		return $this;
	}

	/**
	 * Set the TO recipients
	 *
	 * @param array|string $to
	 *
	 * @return $this
	 * @throws \Exception
	 * @throws \Exception
	 */
	public function to($to)
	{
		if(is_string($to) && !empty($to)){
			$this->envelope->setTo([$to => $to]);
		} else if(str::isAssociativeArray($to)){
			$this->envelope->setTo($to);
		} else if(str::isNumericArray($to)){
			foreach($to as $t){
				if(is_string($t)){
					$tos[] = $t;
				} else {
					foreach($t as $email => $name){
						$tos[$email] = $name;
					}
				}
			}
			$this->envelope->setTo($tos);
		} else {
			throw new \Exception("An email was attempted sent without a recipient.");
		}

		return $this;
	}

	/**
	 * Set the CC recipients
	 *
	 * @param array|string $cc
	 *
	 * @return $this
	 */
	public function cc($cc)
	{
		if(is_string($cc) && !empty($cc)){
			$this->envelope->setCc([$cc => $cc]);
		} else if(str::isAssociativeArray($cc)){
			$this->envelope->setCc($cc);
		} else if(str::isNumericArray($cc)){
			foreach($cc as $t){
				if(is_string($t)){
					$tos[] = $t;
				} else {
					foreach($t as $email => $name){
						$tos[$email] = $name;
					}
				}
			}
			$this->envelope->setCc($tos);
		}

		return $this;
	}

	/**
	 * Set the BCC recipients
	 *
	 * @param array|string $bcc
	 *
	 * @return $this
	 */
	public function bcc($bcc)
	{
		if(is_string($bcc) && !empty($bcc)){
			$this->envelope->setBcc([$bcc => $bcc]);
		} else if(str::isAssociativeArray($bcc)){
			$this->envelope->setBcc($bcc);
		} else if(str::isNumericArray($bcc)){
			foreach($bcc as $t){
				if(is_string($t)){
					$tos[] = $t;
				} else {
					foreach($t as $email => $name){
						$tos[$email] = $name;
					}
				}
			}
			$this->envelope->setBcc($tos);
		}

		return $this;
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
	 * @return bool
	 */
	public function send($a = NULL)
	{
		if(is_array($a)){
			foreach($a as $key => $val){
				$this->{$key}($val);
			}
		}

		# Ensure no emails are sent from the dev environment
		if($_SERVER['SERVER_ADDR'] === $_ENV['dev_ip']){
			// === because when accessed from the CLI, SERVER_ADDR = NULL, and if the dev_ip is NOT set (""), will result is a false positive match
			$this->log->info([
				"icon" => "ban",
				"title" => "Email not sent",
				"message" => "Emails will not be sent from the development environment [{$_ENV['dev_ip']}].",
			]);
			return true;
		}

		# Create the Transport
		$transport = new \Swift_SmtpTransport();
		$transport->setHost($_ENV['email_smtp_host']);
		$transport->setPort(587);
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


		# Send the email
		$mailer->send($this->envelope);

		return true;
	}
}