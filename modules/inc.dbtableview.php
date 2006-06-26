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
			$box = $this->box('search');
			$box->add($p.'order', new HiddenInput());
			$box->add($p.'dir', new HiddenInput());
			$box->add($p.'start', new HiddenInput());
			$box->add($p.'limit', new HiddenInput());
			if($this->setup_additional()!==false) {
				$this->set_title('Search form');
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
		protected function add_fulltext_field()
		{
			$this->box('search')->add($this->dbobj()->name('query'));
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
				.$this->javascript()
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

	/**
	 * display records in a searchable and sortable table
	 */
	class DBTableView extends TableView {

		/**
		 * DBOContainer instance
		 */
		protected $obj;

		/**
		 * search form instance
		 */
		protected $form;

		/**
		 * the maximal count of items to display on one page
		 */
		protected $items_on_page = 10;

		/**
		 * @param obj: DBOContainer instance, DBObject instance or class
		 * @param form: Form instance or class
		 */
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

		public function append_auto($field, $title=null)
		{
			require_once MODULE_ROOT.'inc.builder.php';
			static $builder = null;
			if($builder===null)
				$builder = new TableViewBuilder();
			if(is_array($field)) {
				foreach($field as $f)
					$builder->create_auto($this, $f, null);
			} else
				return $builder->create_auto($this, $field, $title);
		}

		/**
		 * you need to call init() prior to adding any columns 
		 */
		public function init()
		{
			if(!$this->obj)
				SwisdkError::handle(new FatalError(
					'Cannot use DBTableView without DBOContainer'));
			if(!$this->form)
				$this->form = new DBTableViewForm();

			$dbo = $this->obj->dbobj_clone();

			$dbo->order = isset($this->form_defaults['order'])?
				$this->form_defaults['order']:$dbo->primary();
			$dbo->dir = isset($this->form_defaults['dir'])?
				$this->form_defaults['dir']:'ASC';
			$dbo->start = isset($this->form_defaults['start'])?
				$this->form_defaults['start']:0;
			$dbo->limit = isset($this->form_defaults['limit'])?
				$this->form_defaults['limit']:$this->items_on_page;

			$this->form->bind($dbo);

			$this->form->setup();
			$this->form->set_clauses($this->obj);
			$this->obj->init();
		}

		protected $form_defaults = array();

		public function set_form_defaults($defaults)
		{
			$this->form_defaults = $defaults;
		}

		/**
		 * @return the DBOContainer
		 */
		public function &dbobj()
		{
			return $this->obj;
		}

		/**
		 * @return the form instance
		 */
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
			$html = "<table class=\"s-table\">\n<thead>\n<tr>\n";
			foreach($this->columns as &$col) {
				$html .= '<th>';
				if($col instanceof NoDataTableViewColumn) {
					$html .= $col->title();
				} else {
					$html .= '<a href="#" onclick="order(\''.$col->column().'\')">';
					$html .= $col->title();
					if($col->column()==$order) {
						$html .= $dir=='DESC'?'&nbsp;&uArr;':'&nbsp;&dArr;';
					}
					$html .= '</a>';
				}
				$html .= "</th>\n";
			}

			$html .= "</tr>\n</thead>\n";
			return $html;
		}

		protected function render_foot()
		{
			$colcount = count($this->columns);
			list($first, $count, $last) = $this->list_position();

			$str = 'displaying '.$first.'&ndash;'.$last.' of '.$count;
			return "<tfoot>\n<tr>\n<td colspan=\"".$colcount.'">'
				.$this->multi_foot()
				.$str.' | skim '
				.'<a href="javascript:skim(-'.$this->items_on_page.')">backwards</a> or '
				.'<a href="javascript:skim('.$this->items_on_page.')">forwards</a>'
				."</td>\n</tr>\n</tfoot>\n</table>";
		}

		protected function multi_foot()
		{
			$id = $this->form->id();
			return '<div style="float:left">'
				.'<a href="javascript:tv_edit()">edit</a>'
				.' or '
				.'<a href="javascript:tv_delete()">delete</a> checked'
				. ($a_html?'<br />'.$a_html:'')
				.'</div>'
				.<<<EOD
<script type="text/javascript">
//<![CDATA[
function tv_edit()
{
	var form = document.getElementById('$id');
	form.action = form.action.replace(/\/_list/, '/_edit/multiple');
	form.submit();
}
function tv_delete()
{
	if(!confirm('Really delete?'))
		return;
	var form = document.getElementById('$id');
	form.action = form.action.replace(/\/_list/, '/_delete/multiple');
	form.submit();
}
function tv_toggle(elem)
{
	var elems = document.getElementById('$id').getElementsByTagName('input');
	for(i=0; i<elems.length; i++)
		elems[i].checked = elem.checked;
}
//]]>
</script>
EOD;
		}

		protected function list_position()
		{
			$formobj = $this->form->dbobj();
			$p = $formobj->_prefix();
			$data = $formobj->data();

			return array($first = $data[$p.'start']+1,
				$count = $this->obj->total_count(),
				min($count, $first+$data[$p.'limit']-1));
		}

		public function html()
		{
			$this->set_data($this->obj->all_data());
			$this->prepend_column(new IDTableViewColumn(
				$this->dbobj()->dbobj()->primary()));
			$renderer = new TableViewFormRenderer();
			$this->form->accept($renderer);
			return $renderer->html_start()
				.parent::html()
				.$this->form_javascript()
				.$renderer->html_end();
		}

		protected function form_javascript()
		{
			$id = $this->form->id();
			$p = $this->form->dbobj()->_prefix();
			return <<<EOD
<script type="text/javascript">
//<![CDATA[
function order(col) {
	var form = document.getElementById('$id');
	var order = form.{$p}order;
	var dir = form.{$p}dir;
	if(order.value==col) {
		dir.value=(dir.value=='DESC'?'ASC':'DESC');
	} else {
		dir.value='ASC';
		order.value=col;
	}
	form.submit();
}
function skim(step)
{
	var form = document.getElementById('$id');
	var start = form.{$p}start;
	start.value=parseInt(start.value)+step;
	form.submit();
}
//]]>
</script>
EOD;
		}
	}

	class IDTableViewColumn extends NoDataTableViewColumn {
		public function html(&$data)
		{
			static $selected = null;
			if($selected===null) {
				if(($ids = getInput($this->column)) && is_array($ids))
					$selected = array_flip($ids);
				else
					$selected = array();
			}
			$id = $data[$this->column];
			return sprintf('<input type="checkbox" name="%s[]" value="%d" %s />',
				$this->column, $id, isset($selected[''.$id])?'checked="checked"':'');
		}

		public function name()
		{
			return '__id_'.$this->column;
		}

		public function title()
		{
			return '<input type="checkbox" onchange="tv_toggle(this)" />';
		}
	}

?>
