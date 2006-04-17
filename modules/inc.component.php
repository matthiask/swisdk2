<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	interface IComponent {
		public function run();
	}

	interface IHtmlComponent extends IComponent {
		public function html();
	}

	interface IFeedComponent extends IComponent {
		public function send_feed();
	}

	abstract class CommandComponent implements IHtmlComponent {
		protected $_html;
		protected $args;

		public function run()
		{
			// TODO make arguments assignable?
			$this->args = Swisdk::arguments();
			if(count($this->args)) {
				$cmd = $this->args[0];
				if(($cmd = $this->args[0])
						&& method_exists($this, 'cmd_'.$cmd)) {
					array_shift($this->args);
					$this->_html = $this->{'cmd_'.$cmd}();
					return;
				}
			}
			$this->_html = $this->cmd_index();
		}

		public function html()
		{
			return $this->_html;
		}

		public function goto($tok=null)
		{
			//FIXME LF attack
			header('Location: http://'
				.Swisdk::config_value('request.host')
				.Swisdk::config_value('runtime.controller.url')
				.$tok);
		}

		abstract protected function cmd_index();
	}

	/**
	 * further hints
	 */
	interface ISmartyAware {
		public function set_smarty_variables(&$smarty);
	}



?>
