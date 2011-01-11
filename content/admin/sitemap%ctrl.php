<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.sitemap.php';
	require_once MODULE_ROOT.'inc.form.php';

	Swisdk::register('SitemapAdminSite');

	class SitemapAdminSite extends Site {
		protected $controller;

		public function run()
		{
			$sitemap = SwisdkSitemap::sitemap();

			$this->controller = Swisdk::config_value('runtime.controller.url');

			$prep = Swisdk::config_value('runtime.domain');
			if($prep)
				$prep = '/'.$prep;

			$tree = $this->build_tree($sitemap, $prep);

			$args = Swisdk::arguments();
			$cmd = array_shift($args);
			$url = '/'.implode('/', $args);

			$form = null;

			switch($cmd) {
			case 'edit':
				$page = SwisdkSitemap::page_raw($url);
				$attr = array(
					'id' => s_get($page, 'id'),
					'title' => s_get($page, 'title'));
			case 'insert':
				if(!isset($attr))
					$attr = array('id' => '', 'title' => '');

				$form = new Form(DBObject::create('PAGE'));
				foreach($attr as $k => $v)
					$form->add($k)->set_title($k)->set_default_value($v);
				$form->add(new SubmitButton());

				$form['id']->add_rule(new RequiredRule());

				if($form->is_valid()) {
					$form->refresh_guard();
					$dbo = $form->dbobj();
					foreach($attr as $k => $v)
						$attr[$k] = $dbo[$k];

					if($cmd=='insert')
						$url .= '/'.$attr['id'];

					SwisdkSitemap::set_page_raw($url, $attr);
					SwisdkSitemap::store();
					$this->go_to();
				}

				break;
			case 'delete':
				SwisdkSitemap::delete_page_raw($url);
				SwisdkSitemap::store();
				$this->go_to();
				break;

			case 'up':
				$offset = -1;
			case 'down':
				if(!isset($offset))
					$offset = 1;

				$child = array_pop($args);
				$base = '/'.implode('/', $args);

				$children = SwisdkSitemap::children($base);
				$pos = array_search($child, $children);

				if($offset==-1 && $pos==0 || $offset==1 && $pos==count($children)-1)
					$this->go_to();

				$bak = $children[$pos];
				$children[$pos] = $children[$pos+$offset];
				$children[$pos+$offset] = $bak;

				SwisdkSitemap::reorder_children($base, $children);
				SwisdkSitemap::store();
				$this->go_to();
				break;
			}

			$smarty = $this->smarty();
			$smarty->assign('content', $tree.($form?$form->html():''));
			$this->run_website_components($smarty);
			$smarty->display_template('base.admin');
		}

		protected function build_tree($tree, $prepend='')
		{
			$pages = s_get($tree, 'pages');
			if(!is_array($pages) || !count($pages))
				return;

			$html = '<ul>';

			if(s_test($page, 'domain') && $p = s_get($page, 'id'))
				$prepend .= '/'.$p;

			foreach($pages as $id => $page) {
				$subtree = $this->build_tree($page, $prepend);

				$html .= <<<EOD
<li>
	<a href="{$this->controller}edit{$prepend}{$page['url']}">{$page['title']}</a>
	<small>
		<a href="{$this->controller}edit{$prepend}{$page['url']}">edit</a>
		<a href="{$this->controller}up{$prepend}{$page['url']}">up</a>
		<a href="{$this->controller}down{$prepend}{$page['url']}">down</a>
		<a href="{$this->controller}delete{$prepend}{$page['url']}">delete</a>
	</small>
	$subtree
	<br />&nbsp;&nbsp;
	<a href="{$this->controller}insert{$prepend}{$page['url']}">
	<small>&rArr; insert here</small></a>
</li>

EOD;
			}

			$html .= '</ul>';
			return $html;
		}
	}

?>
