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

			$this->send_mail($this->dbobj->recipient, $this->dbobj->subject,
				$this->dbobj->message, $this->dbobj->recipient_cc,
				$this->dbobj->recipient_bcc);
		}

		protected function send_mail($to, $sub, $msg, $cc, $bcc)
		{
			$from = $this->dbobj->from;
			if(!$from)
				$from = Swisdk::config_value('core.name', 'Messenger')
					.' <info@'.preg_replace('/^www\./', '',
						Swisdk::config_value('runtime.request.host')).'>';
			$reply_to = $this->dbobj->reply_to;
			if(!$reply_to)
				$reply_to = $from;

			$headers = implode("\r\n", array(
					'From: '.$from,
					'Cc: '.$cc,
					'Bcc: '.$bcc,
					'Reply-To: '.$reply_to,
					'X-Mailer: SWISDK 2.0 http://spinlock.ch/projects/swisdk/'
				));
			if(function_exists('mb_language') && function_exists('mb_send_mail')) {
				mb_language('uni');
				mb_send_mail($to, $sub, $msg, $headers);
			} else
				mail($to, $sub, $msg, $headers);

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
