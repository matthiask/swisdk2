<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT . 'site/inc.site.php';
	require_once MODULE_ROOT . 'inc.tableview.php';
	require_once MODULE_ROOT . 'inc.component.php';
	require_once MODULE_ROOT . 'inc.data.php';
	require_once MODULE_ROOT . 'inc.form.php';

	class DBTableViewForm extends Form {
		public function setup()
		{
			$p = $this->dbobj()->_prefix();
			$this->add($p.'order', new HiddenInput());
			$this->add($p.'dir', new HiddenInput());
			$this->add($p.'start', new HiddenInput());
			$this->add($p.'limit', new HiddenInput());
			if($this->setup_additional()!==false) {
				$this->set_title('Search form');
				$this->add(new SubmitButton());
			}
		}

		protected function setup_additional()
		{
			return false;
		}

		protected function add_fulltext_field()
		{
			$this->add($this->dbobj()->name('query'));
		}

		public function name()
		{
			return Form::to_form_id($this->dbobj());
		}

		public function set_clauses(DBOContainer &$container)
		{
			$obj = $this->dbobj();
			$container->add_order_column($obj->order, $obj->dir);
			$container->set_limit($obj->start, $obj->limit);
			if($query = $obj->query)
				$container->set_fulltext($query);
		}
	}

	class DBTableView extends TableView implements ArrayAccess {

		protected $obj;
		protected $form;

		protected $items_on_page = 10;

		public function __construct($obj=null, $form=null)
		{
			if($obj)
				$this->bind($obj);
			if($form)
				$this->set_form($form);
		}

		public function bind($obj)
		{
			if($obj instanceof DBOContainer)
				$this->obj = $obj;
			else
				// class name or DBObject instance!
				$this->obj = DBOContainer::create($obj);
		}

		public function set_form($form = 'DBTableViewForm')
		{
			if($form instanceof Form)
				$this->form = $form;
			else if(class_exists($form))
				$this->form = new $form();
		}

		public function init()
		{
			if(!$this->obj)
				SwisdkError::handle(new FatalError(
					'Cannot use DBTableView without DBOContainer'));
			if(!$this->form)
				$this->form = new DBTableViewForm();

			$obj = $this->obj->dbobj();

			$this->form->bind(DBObject::create_with_data(
				'DBTableView'.$obj->_class(),
				array(
					'order' => $obj->primary(),
					'dir' => 'ASC',
					'start' => 0,
					'limit' => $this->items_on_page
				)));

			$this->form->setup();
			$this->form->set_clauses($this->obj);
			$this->obj->init();
			$this->set_data($this->obj->data());
		}

		public function &dbobj()
		{
			return $this->obj;
		}

		public function form()
		{
			if(!$this->form) {
				$form = new DBTableViewForm();
			}
			return $this->form;
		}

		protected function render_head()
		{
			$order = $this->form->dbobj()->order;
			$dir = $this->form->dbobj()->dir;
			$html = '<thead><tr>';
			foreach($this->columns as &$col) {
				$html .= '<th><a href="#" onclick="order(\''.$col->column().'\')">';
				$html .= $col->title();
				if($col->column()==$order
						&& (!$col instanceof CmdsTableViewColumn)) {
					$html .= $dir=='DESC'?'&nbsp;&uArr;':'&nbsp;&dArr;';
				}
				$html .= '</a></th>';
			}

			$html .= "</tr></thead>\n";
			return $html;
		}

		protected function render_foot()
		{
			$colcount = count($this->columns);
			$formobj = $this->form->dbobj();
			$p = $formobj->_prefix();
			$data = $formobj->data();

			$first = $data[$p.'start']+1;
			$count = $this->obj->total_count();
			$last = min($count, $first+$data[$p.'limit']-1);

			$str = 'displaying '.$first.'&ndash;'.$last.' of '.$count;
			return '<tfoot><tr><td colspan="'.$colcount.'">'.$str.' | skim '
				.'<a href="javascript:skim(-'.$this->items_on_page.')">backwards</a> or '
				.'<a href="javascript:skim('.$this->items_on_page.')">forwards</a>'
				.'</td></tr></tfoot>';
		}

		public function html()
		{
			$id = $this->form->id();
			$p = $this->form->dbobj()->_prefix();
			return $this->form->html().parent::html().<<<EOD
<script type="text/javascript">
function order(col) {
	var order = document.forms.$id.{$p}order;
	var dir = document.forms.$id.{$p}dir;
	if(order.value==col) {
		dir.value=(dir.value=='DESC'?'ASC':'DESC');
	} else {
		dir.value='ASC';
		order.value=col;
	}
	document.forms.$id.submit();
}
function skim(step)
{
	var start = document.forms.$id.{$p}start;
	start.value=parseInt(start.value)+step;
	document.forms.$id.submit();
}
</script>
EOD;
		}

		/**
		 * ArrayAccess implementation (see PHP SPL)
		 */
		public function offsetExists($offset) { return isset($this->columns[$offset]); }
		public function offsetGet($offset) { return $this->columns[$offset]; }
		public function offsetSet($offset, $value)
		{
			if($offset===null)
				$this->columns[] = $value;
			else
				$this->columns[$offset] = $value;
		}
		public function offsetUnset($offset) { unset($this->columns[$offset]); }
	}

?>
