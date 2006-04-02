<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class Site {
		public function __construct()
		{
			// nothing to do
		}
		
		abstract public function run();
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
			$er = error_reporting(E_ALL); // be sure not to report E_STRICT
			require_once SWISDK_ROOT . 'lib/smarty/libs/Smarty.class.php';
			$this->smarty = new Smarty();
			$this->smarty->compile_dir = SWISDK_ROOT . 'lib/smarty/templates_c';
			$this->smarty->cache_dir = SWISDK_ROOT . 'lib/smarty/cache';
			$this->smarty->template_dir = CONTENT_ROOT;
			//$this->config_dir
			$this->caching = false;
			$this->security = false;
			error_reporting($er);
		}

		public function smarty()
		{
			return $this->smarty;
		}

		public function handle(&$component)
		{
			$er = error_reporting(E_ALL);
			$this->smarty->assign('content', $component->html());
			$this->smarty->display('templates/main.tpl.html');
			error_reporting($er);
		}

		protected $smarty;
	}

?>
