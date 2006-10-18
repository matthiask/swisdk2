<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.form.php';

	class TableViewForm extends Form {
		public function setup()
		{
			$p = $this->dbobj()->_prefix();
			$box = $this->box('search');
			$box->add($p.'order', new HiddenInput());
			$box->add($p.'dir', new HiddenInput());
			$box->add($p.'start', new HiddenInput());
			$box->add($p.'limit', new HiddenInput());
			if($this->setup_additional()!==false) {
				$this->set_title(dgettext('swisdk', 'Search form'));
				$this->box('search')->add(new SubmitButton());
			}
		}

		/**
		 * setup() above will add a title and a submit button to the form
		 * if you do not return false here, thereby making the form visible
		 */
		protected function setup_additional()
		{
			return false;
		}

		/**
		 * add fulltext search field to form
		 */
		protected function add_fulltext_field($title=null)
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

	class TableViewFormRenderer extends TableFormRenderer {
		protected $current = 'search';
		protected $grids = array();

		public function __construct()
		{
			// do nothing
		}

		public function html_start()
		{
			return $this->html_start.$this->grid('search')->html().'<br />';
		}

		public function html_end()
		{
			return $this->grid('action')->html()
				.'<script type="text/javascript">'."\n"
				."//<![CDATA[\n"
				.$this->javascript
				."\n//]]>\n</script>\n"
				.$this->html_end;
		}

		protected function &grid($which = null)
		{
			if(!$which)
				$which = $this->current;
			if(!isset($this->grids[$which]))
				$this->grids[$which] = new Layout_Grid();
			return $this->grids[$which];
		}

		protected function visit_FormBox_start($obj)
		{
			if($obj->name()==='search')
				$this->current = 'search';
			else
				$this->current = 'action';
			parent::visit_FormBox_start($obj);
		}
	}

?>
