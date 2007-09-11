<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.tableview.columns.php';
	require_once MODULE_ROOT.'inc.tableview.form.php';

	class TableView implements Iterator, ArrayAccess, IHtmlComponent {

		/**
		 * TableViewColumn instances
		 */
		protected $columns = array();

		/**
		 * the data to be rendererd
		 */
		protected $obj;

		/**
		 * title
		 */
		protected $title;

		/**
		 * javascript fragments
		 */
		protected $javascript_fragments;

		/**
		 * search form instance
		 */
		protected $form;

		/**
		 * the maximal count of items to display on one page
		 */
		protected $items_on_page = 20;

		/**
		 * features
		 * is selecting and acting on more than one row at a time enabled?
		 * is row sorting by clicking on the column headers enabled?
		 * is paging enabled?
		 * is searching enabled?
		 */
		protected $features = array(
			'multi' => true,
			'order' => true,
			'paging' => true,
			'search' => true,
			'persistence' => true);

		/**
		 * search form default values
		 */
		protected $form_defaults = array();

		/**
		 * is initialized?
		 */
		protected $initialized = false;

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

			Swisdk::needs_library('jquery');
		}

		public function features() { return $this->features; }
		public function set_features($features) { $this->features = $features; }
		public function enable($features) { $this->set_feature_state($features, true); }
		public function disable($features) { $this->set_feature_state($features, false); }

		public function enabled($feature)
		{
			return s_test($this->features, $feature);
		}

		public function set_feature_state($features, $state)
		{
			if(!is_array($features))
				$features = explode(',', $features);
			foreach($features as $f)
				$this->features[$f] = $state;
		}

		public function title()
		{
			return $this->title;
		}

		public function set_title($title)
		{
			$this->title = $title;
		}

		public function set_form_defaults($defaults)
		{
			$this->form_defaults = $defaults;
		}

		public function add_javascript($js)
		{
			$this->javascript_fragments .= $js;
		}

		public function javascript()
		{
			$form_id = $this->form->id();
			$box_id = $form_id.'search_';
			$p = $this->form->dbobj()->_prefix();
			list($first, $count, $last) = $this->list_position();

			foreach(array('order','dir','start','limit') as $t)
				$$t = $this->form->item($p.$t)->id();

			$js = <<<EOD
<script type="text/javascript">
//<![CDATA[
{$this->javascript_fragments}

EOD;
			if($this->enabled('order'))
				$js .= <<<EOD
function order(col) {
	var form = document.getElementById('$form_id');
	var order = form.$order;
	var dir = form.$dir;
	if(order.value==col) {
		dir.value=(dir.value=='DESC'?'ASC':'DESC');
	} else {
		dir.value='ASC';
		order.value=col;
	}
	form.submit();
}

EOD;
			if($this->enabled('paging'))
				$js .= <<<EOD
function skim(step)
{
	var form = document.getElementById('$form_id');
	var start = form.$start;
	var sv = parseInt(start.value);
	if(isNaN(sv))
		sv = 0;
	start.value=sv+step;
	start.value = Math.min(Math.max(start.value,0),
		$count-($count%parseInt(form.$limit.value)));
	form.submit();
}

EOD;
			$js .= <<<EOD
//]]>
</script>

EOD;
			return $js;
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

		public function append_auto_c($fields)
		{
			return $this->append_auto(explode(',', $fields));
		}

		/**
		 * Append or prepend TableViewColumn (column renderers) to the tableview
		 */
		public function append_column(TableViewColumn $column)
		{
			$column->set_tableview($this);
			$this->columns[$column->name()] = $column;
			return $column;
		}

		public function prepend_column(TableViewColumn $column)
		{
			$name = $column->name();
			s_unset($this->columns, $name);
			$this->columns = array_merge(
				array($name => $column),
				$this->columns);
		}

		public function html()
		{
			if($this->enabled('multi'))
				$this->prepend_column(new IDTableViewColumn(
					$this->dbobj()->dbobj()->primary()));
			$renderer = new TableViewFormRenderer();
			$this->form->accept($renderer);
			return $renderer->html_start()
				.$this->render_head()
				.$this->render_body()
				.$this->render_foot()
				.$this->javascript()
				.$renderer->html_end();
		}

		public function bind($obj)
		{
			$this->initialized = false;
			if($obj instanceof DBOContainer)
				$this->obj = $obj;
			else
				// class name or DBObject instance!
				$this->obj = DBOContainer::create($obj);
		}

		public function dbobj()
		{
			return $this->obj;
		}

		public function set_form($form = 'TableViewForm')
		{
			$this->initialized = false;
			if($form instanceof Form)
				$this->form = $form;
			else if(class_exists($form))
				$this->form = new $form();
		}

		/**
		 * @return the form instance
		 */
		public function form()
		{
			if(!$this->form) {
				$form = new TableViewForm();
			}
			return $this->form;
		}

		/**
		 * you need to call init() prior to adding any columns
		 */
		public function init()
		{
			if($this->initialized)
				return;

			if(!$this->obj)
				SwisdkError::handle(new FatalError(
					'Cannot use TableView without DBOContainer'));
			if(!$this->form)
				$this->form = new TableViewForm();

			$dbo = $this->obj->dbobj_clone();
			$dbo->id = -999;
			$this->form->bind($dbo);

			$this->initialized = true;
		}

		public function run()
		{
			$dbo = $this->form->dbobj();

			$dbo->order = s_get($this->form_defaults, 'order', $dbo->primary());
			$dbo->dir = s_get($this->form_defaults, 'dir', 'ASC');
			$dbo->start = s_get($this->form_defaults, 'start', 0);
			$dbo->limit = s_get($this->form_defaults, 'limit', $this->items_on_page);

			$form_enabled = array();
			foreach($this->features as $feature => $enabled)
				if($enabled)
					$form_enabled[] = $feature;
			$this->form->setup($form_enabled);
			$this->form->set_clauses($this->obj);
			$this->obj->init();
		}

		public function column_count()
		{
			return count($this->columns);
		}

		protected function list_position()
		{
			static $position = null;
			if($position===null) {
				$formobj = $this->form->dbobj();
				$p = $formobj->_prefix();
				$data = $formobj->data();
				$count = $this->obj->total_count();

				$position = array(
					min($first = $data[$p.'start']+1, $count),
					$count,
					min($count, $first+$data[$p.'limit']-1),
					$data[$p.'limit']);
			}
			return $position;
		}

		/**
		 * Renders the footer of a TableView where multiple rows can be
		 * selected.
		 */
		protected function multi_foot()
		{
			if(!$this->enabled('multi'))
				return;

			$id = $this->form->id();
			$gid = Swisdk::guard_token_f('guard');
			$delete = _T('Really delete?');

			$controller = Swisdk::config_value('runtime.controller.url');

			return '<div style="float:left">'
				.sprintf(_T('%sedit%s, %scopy%s or %sdelete%s checked'),
					'<a href="javascript:tv_edit()">',
					'</a>',
					'<a href="javascript:tv_copy()">',
					'</a>',
					'<a href="javascript:tv_delete()">',
					'</a>')
				.'</div>'
				.<<<EOD
<script type="text/javascript">
//<![CDATA[
function tv_edit(copy)
{
	var form = document.getElementById('$id');
	form.action = '{$controller}edit_multiple/';
	form.submit();
}
function tv_copy()
{
	var form = document.getElementById('$id');
	form.action = '{$controller}copy_multiple/';
	form.submit();
}
function tv_delete()
{
	if(!confirm('$delete'))
		return;
	var form = document.getElementById('$id');
	form.action = '{$controller}delete_multiple/?guard=$gid';
	form.submit();
}
function tv_toggle(elem)
{
	$('#$id tbody input:checkbox').each(function(){
		this.checked = elem.checked;
		var node = this.parentNode.parentNode;
		if(elem.checked)
			$(node).addClass('checked');
		else
			$(node).removeClass('checked');
	});
}
//]]>
</script>

EOD;
		}

		protected function render_head()
		{
			$html = "<table class=\"s-table\">\n<thead>\n<tr>\n";
			if($t = $this->title())
				$html .= "<th colspan=\"".count($this->columns)."\">"
					."<big><strong>"
					.$t."</strong></big></th>\n</tr>\n<tr>\n";
			if($this->enabled('order')) {
				$order = $this->form->dbobj()->order;
				$dir = $this->form->dbobj()->dir;
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
			} else {
				foreach($this->columns as &$col)
					$html .= '<th>'.$col->title()."</th>\n";
			}
			$html .= "</tr>\n</thead>\n";
			return $html;
		}

		protected function render_body()
		{
			$html = "<tbody>\n";
			foreach($this->obj as $row)
				$html .= $this->render_row($row);
			$html .= "</tbody>\n";
			return $html;
		}

		protected $odd = false;
		protected $rowclass_callback = null;

		public function set_rowclass_callback($callback)
		{
			$this->rowclass_callback = $callback;
		}

		protected function render_row(&$row, $class=null)
		{
			if($this->rowclass_callback)
				$class = call_user_func($this->rowclass_callback, $row);
			$this->odd = !$this->odd;
			if($this->odd)
				$class .= ' odd';
			if($class)
				$class = " class=\"$class\"";
			$html = "<tr{$class}>\n";
			foreach($this->columns as $col)
				$html .= $this->render_cell($col, $row);
			$html .= "</tr>\n";
			return $html;
		}

		protected function render_cell(&$column, &$data)
		{
			$class = $column->css_class();
			if($class)
				$class = ' class="'.$class.'"';
			return '<td'.$class.'>'.$column->html($data)."</td>\n";
		}

		protected function paging_foot()
		{
			if(!$this->enabled('paging'))
				return;

			list($first, $count, $last, $step) = $this->list_position();

			$str = sprintf(_T('displaying %s &ndash; %s of %s'), $first, $last, $count);
			$skim = sprintf(_T('skim %sbackwards%s or %sforwards%s'),
				'<a href="javascript:skim(-'.$step.')">',
				'</a>',
				'<a href="javascript:skim('.$step.')">',
				'</a>');
			return $str.' | '.$skim;
		}

		protected function render_foot()
		{
			$html = $this->multi_foot().$this->paging_foot();

			if($html) {
				$colcount = count($this->columns);
				$html = "<tfoot>\n<tr>\n<td colspan=\"".$colcount.'">'.$html
					."</td>\n</tr>\n</tfoot>\n";
			}

			$html .= "</table>\n";

			if(!$this->enabled('multi'))
				return $html;
			return $html.<<<EOD
<script type="text/javascript">
//<![CDATA[
$(function(){
	$('table.s-table tbody tr').each(function(){
		var row = $(this);
		var cb = $('input:checkbox', row).get(0);
		row.click(function(){
			cb.checked = !cb.checked;
			if(cb.checked)
				$(this).addClass('checked');
			else
				$(this).removeClass('checked');
		});
		$(cb).change(function(){
			if(this.checked)
				row.addClass('checked');
			else
				row.removeClass('checked');
		});
		$(cb).click(function(){
			cb.checked = !this.checked;
		});
		if(cb.checked)
			row.addClass('checked');
	});
});
//]]>
</script>

EOD;
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */
		public function rewind() { reset($this->columns); }
		public function current() { return current($this->columns); }
		public function key() { return key($this->columns); }
		public function next() { return next($this->columns); }
		public function valid() { return $this->current() !== false; }

		/**
		 * ArrayAccess implementation (see PHP SPL)
		 */
		public function offsetExists($offset) { return isset($this->columns[$offset]); }
		public function offsetGet($offset) { return $this->columns[$offset]; }
		public function offsetSet($offset, $value)
		{
			$value->set_tableview($this);
			if($offset===null)
				$this->columns[] = $value;
			else
				$this->columns[$offset] = $value;
		}
		public function offsetUnset($offset) { unset($this->columns[$offset]); }
	}

?>
