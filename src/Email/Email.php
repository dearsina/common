<?php


namespace App\Common\Email;

use App\Common\Common;
use App\Common\str;

/**
 * Class Email
 * A wrapper for the Swift_Message() class.
 *
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
	public function __construct ($a = NULL) {
		parent::__construct();

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

	/**
	 * @param $template
	 * @param $variables
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function template($template, $variables){
		if(!$TemplateClass = str::getClassCase($template)){
			throw new \Exception("No template provided");
		}

		$class = "\\App\\Common\\Email\\Template\\{$TemplateClass}";
		if(!class_exists($class)){
			throw new \Exception("Cannot find the {$TemplateClass} template.");
		}

		$template = new $class($variables);

		if(!$this->subject($template->getSubject())){
			throw new \Exception("No subject generated");
		}

		if(!$this->message($template->getMessageHTML())){
			throw new \Exception("No message generated");
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
	 * @throws \Exception
	 */
	public function subject(string $subject){
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
	public function message(string $message){
		if(!$message){
			throw new \Exception("An email was attempted sent without a message body.");
		}

		# Hack to ensure that base64 embedded photos are converted to CIDs so that Gmail will display them
		$pattern = /** @lang PhpRegExp */'/<img src="data:([^;]+);base64,([^"]+)">/m';
		if(preg_match_all($pattern, $message, $matches)){
			//if there are embedded images
			foreach($matches[2] as $id => $base64){
				//for each base64 encoded image
				$cid = $envelope->embed(new \Swift_Image(base64_decode($base64), "img_".rand(), $matches[1][$id]));
				//create a CID using the decoded data, a random name and the format string from the base64 image
				$message = str_replace("data:{$matches[1][$id]};base64,{$base64}", $cid, $message);
				//replace the entire src tag with the CID
			}
		}

		# Set the HTML body
		$this->envelope->setBody($message, 'text/html');

		# Create and set the text version
		$text = str_replace(array("<br>","<br/>","<p>","</p>"),self::CRLF,strip_tags($message,'<br><br/><p>'));
		$this->envelope->addPart($text, 'text/plain');

		return $this;
	}

	/**
	 * Can be a string to a path, or an array of paths,
	 * or an array of "path" and "filename" key/value pairs.
	 *
	 * @param array|string|null $a
	 *
	 * @return $this
	 */
	public function attachments($a = NULL){
		if(!$a){
			return $this;
		}

		if(is_string($a)){
			$attachments[] = [
				"path" => $a,
				"filename" => pathinfo($a)['basename']
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
	public function to($to){
		if(is_string($to) && !empty($to)){
			$this->envelope->setTo([$to => $to]);
		} else if(str::isAssociativeArray($to)){
			$this->envelope->setTo($to);
		} else if(str::isNumericArray($to)){
			foreach($to as $t){
				$this->envelope->setTo($t);	
			}
		} else {
			throw new \Exception("An email was attempted sent without a recipient.");
		}
		
		return $this;
	}

	/**
	 * Set the CC recipients
	 * @param array|string $cc
	 *
	 * @return $this
	 */
	public function cc($cc){
		if(is_string($cc) && !empty($cc)){
			$this->envelope->setCc([$cc => $cc]);
		} else if(str::isAssociativeArray($cc)){
			$this->envelope->setCc($cc);
		} else if(str::isNumericArray($cc)){
			foreach($cc as $t){
				$this->envelope->setCc($t);
			}
		}

		return $this;
	}

	/**
	 * Set the BCC recipients
	 * @param array|string $bcc
	 *
	 * @return $this
	 */
	public function bcc($bcc){
		if(is_string($bcc) && !empty($bcc)){
			$this->envelope->setBcc([$bcc => $bcc]);
		} else if(str::isAssociativeArray($bcc)){
			$this->envelope->setBcc($bcc);
		} else if(str::isNumericArray($bcc)){
			foreach($bcc as $t){
				$this->envelope->setBcc($t);
			}
		}

		return $this;
	}

	public function silent(){
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
	public function send($a = NULL){
		if(is_array($a)){
			foreach($a as $key => $val){
				$this->{$key}($val);
			}
		}

		# Ensure no emails are sent from the dev environment
		if($_SERVER['SERVER_ADDR'] == $_ENV['dev_ip']){
			$this->log->info([
				"icon" => "ban",
				"title" => "Email not sent",
				"message" => "Emails will not be sent from the development environment [{$_ENV['dev_ip']}]."
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

		# Send the email
		$mailer->send($this->envelope);

		return true;
	}
}