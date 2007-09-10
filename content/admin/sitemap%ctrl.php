<?php

	require_once MODULE_ROOT.'inc.sitemap.php';
	require_once MODULE_ROOT.'inc.form.php';

	Swisdk::register('SitemapAdminSite');

	class BlaComponent extends Site {
		public function run()
		{
		}

		public function html()
		{
			$smarty = $this->smarty();

			$smarty->assign('content', '<h2>BlaComponent</h2>');
			$this->run_website_components($smarty);
			$smarty->display_template('base.admin');
		}
	}

	class SitemapAdminSite extends Site {
		protected $controller;

		public function run()
		{
			$this->handle_component_call();

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

					SwisdkSitemap::set_page_raw($url, $attr);
					SwisdkSitemap::store();
					$this->goto();
				}

				break;
			case 'delete':
				$ss->remove_page($page);
				$ss->store();
				$this->goto();
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
	<a href="{$this->controller}delete{$prepend}{$page['url']}"><small>delete</small></a>
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

	/*

	$s = SwisdkSitemap::instance();

	$s->set_page_attributes('/admin/other/user', array('title' => 'Uuuuser'));
	$s->set_page_attributes('', array('title' => 'Houme'));

	$s->append_child('/admin/', array('id' => 'testo'));

	$s->reorder_children('/admin', explode(',', 'blog,other,testo,jobs'));

	*/

?>
