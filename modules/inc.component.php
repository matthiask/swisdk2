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

		public function run($args=null)
		{
			if($args===null)
				$this->args = Swisdk::config_value('runtime.arguments');
			else
				$this->args = $args;
			if(isset($this->args[0])) {
				$cmd = $this->args[0];
				if($cmd && method_exists($this, 'cmd_'.$cmd)) {
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
			redirect('http://'
				.Swisdk::config_value('runtime.request.host')
				.Swisdk::config_value('runtime.controller.url')
				.$tok);
		}

		abstract protected function cmd_index();
	}

	class BasicViewComponent implements IHtmlComponent {
		protected $mSmarty = null;
		protected $mTemplate = "";

		public function __construct( $template , $data , $name = "data" )
		{
			$smarty = $this->getSmartyRef();
			$smarty->assign( $name , $data );
			$this->mTemplate = $template;
		}

		public function getSmartyRef()
		{
			if( $this->mSmarty === null ) {
				require_once SWISDK_ROOT . "modules/inc.smarty.php";
				$this->mSmarty = new SwisdkSmarty();
			}

			return $this->mSmarty;
		}

		public function run()
		{
			return true;
		}

		public function name()
		{
			return "content";
		}

		public function html()
		{
			return $this->mSmarty->fetch( $this->mTemplate );
		}
	}

?>
