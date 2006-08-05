<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SwisdkMessenger {
		public function send($message)
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
				$this->dbobj->message);
		}

		protected function send_mail($to, $sub, $msg)
		{
			mail($to, $sub, $msg,
				implode("\r\n", array(
					'From: Messenger <messenger@'
						.Swisdk::config_value('runtime.request.host').'>',
					'Cc: ',
					'Bcc: '.Swisdk::config_value('core.admin_email'),
					'Reply-To: '.Swisdk::config_value('core.admin_email'),
					'X-Mailer: SWISDK 2.0 http://spinlock.ch/projects/swisdk/'
				))
			);

		}

		protected function sanity_check()
		{
			if(strpos($this->dbobj->recipient, "\n")
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
