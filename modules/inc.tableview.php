<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class TableView implements Iterator {
		
		protected $columns = array();
		protected $data = array();
		protected $html;
		
		public function append_column(TableViewColumn $column)
		{
			$this->columns[$column->name()] = $column;
		}

		public function html()
		{
			return '<table>'
				. $this->render_head()
				. $this->render_body()
				. $this->render_foot()
				. '</table>';
		}

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
			$html = '<thead><tr>';
			foreach($this->columns as &$col)
				$html .= '<th>' . $col->title() . '</th>';
			$html .= "</tr></thead>\n";
			return $html;
		}

		protected function render_body()
		{
			$html = '<tbody>';
			foreach($this->data as &$row)
				$html .= $this->render_row($row);
			$html .= "</tbody>\n";
			return $html;
		}

		protected function render_row(&$row)
		{
			$html = '<tr>';
			foreach($this->columns as &$col)
				$html .= '<td>' . $col->html($row) . '</td>';
			$html .= "</tr>\n";
			return $html;
		}

		protected function render_foot()
		{
			return '';
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */
		public function rewind() { reset($this->columns); }
		public function current() { return current($this->columns); }
		public function key() { return key($this->columns); }
		public function next() { return next($this->columns); }
		public function valid() { return $this->current() !== false; }
	}

	abstract class TableViewColumn {
		public function __construct($title, $column)
		{
			$this->args = func_get_args();
			$this->title = array_shift($this->args);
			$this->column = array_shift($this->args);
		}

		abstract public function html(&$data);

		public function title()		{ return $this->title; }
		public function column()	{ return $this->column; }
		public function name()		{ return $this->column; }
		public function set_title($t)	{ $this->title = $t; }

		protected $title;
		protected $column;
		protected $args;
	}

	/**
	 * TextTableViewColumn takes a third parameter: maximal string length
	 */
	class TextTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$str = $data[$this->column];
			if($ml = $this->args[0])
				return substr($str, 0, $ml).(strlen($str)>$ml?'&hellip;':'');
			return $data[$this->column];
		}
	}

	class BoolTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			return $data[$this->column]?'true':'false';
		}
	}

	class EnumTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$str = $data[$this->column];
			if(isset($this->args[$str]))
				return $this->args[$str];
			return $str;
		}
	}

	class TemplateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->vars === null) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $this->column,
					$matches, PREG_PATTERN_ORDER);
				if(isset($matches[1]))
					$this->vars = $matches[1];
				foreach($this->vars as $v)
					$this->patterns[] = '/\{' . $v . '\}/';
			}

			$vals = array();
			foreach($this->vars as $v)
				$vals[] = $data[$v];

			return preg_replace($this->patterns, $vals, $this->column);
		}

		protected $vars = null;
		protected $patterns = null;
	}

	class DateTableViewColumn extends TableViewColumn {
		public function __construct($title, $column, $fmt = '%d.%m.%Y : %H:%M')
		{
			parent::__construct($title, $column);
			$this->fmt = $fmt;
		}

		public function html(&$data)
		{
			return strftime($this->fmt, $data[$this->column]);
		}

		protected $fmt;
	}

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

	class CmdsTableViewColumn extends NoDataTableViewColumn {
		public function html(&$data)
		{
			$id = $data[$this->column];
			$html =<<<EOD
<a href="{$this->title}_edit/$id">edit</a><br />
<a href="{$this->title}_delete/$id">delete</a>
EOD;
			return $html;
		}

		public function name()
		{
			return '__cmd_'.$this->column;
		}
	}

	class DBTableViewColumn extends TableViewColumn {
		public function __construct($title, $column, $db_class)
		{
			parent::__construct($title, $column);
			$this->db_class = $db_class;
		}
		
		public function html(&$data)
		{
			$val = $data[$this->column];
			if($this->db_data===null) {
				$doc = DBOContainer::find($this->db_class);
				foreach($doc as $id => &$obj) {
					$this->db_data[$id] = $obj->title();
				}
			}
			
			if(!isset($this->db_data[$val])) {
				return null;
			}
			return $this->db_data[$val];
		}

		protected $db_data = null;
		protected $db_class = null;
	}

?>
