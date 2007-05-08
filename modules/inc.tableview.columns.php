<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

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
			if($t)
				$this->title = dgettext('swisdk', $t);
			else
				$this->title = '';
		}
		
		public function css_class()
		{
			if(!$this->css_class)
				$this->css_class = 's-'.str_replace('_', '-', $this->name());
			return $this->css_class;
		}

		public function set_tableview(&$tableview)
		{
			$this->tableview = $tableview;
		}

		protected $column;
		protected $title;
		protected $args;
		protected $tableview;
		protected $css_class = null;
	}

	/**
	 * TextTableViewColumn takes a third parameter: maximal string length
	 */
	class TextTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$str = strip_tags($data[$this->column]);
			if($ml = s_get($this->args, 0))
				return ellipsize($str, $ml);
			return $data[$this->column];
		}
	}

	class NumberTableViewColumn extends TableViewColumn {
		protected $fmt = null;

		public function html(&$data)
		{
			static $initialized = false;
			if(!$initialized) {
				if(!$this->fmt && isset($this->args[0]))
					$this->fmt = $this->args[0];
				$initialized = true;
			}

			if($this->fmt)
				return sprintf($this->fmt, $data[$this->column]);
			return $data[$this->column];
		}

		public function set_format($format)
		{
			$this->fmt = $format;
			return $this;
		}
	}

	class BoolTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');

			return '<img src="'.$prefix.'/icons/'
				.($data[$this->column]?'tick':'cross')
				.'.png" alt="'
				.($data[$this->column]?'true':'false')
				.'" />';
		}
	}

	class ChoiceTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$value = $data[$this->column];
			return s_get($this->args[0], $value, $value);
		}
	}

	/**
	 * Example:
	 *
	 * $tableview->append_column(new TemplateTableViewColumn(
	 * 	'item_title', 'Title',
	 * 	'<a href="/overview/{item_id}">{item_title}</a>'));
	 */
	class TemplateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->vars === null) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $this->args[0],
					$matches, PREG_PATTERN_ORDER);
				$this->vars = s_get($matches, 1);
				foreach($this->vars as $v)
					$this->patterns[] = '/\{' . $v . '\}/';
			}

			if(!count($this->vars))
				return $this->args[0];

			$vals = array();
			foreach($this->vars as $v)
				$vals[] = $data[$v];

			return preg_replace($this->patterns, $vals, $this->args[0]);
		}

		protected $vars = null;
		protected $patterns = null;
	}

	/**
	 * third parameter: strftime(3)-formatted string. If not specified,
	 * a default string is used.
	 */
	class DateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if(!$data[$this->column])
				return $this->never;

			if($this->fmt === null) {
				if(s_test($this->args, 0))
					$this->fmt = $this->args[0];
				else
					$this->fmt = '%d.%m.%Y : %H:%M';
			}
			return strftime($this->fmt, $data[$this->column]);
		}

		protected $fmt = null;
		protected $never = '(never)';
	}

	class TimeTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			return strftime('%H:%M', $data[$this->column]+82800);
		}
	}

	/**
	 * show a dttm range
	 */
	class DttmRangeTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			return dttmRange($data);
		}
	}

	/**
	 * pass a callback instead of a field name
	 *
	 * Example:
	 * $tableview->append_column(new CallbackTableViewColumn(
	 * 	'callback', 'Whatever'));
	 */
	class CallbackTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$method = $this->column;
			return call_user_func($method, $data);
		}
	}

	/**
	 * type hint for TableView
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
		protected $image_prefix = 'page_white_';
		protected $copy_enabled = true;

		protected $token = null;

		public function html(&$data)
		{
			if(!$this->token)
				$this->token = Swisdk::guard_token_f('guard');
			$id = $data[$this->column];
			$delete = dgettext('swisdk', 'Really delete?');
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			$html =<<<EOD
<a href="{$this->title}edit/$id" title="edit"><img src="$prefix/icons/{$this->image_prefix}edit.png" alt="edit" /></a>

EOD;
			if($this->copy_enabled)
				$html .=<<<EOD
<a href="{$this->title}copy/$id" title="copy"><img src="$prefix/icons/page_white_copy.png" alt="copy" /></a>

EOD;
			$html .=<<<EOD
<a href="{$this->title}delete/$id" title="delete" onclick="if(confirm('$delete')){this.href+='?guard={$this->token}';}else{this.parentNode.parentNode.onclick();return false;}">
	<img src="$prefix/icons/{$this->image_prefix}delete.png" alt="delete" />
</a>
EOD;
			return $html;
		}

		public function name()
		{
			return $this->column.'__cmd';
		}

		public function disable_copy($enabled=false)
		{
			$this->copy_enabled = $enabled;
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
			$this->initialized = true;

			$this->db_class = $this->args[0];

			if($t = s_get($this->args, 2)) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $t,
					$matches, PREG_PATTERN_ORDER);
				$this->vars = s_get($matches, 1);
				foreach($this->vars as $v)
					$this->patterns[] = '/\{' . $v . '\}/';

				$this->db_data = DBOContainer::find($this->db_class)->data();
			} else {
				$doc = DBOContainer::find($this->db_class);
				foreach($doc as $id => $obj)
					$this->db_data[$id] = $obj->title();
			}
		}

		public function html(&$data)
		{
			if(!$this->initialized)
				$this->init_column();
			if($this->vars!==null) {
				$vals = array();
				$record =& $this->db_data[$data[$this->column]];
				foreach($this->vars as $v)
					$vals[] = $record[$v];

				return preg_replace($this->patterns, $vals, $this->args[2]);
			} else {
				$val = $data[$this->column];
				return s_get($this->db_data, $val, $this->no_data);
			}
		}

		public function set_no_data_text($txt)
		{
			$this->no_data = $txt;
		}

		protected $initialized = false;

		protected $db_data = null;
		protected $db_class = null;

		protected $vars = null;
		protected $patterns = null;

		protected $no_data = '(none)';
	}

	class ManyDBTableViewColumn extends DBTableViewColumn {
		protected function init_column()
		{
			$this->initialized = true;
			$this->db_class = $this->args[0];

			if(isset($this->args[1])) {
				if($this->args[1] instanceof DBObject)
					$this->dbobj = $this->args[1];
				else
					$this->dbobj = DBObject::create($this->args[1]);
			} else
				$this->dbobj = DBObject::create(
					$this->tableview->dbobj()->dbobj()->_class());

			$relations = $this->dbobj->relations();
			$rel = $relations[$this->db_class];
			$clause = array($rel['field'].' IN {ids}' => array(
				'ids' => $this->tableview->dbobj()->ids()));

			if($t = s_get($this->args, 2)) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $t,
					$matches, PREG_PATTERN_ORDER);
				if(isset($matches[1]))
					$this->vars = $matches[1];
				foreach($this->vars as $v)
					$this->patterns[] = '/\{' . $v . '\}/';

				$doc = DBOContainer::find($this->db_class, $clause);
				foreach($doc as $id => $obj)
					$this->db_data[$obj->get($rel['field'])][] = $obj;
			} else {
				$doc = DBOContainer::find($this->db_class, $clause);
				foreach($doc as $id => $obj)
					$this->db_data[$obj->get($rel['field'])][] =
						$obj->title();
			}
		}

		public function html(&$data)
		{
			if(!$this->initialized)
				$this->init_column();
			$p = $this->dbobj->primary();

			if(!isset($data[$p]) || !isset($this->db_data[$data[$p]]))
				return $this->no_data;

			$id = $data[$p];
			$data =& $this->db_data[$id];
			$tokens = array();

			if($this->vars!==null) {
				foreach($data as &$record) {
					$vals = array();
					foreach($this->vars as $v)
						$vals[] = $record->pretty_value($v);

					$tokens[] = preg_replace($this->patterns, $vals, $this->args[2]);
				}
			} else
				$tokens =& $data;
			if($this->ellipsize)
				return ellipsize(implode(', ', $tokens), $this->ellipsize);
			return implode(', ', $tokens);
		}

		public function set_ellipsize($e)
		{
			$this->ellipsize = $e;
		}

		protected $dbobj = null;
		protected $ellipsize = false;
	}

	/**
	 * TableViewColumn for data from n-to-m or 3way relations
	 */
	class ManyToManyDBTableViewColumn extends DBTableViewColumn {
		protected function init_column()
		{
			parent::init_column();
			if(isset($this->args[1])) {
				if($this->args[1] instanceof DBObject)
					$this->dbobj = $this->args[1];
				else
					$this->dbobj = DBObject::create($this->args[1]);
			} else
				$this->dbobj = DBObject::create(
					$this->tableview->dbobj()->dbobj()->_class());
			$relations = $this->dbobj->relations();
			$rel = $relations[$this->db_class];
			$p = $rel['link_here'];
			$q = $rel['link_there'];
			$ids = $this->tableview->dbobj()->ids();
			$this->reldata = array();
			if(count($ids)) {
				$rdata = DBObject::db_get_array(sprintf(
					'SELECT %s,%s FROM %s WHERE %s IN (%s)',
					$p, $q, $rel['link_table'], $p, implode(',', $ids)));
				foreach($rdata as $row)
					$this->reldata[$row[$p]][] = $row[$q];
			}
		}

		public function html(&$data)
		{
			if(!$this->initialized)
				$this->init_column();
			$p = $this->dbobj->primary();

			if(!isset($data[$p]) || !isset($this->reldata[$data[$p]]))
				return $this->no_data;
			$vals = $this->reldata[$data[$p]];
			$tokens = array();
			foreach($vals as $v) {
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
			}
			if($this->ellipsize)
				return ellipsize(implode(', ', $tokens), $this->ellipsize);
			return implode(', ', $tokens);
		}

		public function set_ellipsize($e)
		{
			$this->ellipsize = $e;
		}

		protected $reldata = null;
		protected $dbobj = null;
		protected $ellipsize = false;
	}

	/**
	 * Displays a column consisting only of checkboxes. You can use that to execute
	 * actions on multiple rows at once.
	 */
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
			return $this->column.'__id';
		}

		public function title()
		{
			return '<input type="checkbox" onchange="tv_toggle(this)" />';
		}
	}

?>
