<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class TableView implements Iterator, ArrayAccess {

		/**
		 * TableViewColumn instances
		 */
		protected $columns = array();

		/**
		 * the data to be rendererd (nested array)
		 */
		protected $data = array();

		/**
		 * title
		 */
		protected $title;

		public function title()
		{
			return $this->title;
		}

		public function set_title($title)
		{
			$this->title = $title;
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
			return $this->render_head()
				.$this->render_body()
				.$this->render_foot();
		}

		/**
		 * @param data: nested array
		 */
		public function set_data($data)
		{
			$this->data = $data;
		}

		public function column_count()
		{
			return count($this->columns);
		}

		protected function render_head()
		{
			$html = "<table class=\"s-table\">\n<thead>\n<tr>\n";
			if($t = $this->title())
				$html .= "<th colspan=\"".count($this->columns)."\">"
					."<big><strong>"
					.$t."</strong></big></th>\n</tr>\n<tr>\n";
			foreach($this->columns as &$col)
				$html .= '<th>'.$col->title()."</th>\n";
			$html .= "</tr>\n</thead>\n";
			return $html;
		}

		protected function render_body()
		{
			$html = "<tbody>\n";
			foreach($this->data as &$row)
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
			foreach($this->columns as &$col)
				$html .= '<td>'.$col->html($row)."</td>\n";
			$html .= "</tr>\n";
			return $html;
		}

		protected function render_foot()
		{
			return "</table>\n";
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

	abstract class TableViewColumn {
		public function __construct($column, $title=null)
		{
			$this->args = func_get_args();
			$this->column = array_shift($this->args);
			$this->set_title(array_shift($this->args));
		}

		abstract public function html(&$data);

		public function column()	{ return $this->column; }
		public function title()		{ return $this->title; }
		public function name()		{ return $this->column; }
		public function set_title($t)
		{
			$this->title = dgettext('swisdk', $t);
		}

		public function set_tableview(&$tableview)
		{
			$this->tableview = $tableview;
		}

		protected $column;
		protected $title;
		protected $args;
		protected $tableview;
	}

	/**
	 * TextTableViewColumn takes a third parameter: maximal string length
	 */
	class TextTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$str = strip_tags($data[$this->column]);
			if(isset($this->args[0]) && $ml = $this->args[0])
				return substr($str, 0, $ml).(strlen($str)>$ml?'&hellip;':'');
			return $data[$this->column];
		}
	}

	class BoolTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			return $data[$this->column]
				?dgettext('swisdk', 'true')
				:dgettext('swisdk', 'false');
		}
	}

	/**
	 * Example template:
	 * <a href="/overview/{item_id}">{item_title}</a>
	 */
	class TemplateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->vars === null) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $this->args[0],
					$matches, PREG_PATTERN_ORDER);
				if(isset($matches[1]))
					$this->vars = $matches[1];
				foreach($this->vars as $v)
					$this->patterns[] = '/\{' . $v . '\}/';
			}

			$vals = array();
			foreach($this->vars as $v)
				$vals[] = $data[$v];

			return preg_replace($this->patterns, $vals, $this->args[0]);
		}

		protected $vars = null;
		protected $patterns = null;
	}

	/**
	 * third parameter: strftime(3)-formatted string
	 */
	class DateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->fmt === null) {
				if(isset($this->args[0]) && $this->args[0])
					$this->fmt = $this->args[0];
				else
					$this->fmt = '%d.%m.%Y : %H:%M';
			}
			return strftime($this->fmt, $data[$this->column]);
		}

		protected $fmt = null;
	}

	/**
	 * pass a callback instead of a field name
	 */
	class CallbackTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$method = $this->column;
			return call_user_func($method, $data);
		}
	}

	/**
	 * type hint for DBTableView
	 */
	abstract class NoDataTableViewColumn extends TableViewColumn {
		public function title()
		{
			return null;
		}
	}

	/**
	 * displays edit and delete links by default
	 */
	class CmdsTableViewColumn extends NoDataTableViewColumn {
		public function html(&$data)
		{
			$id = $data[$this->column];
			$gid = guardToken('delete');
			$delete = dgettext('swisdk', 'Really delete?');
			$prefix = Swisdk::config_value('runtime.webroot.img', '/images');
			$html =<<<EOD
<a href="{$this->title}_edit/$id"><img src="$prefix/icons/database_edit.png" alt="edit" /></a>
<a href="{$this->title}_delete/$id" onclick="if(confirm('$delete')){this.href+='?guard=$gid';}else{this.parentNode.parentNode.onclick();return false;}">
	<img src="$prefix/icons/database_delete.png" alt="delete" />
</a>
EOD;
			return $html;
		}

		public function name()
		{
			return '__cmd_'.$this->column;
		}
	}

	/**
	 * pass a DBObject class as third parameter
	 *
	 * DBTableViewColumn(field, title, DBObject-class, DBOContainer instance(?), template=null)
	 */
	class DBTableViewColumn extends TableViewColumn {
		protected function init_column()
		{
			if($this->initialized)
				return;
			$this->initialized = true;

			$this->db_class = $this->args[0];
			$this->dbobj = $this->args[1];

			if(isset($this->args[2]) && $t = $this->args[2]) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $t,
					$matches, PREG_PATTERN_ORDER);
				if(isset($matches[1]))
					$this->vars = $matches[1];
				foreach($this->vars as $v)
					$this->patterns[] = '/\{' . $v . '\}/';

				$this->db_data = DBOContainer::find($this->db_class)->data();
			} else {
				$doc = DBOContainer::find($this->db_class);
				foreach($doc as $id => &$obj)
					$this->db_data[$id] = $obj->title();
			}
		}

		public function html(&$data)
		{
			$this->init_column();
			if($this->vars!==null) {
				$vals = array();
				$record =& $this->db_data[$data[$this->column]];
				foreach($this->vars as $v)
					$vals[] = $record[$v];

				return preg_replace($this->patterns, $vals, $this->args[2]);
			} else {
				$val = $data[$this->column];
				if(!isset($this->db_data[$val]))
					return null;
				return $this->db_data[$val];
			}
		}

		protected $initialized = false;

		protected $db_data = null;
		protected $db_class = null;
		protected $dbobj = null;

		protected $vars = null;
		protected $patterns = null;
	}

	/**
	 * TableViewColumn for data from n-to-m or 3way relations
	 */
	class ManyToManyDBTableViewColumn extends DBTableViewColumn {
		public function html(&$data)
		{
			$this->init_column();
			$p = $this->dbobj->primary();
			if($this->reldata===null) {
				$relations = $this->dbobj->relations();
				$rel = $relations[$this->db_class];
				$rdata = DBObject::db_get_array(sprintf(
					'SELECT %s,%s FROM %s WHERE %s IN (%s)',
					$p,$rel['foreign'], $rel['table'], $p,
					implode(',', $this->tableview->dbobj()->ids())));
				$this->reldata = array();
				foreach($rdata as $row)
					$this->reldata[$row[$p]][] = $row[$rel['foreign']];
			}

			if(!isset($data[$p]) || !isset($this->reldata[$data[$p]]))
				return;
			$vals = $this->reldata[$data[$p]];
			$tokens = array();
			foreach($vals as $v)
				if(isset($this->db_data[$v])) {
					if($this->vars!==null) {
						$vals = array();
						$record =& $this->db_data[$v];
						foreach($this->vars as $var)
							$vals[] = $record[$var];

						$tokens[] = preg_replace($this->patterns, $vals, $this->args[2]);
					} else
						$tokens[] = $this->db_data[$v];
				}
			return implode(', ', $tokens);
		}

		protected $reldata = null;
	}

?>
