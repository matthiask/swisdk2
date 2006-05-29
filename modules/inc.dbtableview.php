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

	class DBTableView extends TableView {

		protected $obj;
		protected $form;

		protected $items_on_page = 10;

		public function __construct($data_class, $form_class)
		{
			if($data_class instanceof DBOContainer)
				$this->obj = $data_class;
			else
				$this->obj = DBOContainer::create($data_class);

			$obj = $this->obj->dbobj();
			$fields = $obj->field_list();
			$relations_ = $obj->relations();

			$relations = array();
			foreach($relations_ as $class => &$r) {
				$relations[$r['field']] = $r;
			}

			foreach($fields as &$field) {
				$fname = $field['Field'];
				$pretty = $obj->pretty($fname);
				if(isset($relations[$fname])&&($fname!=$obj->primary())) {
					$this->append_column(new DBTableViewColumn(
						$pretty, $fname,
						$relations[$fname]['class']));
				} else if(strpos($fname,'dttm')!==false) {
					$this->append_column(new DateTableViewColumn(
						$pretty, $fname));
				} else {
					$this->append_column(new TextTableViewColumn(
						$pretty, $fname));
				}
			}

			if($form_class instanceof Form)
				$this->form = $form_class;
			else if(class_exists($form_class))
				$this->form = new $form_class();
			else
				$this->form = new DBTableViewForm();
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

		public function dbobj()
		{
			return $this->obj;
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
	}
?>
