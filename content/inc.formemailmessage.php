<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.messenger.php';

	class FormEmailMessage extends EmailMessage {
		protected $form;

		public function __construct($form, $data=null)
		{
			parent::__construct($form->dbobj());
			$this->form = $form;
			if($data)
				$this->dbobj->set_data($data);

			$this->prepare();
		}

		public function prepare()
		{
			$message = $this->walk_box($this->form);

			$message .= $this->format_section('SERVER');
			$srv = array('REMOTE_ADDR', 'HTTP_USER_AGENT');
			foreach($srv as $s)
				if(isset($_SERVER[$s]))
					$message .= $this->format_item(
						$s, $_SERVER[$s]);

			$this->dbobj->message .= $message;
		}

		protected function walk_box($box)
		{
			$output = '';
			foreach($box as $item) {
				$value = $item->value();
				if($item instanceof CheckboxInput)
					$value = $value?'true':'false';
				else if(is_array($value))
					$value = implode(', ', $value);
				$output .= $this->format_item($item->title(), $value);
			}

			$boxes = $box->boxes();
			foreach($boxes as $b) {
				if(strpos($b->name(), 'zzz_')===0)
						continue;
				$t = $b->title();
				if(!$t)
					$t = $b->name();
				$output .= $this->format_section($t);
				$output .= $this->walk_box($b);
			}

			return $output;
		}

		protected function format_section($title)
		{
			return str_repeat('=', 60)."\n".strip_tags($title).":\n";
		}

		protected function format_item($title, $value)
		{
			return str_repeat('-', 60)."\n".strip_tags($title).":\n"
				.$value."\n";
		}
	}

?>
