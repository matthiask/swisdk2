<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.component.php';

	abstract class Site implements IComponent {
		public function __construct()
		{
			// nothing to do
		}
	}

	abstract class CommandSite extends Site implements IHtmlComponent {
		protected $base_url = null;
		protected $_html;

		public function run()
		{
			$args = Swisdk::arguments();
			if(count($args) && $cmd = array_shift($args)) {
				$method = 'cmd_' . $cmd;
				if(method_exists($this, $method)) {
					$this->_html = $this->$method();
					return;
				}
			}
			$this->_html = $this->cmd_index();
		}

		public function html()
		{
			return $this->_html;
		}

		public function goto($cmd)
		{
			header('Location: '.$this->base_url.$cmd);
		}

		abstract public function cmd_index();
	}

	class ComponentRunnerSite extends Site {
		public function __construct($component=null)
		{
			if(is_object($component))
				$this->component = $component;
			else if($component)
				$this->component = new $component;
			else
				$this->component = new $this->class;
			parent::__construct();
		}

		protected $component;
		protected $class;
		
		public function run()
		{
			$this->component->run();
			/*
			if($this->component instanceof IPermissionComponent)
				...
			*/
			$handler = new HtmlOutputHandler();
			$handler->handle($this->component);
		}
	}

	abstract class OutputHandler {
		abstract public function handle(&$component);
	}

	class HtmlOutputHandler extends OutputHandler {
		public function handle(&$component)
		{
			echo $component->html();
		}
	}

	class HtmlTemplateOutputHandler extends HtmlOutputHandler {
		public function handle(&$component)
		{
			$tmpl = file_get_contents(CONTENT_ROOT.'template.tpl');
			print str_replace('__CONTENT__', $component->html(), $tmpl);
		}
	}

	class SmartyOutputHandler extends HtmlOutputHandler {
		public function __construct()
		{
			require_once MODULE_ROOT.'inc.smarty.php';
			$this->smarty = new SwisdkSmarty();
		}

		public function smarty()
		{
			return $this->smarty;
		}

		public function handle(&$component)
		{
			$this->smarty->assign('content', $component->html());
			if($component instanceof ISmartyAware)
				$component->set_smarty_variables($this->smarty);
			$this->smarty->display('templates/main.tpl.html');
		}

		protected $smarty;
	}

?>
