<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SwisdkMessenger {
		public static function send($message)
		{
			$message->send();
		}
	}

	abstract class Message {
		protected $dbobj;

		public function bind($dbobj)
		{
			$this->dbobj = $dbobj;
		}

		public function &dbobj()
		{
			return $this->dbobj;
		}

		abstract public function send();
	}

	/**
	 * EmailMessage needs at least the following DBObject properties to
	 * function properly:
	 *
	 * email_recipient
	 * email_subject
	 * email_message
	 *
	 * The following properties are respected too:
	 *
	 * email_from (defaults to info@HTTP_HOST)
	 * email_reply_to (defauls to email_from)
	 */
	class EmailMessage extends Message {
		protected $auto_html = false;

		public function __construct($dbo = null)
		{
			if($dbo)
				$this->dbobj = $dbo;
			else
				$this->dbobj = DBObject::create('Email');
		}

		public function send()
		{
			if(!$this->sanity_check())
				return;

			if(!$this->dbobj->from)
				$this->dbobj->from = Swisdk::config_value('core.name', 'Messenger')
					.' <info@'.preg_replace('/^www\./', '',
						Swisdk::config_value('runtime.request.host')).'>';

			if(!$this->dbobj->reply_to)
				$this->dbobj->reply_to = $this->dbobj->from;

			if($this->auto_html)
				$this->send_mail_html();
			else
				$this->send_mail_text();
		}

		protected function send_mail_html($to, $sub, $msg, $cc, $bcc)
		{
			require_once MODULE_ROOT.'inc.smarty.php';
			require_once SWISDK_ROOT.'lib/contrib/htmlMimeMail5/htmlMimeMail5.php';

			$mail = new htmlMimeMail5();

			$mail->setHeadCharset('UTF-8');
			$mail->setTextCharset('UTF-8');
			$mail->setHTMLCharset('UTF-8');

			$mail->setFrom($this->dbobj->from);
			$mail->setSubject($this->dbobj->subject);
			$mail->setText($this->dbobj->message);

			$smarty = new SwisdkSmarty();
			foreach($this->dbobj as $k => $v)
				$smarty->assign($this->dbobj->shortname($k), $v);

			$mail->setHTML($smarty->fetch_template('base.mail'));

			$mail->setCc($this->dbobj->recipient_cc);
			$mail->setBcc($this->dbobj->recipient_bcc);
			$mail->setHeader('Reply-To', $this->dbobj->reply_to);

			$mail->send(array($this->dbobj->recipient));
		}

		protected function send_mail_text($to, $sub, $msg, $cc, $bcc)
		{
			$headers = implode("\r\n", array(
					'From: '.$this->dbobj->from,
					'Cc: '.$this->dbobj->recipient_cc,
					'Bcc: '.$this->dbobj->recipient_bcc,
					'Reply-To: '.$this->dbobj->reply_to,
					'X-Mailer: SWISDK 2.0 http://spinlock.ch/projects/swisdk/'
				));
			if(function_exists('mb_language') && function_exists('mb_send_mail')) {
				mb_language('uni');
				mb_send_mail($this->dbobj->recipient, $this->dbobj->subject,
					$this->dbobj->message, $headers);
			} else
				mail($this->dbobj->recipient, $this->dbobj->subject,
					$this->dbobj->message, $headers);
		}

		protected function sanity_check()
		{
			if(strpos($this->dbobj->recipient, "\n")
					|| strpos($this->dbobj->recipient_cc, "\n")
					|| strpos($this->dbobj->recipient_bcc, "\n")
					|| strpos($this->dbobj->from, "\n")
					|| strpos($this->dbobj->reply_to, "\n")
					|| strpos($this->dbobj->subject, "\n"))
				return false;
			return true;
		}
	}

	class SmartyEmailMessage extends EmailMessage {
		protected $template;

		public function __construct($template, $dbo = null)
		{
			$this->template = $template;
			parent::__construct($dbo);
		}

		public function send()
		{
			require_once MODULE_ROOT.'inc.smarty.php';
			$smarty = new SwisdkSmarty();
			$smarty->assign('email', $this->dbobj->data());
			$text = $smarty->fetch($this->template);

			// take the first line as subject, the rest as message body
			$pos = strpos($text, "\n");
			$this->send_mail($this->dbobj->recipient,
				substr($text, 0, $pos),
				substr($text, $pos+1));
		}
	}

	/*
	class OtherTransportMessage {
		public function send()
		{
			...
		}
	}
	*/

?>
