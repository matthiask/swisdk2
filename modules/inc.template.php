<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class TemplateProcessor {
		protected $vars;
		protected $patterns;
		protected $template;

		public function __construct($template)
		{
			$this->template = $template;
			$matches = array();
			preg_match_all('/\{([A-Za-z_0-9]+)}/', $template,
				$matches, PREG_PATTERN_ORDER);

			$this->vars = s_get($matches, 1);
			foreach($this->vars as $v)
				$this->patterns[] = '/\{'.$v.'\}/';
		}

		public function evaluate($dbo)
		{
			$vals = array();
			foreach($this->vars as $v)
				$vals[] = $dbo->pretty_value($v);

			return preg_replace($this->patterns, $vals,
				$this->template);
		}
	}

?>
