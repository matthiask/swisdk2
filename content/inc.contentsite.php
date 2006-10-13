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
		 */
		protected $title = null;

		/**
		 * DBObject or DBOContainer instance
		 */
		protected $dbobj;

		/**
		 * can be one of feed, trackback, archive, single or default
		 */
		protected $mode = 'default';

		/**
		 * archive specifics (year, month etc.)
		 */
		protected $archive_mode, $archive_dttm;

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

			if(isset($_GET['p']) && $id = intval($_GET['p'])) {
				$this->request['id'] = $id;
				$this->mode = 'single';
				return;
			}

			while(count($args)) {
				$arg = array_shift($args);
				if(isset($this->parser_config[$arg])) {
					if($this->parser_config[$arg][0]==CONTENT_SITE_PARAM_ONE)
						$this->request[$arg] = array_shift($args);
					else
						$this->request[$arg] = true;
					if(isset($this->parser_config[$arg]['mode']))
						$this->mode = $this->parser_config[$arg]['mode'];
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

		protected function handle_default()
		{
			$this->init();
			$this->filter();

			$this->handle_listing();
		}

		protected function handle_archive()
		{
			$this->init();
			$this->filter();

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
			$p = $this->dbobj->dbobj()->_prefix();
			$this->smarty->assign('items', $this->dbobj);
			if($this->find_config_value('comments_enabled')) {
				$comment_count = DBObject::db_get_array(
					'SELECT comment_realm, COUNT(comment_id) AS count '
					.'FROM '.$this->dbobj->table().', tbl_comment '
					.'WHERE '.$p.'comment_realm=comment_realm '
					.'AND '.$p.'id IN ('.implode(',', $this->dbobj->ids()).') '
					.'GROUP BY comment_realm',
					array('comment_realm', 'count'));
				$this->smarty->assign('comment_count', $comment_count);
			}
			$this->smarty->register_function($p.'url',
				'generate_'.$p.'url');
			$this->smarty->display(Swisdk::template($this->dbo_class.'.list'));
		}

		protected function handle_feed()
		{
			if(!$this->find_config_value('feed_enabled'))
				SwisdkError::handle(new FatalError('Feed is disabled'));
			$this->dbobj = DBOContainer::create($this->dbo_class);
			$this->filter();

			require_once SWISDK_ROOT.'lib/contrib/feedcreator.class.php';
			require_once SWISDK_ROOT.'lib/contrib/markdown.php';
			$feed = new UniversalFeedCreator();
			$feed->title = Swisdk::config_value('runtime.website.title');
			$feed->description = 'Description';
			$feed->link = 'http://'.Swisdk::config_value('runtime.request.host');
			$feed->syndicationURL = $_SERVER['REQUEST_URI'];

			foreach($this->dbobj as $dbo) {
				$item = new FeedItem();
				$item->title = $dbo->title;
				$item->link = $feed->link
					.$this->generate_url($dbo);
				$item->description = Markdown($dbo->teaser);
				$item->date = date(DATE_W3C, $dbo->start_dttm);
				$item->author = $dbo->author_id;

				$feed->addItem($item);
			}

			$feed->encoding = 'UTF-8';

			$feed->saveFeed('RSS2.0', HTDOCS_ROOT.'feeds/rss20-'
				.sha1($_SERVER['REQUEST_URI']).'.xml');
		}

		protected function handle_single()
		{
			$dbo = null;

			if(isset($this->request['id'])) {
				$this->init(false);
				$dbo = DBObject::find($this->dbo_class,
					$this->request['id']);
			} else {
				$this->init();
				if($this->find_config_value('cut_off_single', true)===true)
					$this->filter_cutoff();
				$this->filter_archive();
				$this->filter_slug();
				$this->dbobj->init();
				$dbo = $this->dbobj->rewind();
			}

			$this->smarty->assign('item', $dbo);
			$chtml = '';
			if($this->find_config_value('comments_enabled')) {
				require_once CONTENT_ROOT.'components/CommentComponent.inc.php';
				$comments = new CommentComponent($dbo->comment_realm);
				$comments->run(array('realm' => $dbo->comment_realm));
				$this->smarty->assign('comments', $comments->html());
			}
			$this->smarty->display(Swisdk::template($this->dbo_class.'.single'));
		}

		protected function handle_trackback()
		{
			if(!$this->find_config_value('trackback_enabled'))
				SwisdkError::handle(new FatalError('Trackback is disabled'));
			$dbo = $this->dbobj->rewind();
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
				$comment->realm = $dbo->comment_realm;
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
			// TODO how to do this with raw SwisdkSmarty?
			$sm = SmartyMaster::instance();
			$sm->add_html_fragment('content', 'No items matched your criteria');
			$sm->display();
		}


		protected function init($container = true)
		{
			if(!$this->smarty) {
				$this->smarty = new SwisdkSmarty();
				$this->smarty->assign('title', $this->title);
			}
			if($container)
				$this->dbobj = DBOContainer::create($this->dbo_class);
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
			if($which) {
				if(!is_array($which))
					$which = explode(',', $which);
				foreach($which as $m)
					$this->{'filter_'.$m}();
			} else {
				$methods = get_class_methods($this);
				foreach($methods as $method)
					if(strpos($method, 'filter_')===0)
						$this->$method();
			}

			$this->dbobj->init();
		}

		protected function filter_limit()
		{
			if($limit = $this->find_config_value('default_limit', 10)) {
				$offset = 0;
				if(isset($this->request['page']))
					$offset = ($this->request['page']-1)*$limit;
				$this->dbobj->set_limit($offset, $limit);
			}
		}

		protected function filter_cutoff()
		{
			if($cop = $this->find_config_value('cut_off_past'))
				$this->dbobj->add_clause($cop.'>'.time());
			if($cof = $this->find_config_value('cut_off_future'))
				$this->dbobj->add_clause($cof.'<'.time());
		}

		protected function filter_order()
		{
			if($order = $this->find_config_value('order', '#')) {
				if($order=='#')
					$order = $this->dbobj->dbobj()->name('start_dttm');
				$tokens = explode(':', $order);
				$this->dbobj->add_order_column($tokens[0],
					(isset($tokens[1]) && $tokens[1]=='DESC'?'DESC':'ASC'));
			}
		}

		protected function filter_category()
		{
			if(isset($this->request['category'])
					&& $this->request['category']) {
				$p = $this->dbobj->_prefix();
				$this->dbobj->add_join($this->dbobj->_class().'Category');
				$this->dbobj->add_join('tbl_'.$p.'category',
					'tbl_'.$p.'to_'.$p.'category.'.$p.'category_id='
					.'tbl_'.$p.'category.'.$p.'category_id');
				$this->dbobj->add_clause($p.'category_key=', $this->request['category']);
			}
		}

		protected function filter_archive()
		{
			$pubdate_field = $this->find_config_value('pubdate_field', '#');
			if($pubdate_field=='#')
				$pubdate_field = $this->dbobj->dbobj()->name('start_dttm');

			if(isset($this->request['date']) && $pubdate_field) {
				list($this->archive_dttm, $end, $this->archive_mode) =
					$this->dttm_range($this->request['date']);
				$this->dbobj->add_clause($pubdate_field.'>',
					$this->archive_dttm);
				$this->dbobj->add_clause($pubdate_field.'<', $end);
			}
		}
			
		protected function filter_slug()
		{
			if(isset($this->request['slug'])
					&& $slug_field = $this->find_config_value('slug', '#')) {
				if($slug_field=='#')
					$slug_field = $this->dbobj->dbobj()->name('name');
				$this->dbobj->add_clause($slug_field.'=',
					$this->request['slug']);
			}
		}


		/**
		 * Helper methods
		 */

		protected function find_config_value($key, $default = null)
		{
			$keys = array('content.'.$this->dbo_class.'.'.$key,
				'content.'.$key);
			while($key = array_shift($keys))
				if(($value = Swisdk::config_value($key))!==null)
					return $value;
			return $default;
		}

		protected function dttm_range($numbers)
		{
			list($year, $month, $day) = $numbers;
			if($year) {
				if($month) {
					if($day) {
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
	}

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
		exit();
	}


	class MLContentSite extends ContentSite {
		public function init_dbobj()
		{
			parent::init_dbobj();
			$this->dbobj->add_join('Language');
			$this->dbobj->add_clause('language_id=', Swisdk::language());
		}

	}

?>
