<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.tableview.columns.php';
	require_once MODULE_ROOT.'inc.tableview.form.php';

	class TableView implements Iterator, ArrayAccess {

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
		protected $items_on_page = 10;

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
			'search' => true);

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
		}

		public function features() { return $this->features; }
		public function set_features($features) { $this->features = $features; }
		public function enable($features) { $this->set_feature_state($features, true); }
		public function disable($features) { $this->set_feature_state($features, false); }

		public function enabled($feature)
		{
			return isset($this->features[$feature]) && $this->features[$feature];
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
			$js = <<<EOD
<script type="text/javascript">
//<![CDATA[
{$this->javascript_fragments}

EOD;
			if($this->enabled('order'))
				$js .= <<<EOD
function order(col) {
	var form = document.getElementById('$form_id');
	var order = form.$box_id{$p}order;
	var dir = form.$box_id{$p}dir;
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
	var start = form.$box_id{$p}start;
	var sv = parseInt(start.value);
	if(isNaN(sv))
		sv = 0;
	start.value=sv+step;
	start.value = Math.min(Math.max(start.value,0),
		$count-($count%parseInt(form.$box_id{$p}limit.value)));
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

		/**
		 * Append or prepend TableViewColumn (column renderers) to the tableview
		 */
		public function append_column(TableViewColumn $column)
		{
			$column->set_tableview($this);
			$this->columns[$column->name()] = $column;
		}

		public function prepend_column(TableViewColumn $column)
		{
			$name = $column->name();
			if(isset($this->columns[$name]))
				unset($this->columns[$name]);
			$this->columns = array_merge(
				array($name => $column),
				$this->columns);
		}

		public function html()
		{
			$this->init();

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
					dgettext('swisdk', 'Cannot use TableView without DBOContainer')));
			if(!$this->form)
				$this->form = new TableViewForm();

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

			$form_enabled = array();
			foreach($this->features as $feature => $enabled)
				if($enabled)
					$form_enabled[] = $feature;
			$this->form->setup($form_enabled);
			$this->form->set_clauses($this->obj);
			$this->obj->init();

			$this->initialized = true;
		}

		public function column_count()
		{
			return count($this->columns);
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

		/**
		 * Renders the footer of a TableView where multiple rows can be
		 * selected.
		 */
		protected function multi_foot()
		{
			if(!$this->enabled('multi'))
				return;

			$id = $this->form->id();
			$gid = guardToken('delete');
			$delete = dgettext('swisdk', 'Really delete?');
			return '<div style="float:left">'
				.sprintf(dgettext('swisdk', '%s edit %s or %s delete %s checked'),
					'<a href="javascript:tv_edit()">',
					'</a>',
					'<a href="javascript:tv_delete()">',
					'</a>')
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
	if(!confirm('$delete'))
		return;
	var form = document.getElementById('$id');
	form.action = form.action.replace(/\/_list/, '/_delete/multiple?guard=$gid');
	form.submit();
}
function tv_toggle(elem)
{
	var elems = document.getElementById('$id').getElementsByTagName('tbody')[1].getElementsByTagName('input');
	for(i=0; i<elems.length; i++) {
		elems[i].checked = elem.checked;
		var node = elems[i].parentNode.parentNode;
		if(node.tagName=='TR') {
			if(elems[i].checked)
				node.className += ' checked';
			else
				node.className = node.className.replace(/checked/g, '');
		}
	}
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

		protected function render_row(&$row, $class=null)
		{
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

			list($first, $count, $last) = $this->list_position();

			$str = sprintf(dgettext('swisdk', 'displaying %s &ndash; %s of %s'), $first, $last, $count);
			$skim = sprintf(dgettext('swisdk', 'skim %s backwards %s or %s forwards %s'),
				'<a href="javascript:skim(-'.$this->items_on_page.')">',
				'</a>',
				'<a href="javascript:skim('.$this->items_on_page.')">',
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
function init_tableview()
{
	var tables = document.getElementsByTagName('table');
	for(i=0; i<tables.length; i++) {
		if(tables[i].className=='s-table') {
			var rows = tables[i].getElementsByTagName('tbody')[0].getElementsByTagName('tr');
			for(j=0; j<rows.length; j++) {
				rows[j].onclick = function(){
					var cb = this.getElementsByTagName('input')[0];
					cb.checked = !cb.checked;
					cb.onchange();
				}
				var cb = rows[j].getElementsByTagName('input')[0];
				cb.onchange = function(){
					var row = this.parentNode.parentNode;
					if(this.checked)
						row.className += ' checked';
					else
						row.className =
							row.className.replace(/checked/g, '');
				}
				cb.onclick = function(){
					// hack. revert toggle effect of tr.onclick
					this.checked = !this.checked;
				}
				if(cb.checked)
					rows[j].className += ' checked';
			}
		}
	}
}
add_event(window, 'load', init_tableview);
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
