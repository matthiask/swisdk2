<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.permission.php';
	require_once MODULE_ROOT.'inc.smarty.php';

	/**
	 * this site has Wordpress-style URL parsing facilities and can be used
	 * whenever there is some content which has list and detail views
	 *
	 * Note: It also handles an ID passed to the server by GET.
	 * Example: /blog/?p=110
	 */

	define('CONTENT_SITE_PARAM_NONE', 1);
	define('CONTENT_SITE_PARAM_ONE', 2);

	abstract class ContentSite extends Site {
		/**
		 * the request is parsed into variables stored inside this array
		 */
		protected $request;

		/**
		 * SwisdkSmarty instance
		 */
		protected $smarty;

		/**
		 * the default (non-archive) listing title
		 *
		 * f.e. "Blog" or "Agenda"
		 */
		protected $title = null;

		/**
		 * DBObject or DBOContainer instance
		 */
		protected $dbobj;
		protected $multilanguage = false;

		/**
		 * can be feed, trackback, archive, single, default or something
		 * else for which a handler method exists
		 */
		protected $mode = 'default';

		/**
		 * archive specifics (year, month etc.)
		 */
		protected $archive_mode, $archive_dttm;

		protected $template_list = 'list';
		protected $template_single = 'single';

		/**
		 * parser tokens
		 *
		 * 0: set flag to true
		 * 1: set value in request array to next url token
		 *
		 * Example:
		 *
		 * /category/computer/page/3
		 * array(
		 * 	'category' => 'computer',
		 * 	'page' => 3)
		 * mode = 'default'
		 *
		 * /category/computer/2004/
		 * array(
		 * 	'category' => 'computer')
		 * mode= 'archive'
		 * archive_mode = 'year'
		 * archive_dttm = $some_day_in_2004
		 */
		protected $parser_config = array(
			'category' => array(
				CONTENT_SITE_PARAM_ONE),
			'page' => array(
				CONTENT_SITE_PARAM_ONE),
			'feed' => array(
				CONTENT_SITE_PARAM_NONE,
				'mode' => 'feed'),
			'comment_feed' => array(
				CONTENT_SITE_PARAM_NONE,
				'mode' => 'comment_feed'),
			'comments' => array(
				CONTENT_SITE_PARAM_NONE,
				'mode' => 'comment_feed'),
			'feed_comments' => array(
				CONTENT_SITE_PARAM_NONE,
				'mode' => 'feed_comments'),
			'trackback' => array(
				CONTENT_SITE_PARAM_NONE,
				'mode' => 'trackback'));

		public function __construct()
		{
		}

		public function run()
		{
			PermissionManager::check_throw();
			$this->parse_request();

			$this->{'handle_'.$this->mode}();
		}

		protected function parse_request()
		{
			$args = Swisdk::config_value('runtime.arguments');
			$this->request = array();

			// handle /controller?p=<numeric> specially
			if($id = s_get($_GET, 'p')) {
				$this->request['id'] = $id;
				$this->mode = 'single';
				return;
			}

			if(!count($args))
				$this->mode = 'home';

			while(count($args)) {
				$arg = array_shift($args);
				if(isset($this->parser_config[$arg])) {
					if($this->parser_config[$arg][0]==CONTENT_SITE_PARAM_ONE)
						$this->request[$arg] = array_shift($args);
					else
						$this->request[$arg] = true;

					$vars = array('mode', 'template_single', 'template_list');
					foreach($vars as $v)
						if(isset($this->parser_config[$arg][$v]))
							$this->$v = $this->parser_config[$arg][$v];
				} else {
					if(is_numeric($arg)) {
						$this->request['date'][] = $arg;
						$this->mode = 'archive';
					} else if($arg) {
						$this->request['slug'] = urldecode($arg);
						$this->mode = 'single';
					}
				}
			}
		}

		protected function handle_home()
		{
			$this->handle_default();
		}

		protected function handle_default()
		{
			$this->init();
			$this->filter();
			$this->dbobj->init();

			$this->handle_listing();
		}

		protected function handle_archive()
		{
			$this->init();
			if($this->find_config_value('cut_off_archive', true))
				$this->filter();
			else
				$this->filter('!cutoff');
			$this->dbobj->init();

			$title = dgettext('swisdk', 'Archive for ');
			switch($this->archive_mode) {
				case 'day':
					$title .= '%d. %B %Y';
					break;
				case 'month':
					$title .= '%B %Y';
					break;
				case 'year':
					$title .= '%Y';
					break;
			}
			$this->smarty->assign('title', strftime($title, $this->archive_dttm));
			$this->handle_listing();
		}

		protected function handle_listing()
		{
			if(!$this->dbobj->count()) {
				$this->handle_none();
				return;
			}
			$dbobj = $this->dbobj->dbobj();
			$p = $dbobj->_prefix();
			$this->smarty->assign('items', $this->dbobj);
			if($this->find_config_value('comments_enabled')
					&& count($ids = $this->dbobj->ids())) {
				$comment_count = DBObject::db_get_array(
					'SELECT comment_realm, COUNT(comment_id) AS count '
					.'FROM '.$dbobj->table().', tbl_comment '
					.'WHERE '.$p.'comment_realm=comment_realm '
					.'AND '.$p.'id IN ('.implode(',', $ids).') '
					.'GROUP BY comment_realm',
					array('comment_realm', 'count'));
				$this->smarty->assign('comment_count', $comment_count);
			}
			if($this->find_config_value('categories')
					&& count($ids = $this->dbobj->ids())) {
				$this->smarty->assign('categories',
					DBOContainer::find('Category', array(
					':join' => $dbobj->_class(),
					$dbobj->primary().' IN {ids}' => array(
						'ids' => $ids)
					)));
				$rel = $dbobj->relations();
				$r = $rel['Category'];
				$_i2c = DBObject::db_get_array('SELECT * FROM '.$r['link_table']
					.' WHERE '.$r['link_here'].' IN ('.implode(',', $ids).')');
				$i2c = array();
				foreach($_i2c as $row)
					$i2c[$row[$r['link_here']]][] = $row[$r['link_there']];
				$this->smarty->assign('items_to_categories', $i2c);

				if($this->find_config_value('group_listing_by_category')) {
					$c2i = array();
					foreach($i2c as $i => $cs)
						foreach($cs as $c)
							$c2i[$c][] = $i;
					$this->smarty->assign('categories_to_items', $c2i);
				}
			}

			if($this->find_config_value('realm_links')) {
				$realm = PermissionManager::realm_for_url();
				$this->smarty->assign('current_realm', $realm['realm_id']);
				$this->smarty->assign('realms', DBOContainer::find('Realm')
					->collect('id', 'title'));
			}
			$this->register_functions();
			$this->run_website_components($this->smarty);
			$this->display($this->template_list);
		}

		/**
		 * register smarty functions for paging
		 */
		protected function register_functions()
		{
			$this->smarty->register_function('generate_paging',
				array(&$this, '_generate_paging'));
			$this->smarty->register_function('generate_pagelinks',
				array(&$this, '_generate_pagelinks'));
			$this->smarty->register_function('generate_page_list',
				array(&$this, '_generate_page_list'));
			$this->smarty->register_function('generate_page_first',
				array(&$this, '_generate_page_first'));
			$this->smarty->register_function('generate_page_last',
				array(&$this, '_generate_page_last'));
			$this->smarty->register_function('generate_page_next',
				array(&$this, '_generate_page_next'));
			$this->smarty->register_function('generate_page_previous',
				array(&$this, '_generate_page_previous'));
			$this->smarty->register_function('generate_page_fpnl',
				array(&$this, '_generate_page_fpnl'));
			$this->smarty->register_function('generate_page_pcn',
				array(&$this, '_generate_page_pcn'));

			$this->smarty->register_function('generate_item_previous_url',
				array(&$this, '_generate_item_previous_url'));
			$this->smarty->register_function('generate_item_next_url',
				array(&$this, '_generate_item_next_url'));
			$this->smarty->register_function('generate_page_list_from_item',
				array(&$this, '_generate_page_list_from_item'));

			$this->smarty->register_function('generate_date',
				array(&$this, '_generate_date'));
		}

		protected $_paging_limit = null;
		protected $_paging_offset;
		protected $_paging_current_page;
		protected $_paging_total_count;
		protected $_paging_page_count;
		protected $_paging_url;
		protected $_img_prefix;

		/**
		 * returns false if no paging links should be shown (paging is deactivated
		 * or all elements fit on one page)
		 */
		protected function _init_paging_vars()
		{
			if(!$this->dbobj instanceof DBOContainer)
				return false;

			if($this->_paging_limit===null) {
				$this->_paging_limit = $this->find_config_value('default_limit', 10);
				if(!$this->_paging_limit)
					return false;

				$this->_paging_current_page = s_get($this->request, 'page', 1);
				$this->_paging_offset =
					($this->_paging_current_page-1)*$this->_paging_limit;
				$this->_paging_total_count = $this->dbobj->total_count();
				$this->_paging_page_count = ceil($this->_paging_total_count/
					$this->_paging_limit);
				$this->_paging_url = preg_replace('/\/page\/[0-9]+/', '',
					rtrim(Swisdk::config_value('runtime.request.uri'), '/'));

				$this->_img_prefix = Swisdk::config_value('runtime.webroot.img', '/img');

			}

			return $this->_paging_limit
				&& $this->_paging_total_count>$this->_paging_limit;
		}

		protected $_paging_config;

		/**
		 * {generate_paging fmt="%p %c %n"}
		 *
		 * Available types:
		 *
		 * %f: First page
		 * %p: Previous page
		 * %n: Next page
		 * %l: Last page
		 *
		 * %c: Counter (current/total)
		 *
		 * You can also pass mode bits:
		 *
		 * %img.f: First page, force image link
		 * %txt.n: Next page, force text link
		 *
		 * You can also specify your own link text or image:
		 *
		 * {generate_paging "%txt.p %txt.n" p_txt="Zurück" n_img="/media/prev.png"}
		 */
		public function _generate_paging($params, &$smarty)
		{
			if(!$this->_init_paging_vars())
				return '';

			$this->_paging_config = $params;

			return preg_replace_callback(
				'/%(([\S]*)\.)?([a-z])([\s])?/',
				array(&$this, '_paging_fmt_func'),
				$params['fmt']);
		}

		protected function _paging_fmt_func($match)
		{
			$mode = $match[2];
			$type = $match[3];

			$img = null;
			$txt = null;
			$lnk = null;

			$show_img = true;
			$show_txt = true;

			switch($type) {
				case 'f':
					if($this->_paging_current_page==1)
						return '';
					$img = $this->_img_prefix.'/icons/resultset_first.png';
					$txt = 'first page';
					$lnk = '/page/1';
					break;
				case 'p':
					if($this->_paging_current_page==1)
						return;
					$img = $this->_img_prefix.'/icons/resultset_previous.png';
					$txt = 'previous page';
					$lnk = '/page/'.($this->_paging_current_page-1);
					break;
				case 'n':
					if($this->_paging_current_page==$this->_paging_page_count)
						return;
					$img = $this->_img_prefix.'/icons/resultset_next.png';
					$txt = 'next page';
					$lnk = '/page/'.($this->_paging_current_page+1);
					break;
				case 'l':
					if($this->_paging_current_page==$this->_paging_page_count)
						return;
					$img = $this->_img_prefix.'/icons/resultset_last.png';
					$txt = 'last page';
					$lnk = '/page/'.$this->_paging_page_count;
					break;
				case 'c':
					$txt = $this->_paging_current_page.'/'
						.$this->_paging_page_count.' ';
					break;
				default:
					return 'invalid: '.$match[0];
			}

			switch($mode) {
				case 'img':
					$show_txt = false;
					break;
				case 'txt':
					$show_img = false;
					break;
				default:
			}

			$cfg = array(
				'img' => $type.'_img',
				'txt' => $type.'_txt',
				'show_img' => 'show_img',
				'show_txt' => 'show_txt');

			foreach($cfg as $v => $k)
				if(isset($this->_paging_config[$k]))
					$$v = $this->_paging_config[$k];

			$html = '';
			if($lnk)
				$html = '<a href="'.$this->_paging_url.$lnk.'">';
			if($show_img && $img)
				$html .= '<img src="'.$img.'" alt="'.$txt.'" /> ';
			if($show_txt && $txt)
				$html .= $txt;
			if($lnk)
				$html .= '</a>';

			return $html;
		}

		public function _generate_pagelinks($params, &$smarty)
		{
			if(!$this->_init_paging_vars())
				return '';

			$html = '<ul class="pagelinks">';
			$page = 1;
			for($i=0; $i<$this->_paging_total_count; $i+=$this->_paging_limit)
				$html .= '<li><a href="'.$this->_paging_url
					.'/page/'.($page++).'">'.$i.'</a></li>';
			$html .= '</ul>';
			return $html;
		}

		public function _generate_page_list($params, &$smarty)
		{
			$this->_init_paging_vars();

			$html = '';
			$template_first_incl = '<a href="%u"><img src="'.$this->_img_prefix.'/icons/resultset_first.png" /></a> ';
			$template_last_incl = '<a href="%u"><img src="'.$this->_img_prefix.'/icons/resultset_first.png" /></a> ';
			$template_first = $template_first_incl.'&hellip; ';
			$template_last = '&hellip; '.$template_last_incl;
			$template_page = '<a href="%u">%p</a> ';
			$template_current = '<a href="%u">%p</a> ';
			$template_previous = '<a href="%u"><img src="'.$this->_img_prefix.'/icons/resultset_previous.png" /></a> ';
			$template_next = '<a href="%u"><img src="'.$this->_img_prefix.'/icons/resultset_next.png" /></a> ';
			$template_previous = '';
			$template_next = '';
			$delimiter = ' ';
			$prevnext_count = 2;

			foreach(array('template_first',	'template_last',
					'template_first_incl', 'template_last_incl',
					'template_page',
					'template_current', 'template_previous',
					'template_next', 'delimiter',
					'prevnext_count') as $s)
				if(isset($params[$s]))
					$$s = $params[$s];

			$pagecount = ceil($this->_paging_total_count/$this->_paging_limit);

			$plr = $prevnext_count;

			$page = max($this->_paging_current_page-$plr, 1);

			$pattern = array('%u', '%p');
			$repl = array(0, 1);

			// previous
			$repl[0] = $this->_paging_url.'/page/1';
			$repl[1] = 1;
			if($this->_paging_current_page==$plr+2)
				$html .= str_replace($pattern, $repl, $template_first_incl)
					.str_replace($pattern, $repl, $template_page)
					.$delimiter;
			else if($this->_paging_current_page>$plr+1)
				$html .= str_replace($pattern, $repl, $template_first);
			else
				$html .= str_replace($pattern, $repl, $template_first_incl);

			while($page<$this->_paging_current_page) {
				$repl[0] = $this->_paging_url.'/page/'.$page;
				$repl[1] = $page;
				$html .= str_replace($pattern, $repl, $template_page)
					.$delimiter;
				$page++;
			}

			$html .= str_replace($pattern, $repl, $template_previous);

			// current
			$repl[0] = $this->_paging_url.'/page/'.$page;
			$repl[1] = $page;
			$html .= str_replace($pattern, $repl, $template_current);
			$page++;

			// next
			$repl[0] = $this->_paging_url.'/page/'.$page;
			$repl[1] = $page;
			$html .= str_replace($pattern, $repl, $template_next);

			$end = min($this->_paging_current_page+$plr,
				$pagecount);

			while($page<=$end) {
				$repl[0] = $this->_paging_url.'/page/'.$page;
				$repl[1] = $page;
				$html .= $delimiter
					.str_replace($pattern, $repl, $template_page);
				$page++;
			}

			$repl[0] = $this->_paging_url.'/page/'.$pagecount;
			$repl[1] = $pagecount;
			if($page==$pagecount)
				$html .= $delimiter.str_replace($pattern, $repl, $template_page)
					.str_replace($pattern, $repl, $template_last_incl);
			else if($page<$pagecount+1)
				$html .= str_replace($pattern, $repl, $template_last);
			else
				$html .= str_replace($pattern, $repl, $template_last_incl);

			return $html;
		}

		/**
		 * generate page first previous next last
		 */
		public function _generate_page_fpnl($params, &$smarty)
		{
			return $this->_generate_paging(array(
				'fmt' => '%img.f %img.p %img.n %img.l'), $smarty);
		}

		/**
		 * generate page first counter last
		 */
		public function _generate_page_pcn($params, &$smarty)
		{
			return $this->_generate_paging(array(
				'fmt' => '%img.p %c %img.n'), $smarty);
		}

		public function _generate_page_first($params, &$smarty)
		{
			if(!$this->_init_paging_vars())
				return '';

			$title = '<img src="'.$this->_img_prefix
				.'/icons/resultset_first.png" alt="first page" />';
			if(isset($params['title']))
				$title = $params['title'];

			if($this->_paging_current_page==1)
				return '';
			return '<a href="'.$this->_paging_url.'/page/1">'.$title.'</a>';
		}

		public function _generate_page_last($params, &$smarty)
		{
			if(!$this->_init_paging_vars())
				return '';

			$title = '<img src="'.$this->_img_prefix
				.'/icons/resultset_last.png" alt="last page" />';
			if(isset($params['title']))
				$title = $params['title'];

			if($this->_paging_offset+$this->_paging_limit>$this->_paging_total_count)
				return '';
			return '<a href="'.$this->_paging_url.'/page/'
				.ceil($this->_paging_total_count/$this->_paging_limit)
				.'">'.$title.'</a>';
		}

		public function _generate_page_next($params, &$smarty)
		{
			if(!$this->_init_paging_vars())
				return '';

			$title = '<img src="'.$this->_img_prefix
				.'/icons/resultset_next.png" alt="next page" />';
			if(isset($params['title']))
				$title = $params['title'];

			if($this->_paging_offset+$this->_paging_limit>$this->_paging_total_count)
				return '';
			return '<a href="'.$this->_paging_url.'/page/'
				.($this->_paging_current_page+1).'">'.$title.'</a>';
		}

		public function _generate_page_previous($params, &$smarty)
		{
			if(!$this->_init_paging_vars())
				return '';

			$title = '<img src="'.$this->_img_prefix
				.'/icons/resultset_previous.png" alt="previous page" />';
			if(isset($params['title']))
				$title = $params['title'];

			if($this->_paging_current_page==1)
				return '';
			return '<a href="'.$this->_paging_url.'/page/'
				.($this->_paging_current_page-1).'">'.$title.'</a>';
		}

		public function _generate_item_previous_url($params, &$smarty)
		{
			return $this->_item_previous_next_url_helper($params, true);
		}

		public function _generate_item_next_url($params, &$smarty)
		{
			return $this->_item_previous_next_url_helper($params, false);
		}

		protected function _item_previous_next_url_helper($params, $reverse)
		{
			$dbo = $this->dbobj;

			$this->init_dbobj();
			$this->filter('!order,!limit');

			if($order = $this->find_config_value('order', '#')) {
				if($order=='#')
					$order = $this->dbobj->dbobj()->name('start_dttm');
				$tokens = explode(':', $order);

				$dir = $tokens[1]=='DESC';
				if($reverse)
					$dir = !$dir;

				$this->dbobj->add_order_column($tokens[0],
					(isset($tokens[1]) && $dir?'ASC':'DESC'));

				$this->dbobj->add_clause($tokens[0]
					.($dir?'>':'<'), $dbo->get($tokens[0]));
				$this->dbobj->set_limit(1);
			}

			$this->dbobj->init();
			$item = $this->dbobj->rewind();
			$this->dbobj = $dbo;

			if($item) {
				$url = Swisdk::load_instance('UrlGenerator')->generate_url($item);
				if(isset($params['template']))
					return sprintf($params['template'], $url);
				return $url;
			}

			return null;
		}

		public function _generate_page_list_from_item($params, &$smarty)
		{
			$item = $params['item'];

			$prev_count = $this->_page_list_from_item_helper($item, false);
			$next_count = $this->_page_list_from_item_helper($item, true);

			$this->_paging_limit = $this->find_config_value('default_limit', 10);
			if(!$this->_paging_limit)
				return false;

			$this->_paging_current_page =
				intval(ceil(($prev_count+1)/$this->_paging_limit));
			$this->_paging_offset =
				($this->_paging_current_page-1)*$this->_paging_limit;
			$this->_paging_total_count = $prev_count+1+$next_count;
			$this->_paging_page_count = ceil($this->_paging_total_count/
				$this->_paging_limit);
			$this->_paging_url = preg_replace('/\/page\/[0-9]+/', '',
				rtrim(Swisdk::config_value('runtime.request.uri'), '/'));

			$this->_img_prefix = Swisdk::config_value('runtime.webroot.img', '/img');

			$dbo = $this->dbobj;
			$this->init_dbobj();
			$html = $this->_generate_page_list($params, $smarty);
			$this->dbobj = $dbo;

			return $html;
		}

		protected function _page_list_from_item_helper($dbo, $reverse)
		{
			$dbo = $this->dbobj;

			$this->init_dbobj();
			$this->filter('!order,!limit');

			if($order = $this->find_config_value('order', '#')) {
				if($order=='#')
					$order = $this->dbobj->dbobj()->name('start_dttm');
				$tokens = explode(':', $order);

				$dir = $tokens[1]=='DESC';
				if($reverse)
					$dir = !$dir;

				$this->dbobj->add_order_column($tokens[0],
					(isset($tokens[1]) && $dir?'ASC':'DESC'));

				$this->dbobj->add_clause($tokens[0]
					.($dir?'>':'<'), $dbo->get($tokens[0]));
			}

			$count = $this->dbobj->total_count();
			$this->dbobj = $dbo;
			return $count;
		}

		protected $_generate_date_data = array();

		public function _generate_date($params, &$smarty)
		{
			$ref =& $this->_generate_date_data;
			if(isset($params['name']) && $n = $params['name']) {
				if(!isset($ref[$n]))
					$ref[$n] = array();
				$ref =& $ref[$n];
			}

			$item = $params['item'];
			$fmt = $params['fmt'];

			if(!isset($ref[$fmt])) {
				$pubdate_field = $this->find_config_value('pubdate_field', '#');
				if($pubdate_field=='#')
					$pubdate_field = $this->dbobj->dbobj()->name('start_dttm');

				$ref[$fmt] = array(
					'pubdate_field' => $pubdate_field,
					'last_str' => '');
			}

			$ref =& $ref[$fmt];
			$str = strftime($fmt, $item->get($ref['pubdate_field']));

			if($str!=$ref['last_str']) {
				$ref['last_str'] = $str;
				return $str;
			}

			return '';
		}

		protected function handle_feed()
		{
			if(!$this->find_config_value('feed_enabled'))
				SwisdkError::handle(new FatalError('Feed is disabled'));
			$this->init_dbobj();
			$this->filter();
			$this->dbobj->init();

			require_once SWISDK_ROOT.'lib/contrib/feedcreator.class.php';
			require_once SWISDK_ROOT.'lib/contrib/markdown.php';
			$feed = new UniversalFeedCreator();
			$feed->title = Swisdk::config_value('runtime.website.title');
			$feed->description = $feed->title;
			$feed->link = 'http://'.Swisdk::config_value('runtime.request.host');
			$feed->syndicationURL = $_SERVER['REQUEST_URI'];

			$ug = Swisdk::load_instance('UrlGenerator');

			$authors = DBOContainer::find_by_id('User',
				$this->dbobj->collect('id', 'author_id'));

			foreach($this->dbobj as $dbo) {
				$item = new FeedItem();
				$item->title = $dbo->title;
				$item->link = $feed->link
					.$ug->generate_url($dbo);
				$item->description = Markdown($dbo->teaser);
				$item->date = date(DATE_W3C, $dbo->start_dttm);
				$item->author = $authors[$dbo->author_id]->forename.' '
					.$authors[$dbo->author_id]->name;

				$feed->addItem($item);
			}

			$feed->encoding = 'UTF-8';

			Swisdk::require_htdocs_data_directory('feeds');

			$feed->saveFeed('RSS2.0', HTDOCS_DATA_ROOT
				.'feeds/rss20-'.sha1($_SERVER['REQUEST_URI']).'.xml');
		}

		protected function handle_feed_comments()
		{
			if(!$this->find_config_value('feed_enabled'))
				SwisdkError::handle(new FatalError('Feed is disabled'));
			if(!$this->find_config_value('comments_enabled'))
				SwisdkError::handle(new FatalError('Comments are disabled'));

			require_once SWISDK_ROOT.'lib/contrib/feedcreator.class.php';
			require_once SWISDK_ROOT.'lib/contrib/markdown.php';
			$feed = new UniversalFeedCreator();
			$feed->title = Swisdk::config_value('runtime.website.title')
				.' | '.dgettext('swisdk', 'Comments');
			$feed->description = $feed->title;
			$feed->link = Swisdk::config_value('runtime.request.protocol')
				.'//'.Swisdk::config_value('runtime.request.host');
			$feed->syndicationURL = $_SERVER['REQUEST_URI'];

			$ug = Swisdk::load_instance('UrlGenerator');
			$comments = DBOContainer::find('Comment', array(
				':order' => array('comment_creation_dttm', 'DESC'),
				':limit' => 20));

			foreach($comments as $c) {
				$item = new FeedItem();
				$item->title = ellipsize(strip_tags($c->text), 50, '...');
				$item->link = $feed->link.$ug->generate_url($c);
				$item->description = Markdown($c->text);
				$item->date = date(DATE_W3C, $c->creation_dttm);
				$item->author = $c->author;

				$feed->addItem($item);
			}

			$feed->encoding = 'UTF-8';

			Swisdk::require_htdocs_data_directory('feeds');

			$feed->saveFeed('RSS2.0', HTDOCS_DATA_ROOT
				.'feeds/rss20-'.sha1($_SERVER['REQUEST_URI']).'.xml');
		}

		protected function handle_comment_feed()
		{
			if(!$this->find_config_value('feed_enabled'))
				SwisdkError::handle(new FatalError('Feed is disabled'));
			if(!$this->find_config_value('comments_enabled'))
				SwisdkError::handle(new FatalError('Comments are disabled'));

			$ug = Swisdk::load_instance('UrlGenerator');
			$this->dbobj = $this->_single_get_dbobj();
			if(!$this->dbobj)
				return $this->handle_none();

			require_once SWISDK_ROOT.'lib/contrib/feedcreator.class.php';
			require_once SWISDK_ROOT.'lib/contrib/markdown.php';
			$feed = new UniversalFeedCreator();
			$feed->title = $this->dbobj->title
				.' | '.dgettext('swisdk', 'Comments');
			$feed->description = sprintf(dgettext('swisdk', 'Comments for \'%s\''),
				$this->dbobj->title);
			$feed->link = Swisdk::config_value('runtime.request.protocol')
				.'//'.Swisdk::config_value('runtime.request.host')
				.$ug->generate_url($this->dbobj);
			$feed->syndicationURL = $_SERVER['REQUEST_URI'];

			$comments = DBOContainer::find('Comment', array(
				'comment_realm=' => $this->dbobj->comment_realm));

			foreach($comments as $c) {
				$item = new FeedItem();
				$item->title = ellipsize(strip_tags($c->text), 50, '...');
				$item->link = $feed->link.'#comment'.$c->id();
				$item->description = Markdown($c->text);
				$item->date = date(DATE_W3C, $c->creation_dttm);
				$item->author = $c->author;

				$feed->addItem($item);
			}

			$feed->encoding = 'UTF-8';

			Swisdk::require_htdocs_data_directory('feeds');

			$feed->saveFeed('RSS2.0', HTDOCS_DATA_ROOT
				.'feeds/rss20-'.sha1($_SERVER['REQUEST_URI']).'.xml');
		}

		protected function _single_get_dbobj()
		{
			if(isset($this->request['id'])) {
				$this->init(false);
				if($this->multilanguage)
					return DBObjectML::find($this->dbo_class,
						$this->request['id']);
				else
					return DBObject::find($this->dbo_class,
						$this->request['id']);
			} else {
				$this->init();
				if($this->find_config_value('cut_off_archive', true)===true)
					$this->filter_cutoff();
				$this->filter_archive();
				$this->filter_slug();
				$this->dbobj->init();
				return $this->dbobj->rewind();
			}
		}

		protected function _single_comment_handler($dbo)
		{
			if($this->find_config_value('comments_enabled')) {
				Swisdk::load('CommentComponent', 'components');
				$comments = new CommentComponent($dbo->comment_realm);
				$comments->run();
				$comments->set_smarty($this->smarty);
			}
		}

		protected function _single_handler($dbo)
		{
			if($this->find_config_value('permission_filter'))
				PermissionManager::check_access_throw($dbo);

			$this->smarty->assign('item', $dbo);
			$this->_single_comment_handler($dbo);
			if($this->find_config_value('categories')) {
				$this->smarty->assign('categories',
					$dbo->related('Category'));
			}
			if($this->find_config_value('realm_links')) {
				$realm = PermissionManager::realm_for_url();
				if($realm['realm_id']!=$dbo->realm_id) {
					$this->smarty->assign('realm',
						$dbo->related('Realm'));
				}
			}
			if($this->find_config_value('trackback_enabled')){
				$urlgen = Swisdk::load_instance('UrlGenerator');
				$proto = Swisdk::config_value('runtime.request.protocol');
				$host = Swisdk::config_value('runtime.request.host');
				$url = "$proto//$host{$urlgen->generate_url($dbo)}";
				$this->smarty->assign('trackback_enabled', true);
				$this->smarty->assign('trackback_about', $url);
				$this->smarty->assign('trackback_url', "{$url}trackback");
			} else {
				$this->smarty->assign('trackback_enabled', 'false');
			}

			$this->register_functions();
			$this->run_website_components($this->smarty);
		}

		protected function handle_single()
		{
			$this->dbobj = $this->_single_get_dbobj();
			if(!$this->dbobj)
				return $this->handle_none();
			$this->_single_handler($this->dbobj);
			$this->display($this->template_single);
		}

		protected function handle_trackback()
		{
			if(!$this->find_config_value('trackback_enabled'))
				SwisdkError::handle(new FatalError('Trackback is disabled'));

			$this->dbobj = $this->_single_get_dbobj();
			$url = getInput('url');
			$title = getInput('title');
			$excerpt = getInput('excerpt');
			$blog_name = getInput('blog_name');
			$charset = getInput('charset');
			if ($charset)
				$charset = strtoupper(trim($charset));
			else
				$charset = 'ASCII, UTF-8, ISO-8859-1, JIS, EUC-JP, SJIS';

			if(function_exists('mb_convert_encoding')) {
				$title = mb_convert_encoding($title, 'UTF-8', $charset);
				$excerpt = mb_convert_encoding($excerpt, 'UTF-8', $charset);
				$blog_name = mb_convert_encoding($blog_name, 'UTF-8', $charset);
			}

			if(!$title && !$url && !$blog_name)
				$this->handle_single();

			if($url && $title) {
				$comment = DBObject::create('Comment');
				$comment->realm = $this->dbobj->comment_realm;
				$comment->author = $blog_name;
				$comment->author_url = $url;
				$comment->text = "<strong>$title</strong>\n\n$excerpt";
				$comment->type = 'trackback';

				// TODO check for dupes
				$comment->store();

				trackback_response(0);
			}

			trackback_response(1, 'Trackback failed');
		}

		protected function handle_none()
		{
			$smarty = new SwisdkSmarty();
			$smarty->assign('items', array());
			$this->register_functions();
			$this->run_website_components($smarty);
			$this->display($this->template_list);
		}


		protected function init($container = true)
		{
			if(!$this->smarty) {
				$this->smarty = new SwisdkSmarty();
				$this->smarty->assign('title', $this->title);
			}
			if($container)
				$this->init_dbobj();
		}

		protected function init_dbobj()
		{
			if($this->multilanguage)
				$this->dbobj = DBOContainer::create(
					DBObjectML::create($this->dbo_class));
			else
				$this->dbobj = DBOContainer::create(
					$this->dbo_class);
		}


		/**
		 * Filtering methods
		 *
		 * filter()
		 * filter('limit,cutoff')
		 * filter(array('limit', 'cutoff'))
		 */

		protected function filter($which = null)
		{
			$methods = get_class_methods($this);
			$which = explode(',', $which);
			$include = array();
			$exclude = array();
			foreach($which as $m) {
				if(!($m = trim($m)))
					continue;
				if($m{0}=='!')
					$exclude[] = 'filter_'.substr($m, 1);
				else
					$include[] = 'filter_'.$m;
			}

			if(count($include))
				$methods = array_intersect($methods, $include);
			if(count($exclude))
				$methods = array_diff($methods, $exclude);
			foreach($methods as $m)
				if(strpos($m, 'filter_')===0)
					$this->$m();
		}

		/**
		 * paging filter (do not display more than $default_limit entries)
		 */
		protected function filter_limit()
		{
			if($limit = $this->find_config_value('default_limit', 10)) {
				$limit = $this->find_config_value('default_limit', 10);
				$page = s_get($this->request, 'page', 1);
				$this->dbobj->set_limit(($page-1)*$limit, $limit);
			}
		}

		/**
		 * use this filter to hide things from the past respective from the future
		 *
		 * F.e. hide articles which have a publication date in the future or events
		 * which have gone by
		 */
		protected function filter_cutoff()
		{
			$time = time();
			if($cop = $this->find_config_value('cut_off_past'))
				$this->dbobj->add_clause($cop.'>='.$time);
			if($cof = $this->find_config_value('cut_off_future'))
				$this->dbobj->add_clause($cof.'<'.$time);
			if($coe = $this->find_config_value('cut_off_end')) {
				if($coef = $this->find_config_value('cut_off_end_flag')) {
					$mode = $coef{0}=='!';
					$this->dbobj->add_clause('('
						.($mode
							?substr($coef, 1).'!='
							:$coef.'=')
						.'0 OR '
						.$coe.'>'.$time.')');
				} else
					$this->dbobj->add_clause($coe.'>'.$time);
			}
		}

		/**
		 * Should the entries be ordered?
		 */
		protected function filter_order()
		{
			if($order = $this->find_config_value('order', '#')) {
				if($order=='#')
					$order = $this->dbobj->dbobj()->name('start_dttm');
				$columns = explode(',', $order);
				foreach($columns as $o) {
					$tokens = explode(':', $o);
					$this->dbobj->add_order_column($tokens[0],
						(isset($tokens[1]) && $tokens[1]=='DESC'?'DESC':'ASC'));
				}
			}
		}

		/**
		 * Filter by entry category
		 *
		 * needs a table tbl_entry_category with the three fields
		 * entry_category_id, entry_category_key and entry_category_title
		 */
		protected function filter_category()
		{
			if(!$this->find_config_value('categories'))
				return;

			if(s_test($this->request, 'category')) {
				$dbo = $this->dbobj->dbobj_clone();
				$p = $dbo->_prefix();
				$this->dbobj->add_join('Category');
				$this->dbobj->add_clause('category_name=',
					$this->request['category']);
			}
		}

		/**
		 * Find out timespan using the numbers in the arguments and filter accordingly
		 */
		protected function filter_archive()
		{
			$pubdate_field = $this->find_config_value('pubdate_field', '#');
			if($pubdate_field=='#')
				$pubdate_field = $this->dbobj->dbobj()->name('start_dttm');

			if(isset($this->request['date']) && $pubdate_field) {
				list($this->archive_dttm, $end, $this->archive_mode) =
					$this->dttm_range($this->request['date']);
				$this->dbobj->add_clause($pubdate_field.'>=',
					$this->archive_dttm);
				$this->dbobj->add_clause($pubdate_field.'<', $end);
			}
		}
			
		/**
		 * Filter by slug (entry name)
		 */
		protected function filter_slug()
		{
			if(isset($this->request['slug']) && $slug_field =
					$this->find_config_value('slug_field', '#')) {
				if($slug_field=='#') {
					if($this->multilanguage)
						$slug_field = $this->dbobj->dbobj()
							->content_dbobj()->name('name');
					else
						$slug_field = $this->dbobj->dbobj()
							->name('name');
				}
				$this->dbobj->add_clause($slug_field.'=',
					$this->request['slug']);
			}
		}

		protected function filter_permission()
		{
			if($this->find_config_value('permission_filter'))
				PermissionManager::set_realm_clause($this->dbobj);
		}

		protected function filter_active()
		{
			if($this->find_config_value('active_filter'))
				$this->dbobj->add_clause($this->dbobj->dbobj()->name('active')
					.'!=0');
		}

		protected function filter_language()
		{
			if($this->multilanguage)
				$this->dbobj->add_clause($this->dbobj->dbobj()
					->content_dbobj()->name('language_id').'=',
					Swisdk::language());
		}


		/**
		 * Helper methods
		 */

		/**
		 * Walk the configuration to find a matching value
		 *
		 * find_config_value('pubdate_field') tries to get
		 * content.<dbobject class>.pubdate_field and then
		 * content.pubdate_field
		 *
		 * You can f.e. enable comments globally, but disable
		 * them for events using
		 * [content]
		 * comments_enabled = true
		 * event.comments_enabled = false
		 */
		protected function find_config_value($key, $default = null)
		{
			return Swisdk::website_config_value(array(
				'content.'.$this->dbo_class.'.'.$key,
				'content.'.$key), $default);
		}

		/**
		 * Argument:
		 * array($year [, $month, [$day]])
		 *
		 * Returns:
		 * array($start_timestamp, $end_timestamp, $timespan_type)
		 */
		protected function dttm_range($numbers)
		{
			if(isset($numbers[0]) && $year=$numbers[0]) {
				if(isset($numbers[1]) && $month=$numbers[1]) {
					if(isset($numbers[2]) && $day=$numbers[2]) {
						return array(
							mktime(0, 0, 0, $month, $day, $year),
							mktime(0, 0, 0, $month, $day+1, $year),
							'day');
					}
					return array(
						mktime(0, 0, 0, $month, 1, $year),
						mktime(0, 0, 0, $month+1, 1, $year),
						'month');
				}
				return array(
					mktime(0, 0, 0, 1, 1, $year),
					mktime(0, 0, 0, 1, 1, $year+1),
					'year');
			}
			return null;
		}

		protected function display($action)
		{
			$this->smarty->display_template($this->dbo_class.'.'.$action);
		}
	}

	/**
	 * Helper function which can send a trackback response
	 */
	function trackback_response($error = 0, $error_message = '')
	{
		header('Content-Type: text/xml; charset=UTF-8');
		if ($error) {
			echo '<?xml version="1.0" encoding="utf-8"?'.">\n";
			echo "<response>\n";
			echo "<error>1</error>\n";
			echo "<message>$error_message</message>\n";
			echo "</response>";
			die();
		} else {
			echo '<?xml version="1.0" encoding="utf-8"?'.">\n";
			echo "<response>\n";
			echo "<error>0</error>\n";
			echo "</response>";
		}
		Swisdk::shutdown();
	}


	/**
	 * Multilanguage content site
	 *
	 * Also filter by language
	 */
	class ContentSiteML extends ContentSite {
		public function filter_language()
		{
			$this->dbobj->add_join('Language');
			$this->dbobj->add_clause('tbl_language.language_id=', Swisdk::language());
		}

		public function filter_category()
		{
			if(s_test($this->request, 'category')) {
				$dbo = $this->dbobj->dbobj_clone();
				$p = $dbo->_prefix();
				$this->dbobj->add_join('Category');
				$this->dbobj->add_join('tbl_category_content',
					'tbl_category.category_id=category_content_category_id');
				$this->dbobj->add_clause('category_content_language_id=',
					Swisdk::language());
				$this->dbobj->add_clause('category_content_name=',
					$this->request['category']);
			}
		}

	}

?>
