<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.sitemap.php';

	class AdminSiteComponent implements ISmartyComponent {
		protected $prepend = null;

		protected $admin_page;
		protected $current_page;

		public function run()
		{
			$this->prepend = Swisdk::config_value('runtime.navigation.prepend');
			$this->current_page = SwisdkSitemap::page(
				Swisdk::config_value('runtime.controller.url'));
		}

		public function set_smarty(&$smarty)
		{
			$smarty->assign('currentmodule', $this->current_page);
			$smarty->register_function('generate_module_switch', array(
				&$this, 'generate_module_switch'));
			$smarty->register_function('generate_module_html', array(
				&$this, 'generate_module_html'));
		}

		private function &admin_page()
		{
			if(!$this->admin_page) {
				$page = '/admin';
				if($d = Swisdk::config_value('runtime.domain'))
					$page = '/'.$d.$page;
				$this->admin_page = SwisdkSitemap::page($page);
			}

			return $this->admin_page;
		}

		public function generate_module_switch()
		{
			$page = $this->admin_page();
			$html = '';

			if(!s_test($page, 'pages'))
				return '';

			if($page['url']==$this->current_page['url'])
				$html .= '<option value="">-- go to --</option>';

			foreach($page['pages'] as $p) {
				$type = s_get($p, 'adminsite:type');

				if($type=='section')
					$html .= $this->generate_module_switch_section($p);
				else if($type=='hidden')
					;
				else
					$html .= $this->generate_module_switch_module($p);
			}

			return $html;
		}

		private function generate_module_switch_section($page)
		{
			$html = '<option value="" style="font-weight:bold">'
				.$page['title'].'</option>';

			foreach($page['pages'] as $p)
				$html .= $this->generate_module_switch_module($p, '&nbsp;&nbsp;');

			return $html;
		}

		private function generate_module_switch_module($page, $prepend='')
		{
			$selected = '';
			if($page['url']==$this->current_page['url'])
				$selected = ' selected="selected"';

			return <<<EOD
<option$selected value="{$this->prepend}{$page['url']}">$prepend{$page['title']}</option>

EOD;
		}

		public function generate_module_html()
		{
			$page = $this->admin_page();

			if(!s_test($page, 'pages'))
				return '';

			$html = '<div id="admin-modules">';

			$in_list = false;

			foreach($page['pages'] as $p) {
				$type = s_get($p, 'adminsite:type');

				if($type=='section')  {
					if($in_list) {
						$in_list = false;
						$html .= '</ul>';
					}
					$html .= $this->generate_module_html_section($p);
				} else if($type=='hidden')
					;
				else {
					if(!$in_list) {
						$in_list = true;
						$html .= '<ul>';
					}
					$html .= $this->generate_module_html_module($p);
				}
			}

			if($in_list) {
				$in_list = false;
				$html .= '</ul>';
			}

			$html .= '</div>';

			return $html;
		}

		private function generate_module_html_section($page)
		{
			$section = cssClassify($page['url']);
			$html = '<div class="admin-modules-section">';
			/*
			$html .= '<h2><a href="#" onclick="$(\'#'.$section.'\').slideToggle();return false">'
				.$page['title'].'</a></h2>';
			*/
			$html .= '<h2>'.$page['title'].'</h2>';

			$html .= '<ul id="'.$section.'">';
			foreach($page['pages'] as $p) {
				$html .= $this->generate_module_html_module($p);
			}
			$html .= '</ul><br style="clear:both" /></div>';
			return $html;
		}

		private function generate_module_html_module($page)
		{
			return <<<EOD
<li>
	<a href="{$this->prepend}{$page['url']}">
		<img src="/media/admin/edit-copy.png" alt="" />
		{$page['title']}
	</a>
</li>

EOD;
		}

		protected $html;
	}

?>
