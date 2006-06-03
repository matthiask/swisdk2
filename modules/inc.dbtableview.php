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
				$html .= '<th>';
				if(!($col instanceof NoDataTableViewColumn)) {
					$html .= '<a href="#" onclick="order(\''.$col->column().'\')">';
					$html .= $col->title();
					if($col->column()==$order
							&& (!$col instanceof NoDataTableViewColumn)) {
						$html .= $dir=='DESC'?'&nbsp;&uArr;':'&nbsp;&dArr;';
					}
					$html .= '</a>';
				}
				$html .= '</th>';
			}

			$html .= "</tr></thead>\n";
			return $html;
		}

		protected function render_foot()
		{
			$colcount = count($this->columns);
			list($first, $count, $last) = $this->list_position();

			$str = 'displaying '.$first.'&ndash;'.$last.' of '.$count;
			return '<tfoot><tr><td colspan="'.$colcount.'">'.$str.' | skim '
				.'<a href="javascript:skim(-'.$this->items_on_page.')">backwards</a> or '
				.'<a href="javascript:skim('.$this->items_on_page.')">forwards</a>'
				.'</td></tr></tfoot>';
		}

		protected function list_position()
		{
			$formobj = $this->form->dbobj();
			$p = $formobj->_prefix();
			$data = $formobj->data();

			return array($data[$p.'start']+1,
				$this->obj->total_count(),
				min($count, $first+$data[$p.'limit']-1));
		}

		public function html()
		{
			return $this->form->html().parent::html().$this->form_javascript();
		}

		protected function form_javascript()
		{
			$id = $this->form->id();
			$p = $this->form->dbobj()->_prefix();
			return <<<EOD
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

	class IDTableViewColumn extends NoDataTableViewColumn {
		public function html(&$data)
		{
			$id = $data[$this->column];
			return sprintf('<input type="checkbox" name="%s[]" value="%d" />',
				$this->column, $data[$this->column]);
		}

		public function name()
		{
			return '__id_'.$this->column;
		}
	}

	class MultiDBTableView extends DBTableView {
		protected $target = null;

		public function set_target($target)
		{
			$this->target = $target;
		}

		public function html()
		{
			array_unshift($this->columns, new IDTableViewColumn(
				null, $this->dbobj()->dbobj()->primary()));
			return $this->form->html()
				.$this->form_javascript()
				.'<form name="tableview" action="'.$this->target.'" method="post">'
				.'<input type="hidden" name="multiple" value="1" />'
				.'<input type="hidden" name="command" value="" />'
				.'<table>'
				.$this->render_head()
				.$this->render_body()
				.$this->render_foot()
				.'</table></form>';
		}

		public function render_foot()
		{
			$colcount = count($this->columns);
			list($first, $count, $last) = $this->list_position();

			$str = 'displaying '.$first.'&ndash;'.$last.' of '.$count;
			return '<tfoot><tr>'
				.'<td colspan="'.$colcount.'">'
				.'<div style="float:left">'
					.'<a href="javascript:tv_edit()">edit</a>'
					.' or '
					.'<a href="javascript:tv_delete()">delete</a> checked'
				.'</div>'
				.$str.' | skim '
				.'<a href="javascript:skim(-'.$this->items_on_page.')">backwards</a> or '
				.'<a href="javascript:skim('.$this->items_on_page.')">forwards</a>'
				.'</td>'
				.'</tr></tfoot>'.<<<EOD
<script type="text/javascript">
function tv_edit()
{
	document.forms.tableview.command.value='edit';
	document.forms.tableview.action+='_edit/multiple';
	document.forms.tableview.submit();
}
function tv_delete()
{
	if(!confirm('Really delete?'))
		return;
	document.forms.tableview.command.value='delete';
	document.forms.tableview.action+='_delete/multiple';
	document.forms.tableview.submit();
}
</script>
EOD;
		}
	}

?>
