<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.form.php';

	class TableViewForm extends Form {
		protected $search;
		protected $action;

		public function setup($which=null)
		{
			$this->search = $this->box('search');
			$this->action = $this->box('action');

			$this->search->bind($this->dbobj());
			$this->action->bind($this->dbobj());

			if($which || is_array($which)) {
				if(!is_array($which))
					$which = explode(',', $which);
				foreach($which as $m)
					if(method_exists($this, $m = 'setup_'.$m))
						$this->$m();
			} else {
				$methods = get_class_methods($this);
				foreach($methods as $method)
					if(strpos($method, 'setup_')===0)
						$this->$method();
			}

			if($defaults = getInput('search_form')) {
				foreach($defaults as $k => $v)
					$this->dbobj->$k = $v;
			}

			$this->init();
		}

		public function setup_search()
		{
		}

		public function add_default_items()
		{
			$this->box('search')->set_title(dgettext('swisdk', 'Search form'));
			$this->box('action')->set_title(dgettext('swisdk', 'Actions'));
			$this->search->add(new SubmitButton());
		}

		public function setup_order()
		{
			$box = $this->box('search');
			$box->add($this->dbobj->name('order'), new HiddenInput());
			$box->add($this->dbobj->name('dir'), new HiddenInput());
		}

		public function setup_paging()
		{
			$box = $this->box('search');
			$box->add($this->dbobj->name('start'), new HiddenInput());
			$box->add($this->dbobj->name('limit'), new HiddenInput());
		}

		public function setup_persistence()
		{
			$this->init();

			$url = Swisdk::config_value('runtime.controller.url');

			if(getInput('swisdk2_persistence_reset')
					&& isset($_SESSION['swisdk2']['am_list_persistence'][$url]))
				unset($_SESSION['swisdk2']['am_list_persistence'][$url]);

			if(isset($_SESSION['swisdk2']['am_list_persistence'][$url])
					&& ((isset($_SERVER['HTTP_REFERER'])
						&& strpos($_SERVER['HTTP_REFERER'],
							$url.'_list')===false)
					|| count($_POST)==0)) {
				$this->dbobj->set_data(unserialize(
					$_SESSION['swisdk2']['am_list_persistence'][$url]));
			}

			$_SESSION['swisdk2']['am_list_persistence'][$url] =
					serialize($this->dbobj->data());

			$this->box('search')->set_title($this->box('search')->title
				.' <small>(<a href="?swisdk2_persistence_reset=1">'
				.dgettext('swisdk', 'reset listing')
				.'</a>)</small>');

		}

		/**
		 * add fulltext search field to form
		 */
		public function add_fulltext_field($title=null)
		{
			$this->box('search')->add($this->dbobj()->name('query'),
				new TextInput(),
				($title?$title:dgettext('swisdk', 'Search')));
		}

		public function name()
		{
			return Form::to_form_id($this->dbobj());
		}

		/**
		 * call this function with your DBOContainer prior to
		 * initialization!
		 */
		public function set_clauses(DBOContainer &$container)
		{
			$obj = $this->box('search')->dbobj();
			$fields = $obj->field_list();
			if(isset($fields[$obj->order]))
				$container->add_order_column($obj->order, $obj->dir);
			$container->set_limit($obj->start, $obj->limit);
			if($query = $obj->query)
				$container->set_fulltext($query);
		}
	}

	class TableViewFormRenderer extends NoTableFormRenderer {
		protected $current = 'search';
		protected $html_fragments = array();

		public function __construct()
		{
			// do nothing
		}

		public function html_start()
		{
			return $this->html_start.$this->html_fragments['search'].'<br />';
		}

		public function html_end()
		{
			return $this->html_fragments['action']
				.'<script type="text/javascript">'."\n"
				."//<![CDATA[\n"
				.$this->javascript
				."\n//]]>\n</script>\n"
				.$this->html_end;
		}

		protected function visit_Form_start($obj)
		{
			$this->form_submitted = $obj->submitted();
		}

		protected function visit_Form_end($obj)
		{
			$this->_collect_javascript($obj);

			$valid = '';
			list($html, $js) = $this->_validation_html($obj);
			$this->add_html_start(
				'<form method="post" action="'.htmlspecialchars($_SERVER['REQUEST_URI'])
				.'" id="'.$obj->id()."\" $html class=\"sf-form\" "
				."accept-charset=\"utf-8\">\n<div>\n".$js);
			$this->add_html_end($this->_message_html($obj));
			$this->add_html_end($this->_info_html($obj));
			$this->add_html_end("</div></form>\n");
		}



		protected function visit_FormBox_end($obj)
		{
			parent::visit_FormBox_end($obj);
			$this->html_fragments[$obj->name()] = $this->html;
			$this->html = '';
		}
	}

?>
