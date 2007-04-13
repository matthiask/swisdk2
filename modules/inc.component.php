<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * nearly all classes implement IComponent
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

	interface ISmartyComponent extends IComponent {
		public function set_smarty(&$smarty);
	}

	abstract class CommandComponent implements IHtmlComponent {
		protected $_html;
		protected $args;

		public function run()
		{
			$this->dispatch();
		}

		public function dispatch($command=null)
		{
			if($this->args===null)
				$this->args = Swisdk::config_value('runtime.arguments');

			if(($command===null) && count($this->args))
				$command = array_shift($this->args);

			if($command && method_exists($this, $cmd = 'cmd_'.$command))
				$this->_html = $this->$cmd();
			else {
				array_unshift($this->args, $command);
				$this->_html = $this->cmd_index();
			}
		}

		public function html()
		{
			return $this->_html;
		}

		public function goto($tok=null)
		{
			redirect('http://'
				.Swisdk::config_value('runtime.request.host')
				.Swisdk::config_value('runtime.controller.url')
				.$tok);
		}

		abstract protected function cmd_index();
	}

	define('STATE_START', 1<<0);
	define('STATE_INVALID', 1<<1);
	define('STATE_FINISHED', 1<<2);
	define('STATE_DISPLAYED', 1<<3);
	define('STATE_RUN', 1<<4);

	class StateComponent {
		protected $state;

		public function state()
		{
			return $this->state;
		}

		public function set_state($state)
		{
			$this->state = $state;
			return $this;
		}

		public function add_state($state)
		{
			$this->state |= $state;
		}

		public function remove_state($state)
		{
			$this->state &= ~$state;
		}

		public function has_state($state)
		{
			return ($this->state & $state)==$state;
		}
	}

?>
