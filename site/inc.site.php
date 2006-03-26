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
			$handler = new HtmlTemplateOutputHandler();
			$handler->handle($this->component);
		}
	}

	abstract class OutputHandler {
		abstract public function handle(&$component);
	}

	class HtmlTemplateOutputHandler extends OutputHandler {
		public function handle(&$component)
		{
			$tmpl = file_get_contents(CONTENT_ROOT.'template.tpl');
			print str_replace('__CONTENT__', $component->html(), $tmpl);
		}
	}

?>
