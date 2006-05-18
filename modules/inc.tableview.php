<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class TableView {
		public function append_column(TableViewColumn $column)
		{
			$this->columns[] = $column;
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

		protected $columns = array();
		protected $data = array();
	}

	abstract class TableViewColumn {
		public function __construct($title, $column)
		{
			$this->title = $title;
			$this->column = $column;
		}

		abstract public function html(&$data);

		public function title()
		{
			return $this->title;
		}

		public function column()
		{
			return $this->column;
		}

		protected $title;
		protected $column;
	}

	class TextTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			return $data[$this->column];
		}
	}

	class TemplateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->vars === null) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $this->column, $matches, PREG_PATTERN_ORDER);
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

	class CmdsTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$id = $data[$this->column];
			$html =<<<EOD
<a href="{$this->title}_edit/$id">edit</a><br />
<a href="{$this->title}_delete/$id">delete</a>
EOD;
			return $html;
		}

		public function title()
		{
			return null;
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
