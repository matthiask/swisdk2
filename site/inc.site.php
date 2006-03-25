<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class Site {

		protected $arguments;

		public function __construct()
		{
		}
		
		public function setArguments( $arguments )
		{
			$this->arguments = $arguments;
		}

		abstract public function run();
	}

	class ComponentRunnerSite extends Site {

		protected $component;
		
		public function run()
		{
			$obj = new $this->component;
			$obj->run();

			$handler = new HtmlTemplateOutputHandler();
			$handler->handle($obj);
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
