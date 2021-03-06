<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class DBOContainer implements Iterator,ArrayAccess {
		/**
		 * class name of contained object
		 */
		protected $class = 'DBObject';

		/**
		 * one instance of contained object
		 */
		protected $obj;

		/**
		 * DBObject array
		 */
		protected $data = array();

		/**
		 * if this variable is non-null, it is used to assign the keys for
		 * the DBObject array in init()
		 */
		protected $init_index = null;

		/**
		 * SQL builder variables. see add_clause(), add_join(), init() and friends
		 */
		protected $clause_sql = ' ';
		protected $order_columns = array();
		protected $limit = '';
		protected $joins = '';

		protected $fulltext = array('include' => array(), 'exclude' => array());
		protected $tags = array('include' => array(), 'exclude' => array());

		public function &dbobj() { return $this->obj; }
		public function _class() { return $this->class; }

		/**
		 * Use the code, luke!
		 */
		protected function __construct($obj = null)
		{
			if(is_null($obj)) {
				$this->obj = DBObject::create($this->class);
			} else {
				$this->obj = $obj;
				$this->class = $obj->_class();
			}
		}

		/**
		 * @return: a new DBOContainer which contains the DBObjects of the
		 * passed type
		 */
		public static function create($class)
		{
			if($class instanceof DBObject)
				return new DBOContainer($class);
			else if(class_exists($class) && is_subclass_of($class, 'DBOContainer'))
				return new $class;
			else
				return new DBOContainer(DBObject::create($class));
		}

		/**
		 * Example:
		 *
		 * DBOContainer::find('News', array('news_active!=0', 'news_start_dttm>', time()));
		 */
		public static function &find($class, $params=null)
		{
			$container = null;
			if(is_string($class))
				$container = new DBOContainer(DBObject::create($class));
			else
				$container = new DBOContainer($class);

			$container->set_traits();

			if(is_array($params))
				$container->add_clause_array($params);
			if($container->init()===false)
				return false;
			return $container;
		}

		public static function find_by_id($class, $ids)
		{
			$container = null;
			if(is_string($class))
				$container = new DBOContainer(DBObject::create($class));
			else
				$container = new DBOContainer($class);

			if(!is_array($ids) || !count($ids))
				return $container;
			$container->add_clause($container->dbobj()->primary().' IN {list}',
				array('list' => $ids));
			if($container->init()===false)
				return false;
			return $container;
		}

		protected function set_traits()
		{
			$traits = $this->obj->traits();
			foreach($traits as $key => &$value) {
				if($key{0}==':')
					$this->add_clause($key, $value);
			}
		}

		/**
		 * Build SQL query and fill up DBObject array
		 */
		public function init()
		{
			$this->data = array();
			$args = func_get_args();
			array_unshift($args, $this->joins);
			$sql = call_user_func_array(array(&$this->obj, '_select_sql'), $args)
				. $this->clause_sql . $this->_fulltext_clause()
				. $this->_tag_clause()
				. (count($this->order_columns)
					?' ORDER BY '.implode(',', $this->order_columns)
					:'')
				. $this->limit;
			return $this->init_by_sql($sql);
		}

		public function init_by_sql($sql)
		{
			$res = DBObject::db_query($sql, $this->obj->db_connection());
			if($res===false)
				return false;

			if($this->init_index!==null) {
				while($row = $res->fetch_assoc()) {
					$obj = clone $this->obj;
					$obj->set_data($row);
					$this->data[$obj->get($this->init_index)] = $obj;
				}
			} else {
				while($row = $res->fetch_assoc()) {
					$obj = clone $this->obj;
					$obj->set_data($row);
					$this->data[$obj->id()] = $obj;
				}
			}

			return true;
		}

		public function count()
		{
			return count($this->data);
		}

		public function total_count()
		{
			$sql = call_user_func_array(array(&$this->obj, '_select_sql'),
					array($this->joins))
				. $this->clause_sql . $this->_fulltext_clause()
				. $this->_tag_clause()
				. (count($this->order_columns)
					?' ORDER BY '.implode(',', $this->order_columns)
					:'');
			$sql = str_replace('SELECT *', 'SELECT COUNT(*) AS count', $sql);
			$res = DBObject::db_get_row($sql, $this->obj->db_connection());
			if($res===false)
				return false;
			return $res['count'];
		}

		/**
		 * here you can pass SQL fragments. The data will be automatically escaped
		 * so you can pass anything you like.
		 *
		 * $doc->add_clause('pen_color=', $_POST['color']);
		 * $doc->add_clause('pen_length>', $_POST['min-length']);
		 *
		 * $doc->add_clause('(pen_xy_id IN {blah} OR pen_yz={bleh})',
		 * 	array('blah' => array(1,2,3), 'bleh' => 'string'));
		 */
		public function add_clause($clause, $data=null, $binding = 'AND')
		{
			if($clause{0}==':') {
                if (!is_array($data))
                    $data = array($data);
				switch($clause) {
					case ':order':
						call_user_func_array(array(
							$this, 'add_order_column'),
							$data);
						break;
					case ':limit':
						call_user_func_array(array(
							$this, 'set_limit'),
							$data);
						break;
					case ':index':
						call_user_func_array(array(
							$this, 'set_index'),
							$data);
						break;
					case ':join':
						call_user_func_array(array(
							$this, 'add_join'),
							$data);
						break;
					case ':fulltext':
						call_user_func_array(array(
							$this, 'set_fulltext'),
							$data);
						break;
					case ':set_realm_clause':
						PermissionManager::set_realm_clause($this);
						break;
					case ':limit_by_realm':
						array_unshift($data, $this);
						call_user_func_array(array(
							'PermissionManager', 'limit_by_realm'),
							$data);
						break;
				}
				return;
			}

			$relations = $this->dbobj()->relations();
			if(isset($relations[$clause])) {
				$rel = $relations[$clause];
				$this->add_join($clause);
				$this->clause_sql .= $binding.' '.$rel['foreign_primary'].'='
					.DBObject::db_escape($data, $this->obj->db_connection());
				return;
			}

			$binding = ' '.$binding.' ';
			if(is_null($data)) {
				$this->clause_sql .= $binding.$clause;
			} else if(is_array($data)) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $clause, $matches,
					PREG_PATTERN_ORDER);
				if(isset($matches[1])) {
					array_walk_recursive($data,
						array('DBObject', 'db_escape_ref'));
					$p = array();
					$q = array();
					foreach($matches[1] as $v) {
						$p[] = '/\{' . $v . '\}/';
						if(is_array($data[$v])) {
							$q[] = '(\''.implode('\',\'', $data[$v]).'\')';
						} else {
							$q[] = '\''.$data[$v].'\'';
						}
					}
					$this->clause_sql .= $binding.preg_replace($p, $q, $clause);
				}
			} else {
				$this->clause_sql .= $binding.$clause
					.DBObject::db_escape($data, $this->obj->db_connection());
			}
		}

		public function add_clause_array($params)
		{
			if(!is_array($params))
				return;
			foreach($params as $k => $v)
				$this->add_clause($k, $v);
		}

		/**
		 * $doc->add_order_column('news_start_dttm', 'DESC');
		 */
		public function add_order_column($column, $dir=null, $prepend=false)
		{
			if(strpos($column, ',')!==false) {
				$cols = s_array($column);
				foreach($cols as $col)
					$this->add_order_column($col);
				return;
			}

			if(strtoupper($column)=='RAND') {
				switch($this->obj->db_driver()) {
					case 'mysql':
						$this->order_columns[] = ' RAND()';
						break;
				}
				return;
			}

			if(strpos($column, ':')!==false)
				list($column, $dir) = explode(':', $column);

			if($column && !preg_match('/[^A-Za-z0-9_\.]/', $column)) {
				$col = $column.($dir=='DESC'?' DESC':' ASC');
				if($prepend)
					array_unshift($this->order_columns, $col);
				else
					$this->order_columns[] = $col;
			}
		}

		public function set_order_column($column, $dir=null)
		{
			$this->order_columns = array();
			$this->add_order_column($column, $dir);
		}

		/**
		 * $doc->set_limit(10);
		 */
		public function set_limit($p1, $p2=null)
		{
			$p1 = intval($p1);
			if($p2!==null)
				$p2 = intval($p2);
			if((!$p1&&$p2===null)||(!$p1&&!$p2))
				return;
			if($p1<0)
				$p1=0;
			if(is_null($p2))
				$this->limit = ' LIMIT ' . $p1;
			else
				$this->limit = ' LIMIT ' . $p1 . ',' . $p2;
		}

		/**
		 * $doc->set_index('language_id');
		 */
		public function set_index($index)
		{
			$this->init_index = $index;
		}

		/**
		 * $doc->add_join('tbl_users', 'pen_user_id=user_id');
		 *
		 * or
		 *
		 * $doc->add_join('User');
		 */
		public function add_join($table, $clause=null, $type='LEFT')
		{
			if($clause===null) {
				$relations = $this->obj->relations();
				if(isset($relations[$table]) && $rel = $relations[$table]) {
					switch($rel['type']) {
						case DB_REL_SINGLE:
						case DB_REL_MANY:
							$this->joins .= ' '.$type.' JOIN '.$rel['foreign_table']
								.' ON '.$rel['foreign_condition'];
							break;
						case DB_REL_N_TO_M:
						case DB_REL_TAGS:
							$this->joins .= ' '.$type.' JOIN '.$rel['link_table']
								.' ON '.$rel['link_condition']
								.' LEFT JOIN '.$rel['foreign_table']
								.' ON '.$rel['foreign_condition'];
							break;
						case DB_REL_3WAY:
							$this->joins .= ' '.$type.' JOIN '.$rel['link_table']
								.' ON '.$rel['link_condition']
								.' LEFT JOIN '.$rel['foreign_table']
								.' ON '.$rel['foreign_condition']
								.' LEFT JOIN '.$rel['choices_table']
								.' ON '.$rel['choices_condition'];
							break;
					}
				} else {
					$dbo = DBObject::create($table);
					$p = $dbo->primary();
					$this->joins .= ' '.$type.' JOIN '.$dbo->table().' ON '
						.$this->dbobj()->name($p).'='.$p;
				}
			} else
				$this->joins .= ' '.$type.' JOIN ' . $table . ' ON ' . $clause;
		}

		/**
		 * Set fulltext search clause
		 */
		public function set_fulltext($clause=null)
		{
			$tokens = split("[ \t]+", $clause);

			foreach($tokens as $t) {
				$matches = array();
				preg_match('/^(?P<inc>[+-])?(?P<tag>tag:)?(?P<q>.*)$/', $t, $matches);

				if($matches['tag']) {
					if($matches['inc']=='-')
						$this->tags['exclude'][] = DBObject::db_escape($matches['q']);
					else
						$this->tags['include'][] = DBObject::db_escape($matches['q']);
				} else if($matches['inc']=='-')
					$this->fulltext['exclude'][] = $this->_ft_escape($matches['q']);
				else
					$this->fulltext['include'][] = $this->_ft_escape($matches['q']);
			}
		}

		protected function _ft_escape($str)
		{
			return preg_replace('/^\'(.*)\'$/', '\1',
				DBObject::db_escape($str, $this->obj->db_connection()));
		}

		protected function _fulltext_clause()
		{
			if((count($this->fulltext['include']) || count($this->fulltext['exclude']))
					&& count($fields = $this->obj->_fulltext_fields())) {

				$fulltext_sql = array();

				foreach($this->fulltext['include'] as $i)
					$fulltext_sql[] = implode(' LIKE \'%'.$i.'%\' OR ', $fields)
						.' LIKE \'%'.$i.'%\'';

				foreach($this->fulltext['exclude'] as $e)
					$fulltext_sql[] = implode(' NOT LIKE \'%'.$e.'%\' AND ', $fields)
						.' NOT LIKE \'%'.$e.'%\'';

				if(count($fulltext_sql))
					return ' AND (('.implode(') AND (', $fulltext_sql).'))';
			}
			return ' ';
		}

		protected function _tag_clause()
		{
			if(!count($this->tags['include']) && !count($this->tags['exclude']))
				return '';
			$relations = $this->obj->relations();
			if(!isset($relations['Tag']))
				return '';

			$rel = $relations['Tag'];
			$p = $this->obj->primary();

			$sql = '';

			if(count($this->tags['include']))
				$sql .= ' AND '.$p.' IN ('
					.'SELECT DISTINCT '.$rel['link_here'].' FROM '.$rel['link_table']
					.' LEFT JOIN tbl_tag ON '.$rel['foreign_condition']
					.' WHERE tag_title IN ('.implode(',', $this->tags['include']).')'
				.')';
			if(count($this->tags['exclude']))
				$sql .= ' AND '.$p.' NOT IN ('
					.'SELECT DISTINCT '.$rel['link_here'].' FROM '.$rel['link_table']
					.' LEFT JOIN tbl_tag ON '.$rel['foreign_condition']
					.' WHERE tag_title IN ('.implode(',', $this->tags['exclude']).')'
				.')';

			return $sql;
		}

		/**
		 * collect and return data from DBObjects
		 */
		public function &data()
		{
			$array = array();
			foreach($this->data as $key => &$obj) {
				$array[$key] = $obj->data();
			}
			return $array;
		}

		/**
		 * return an array of ids
		 */
		public function ids()
		{
			return array_keys($this->data);
		}

		public function collect($key, $value)
		{
			$array = array();
			foreach($this->data as &$dbobj)
				$array[$dbobj->$key] = $dbobj->$value;
			return $array;
		}

		/**
		 * might be expensive because get() does more than __get()
		 */
		public function collect_full($key, $value)
		{
			$array = array();
			foreach($this->data as &$dbobj)
				$array[$dbobj->get($key)] = $dbobj->get($value);
			return $array;
		}

		public function collect_array()
		{
			$args = func_get_args();
			$ret = array();

			if(!count($this->data))
				return array();

			foreach($this->data as &$dbo)
				foreach($args as $arg)
					$ret[$arg][] = $dbo->$arg;

			foreach($ret as &$r)
				$r = array_unique($r);

			if(count($args)==1)
				return $ret[$args[0]];
			return $ret;
		}

		/**
		 * can be used to call delete, store etc. on the contained
		 * DBObjects
		 */
		public function __call($method, $args)
		{
			if(in_array($method, array('update','insert','store','delete', 'dirty'))) {
				foreach($this->data as &$dbobj)
					if(call_user_func_array(array(&$dbobj,$method), $args)===false)
						return false;
			} else {
				foreach($this->data as &$obj)
					call_user_func_array(array(&$obj,$method), $args);
			}
			return true;
		}

		public function add($param)
		{
			if($param instanceof DBObject) {
				if($this->init_index!==null)
					$this->data[$param->get($this->init_index)] = $param;
				else if(isset($this->data[$param->id()]))
					$this->data[$param->id()] = $param;
				else
					$this->data[] = $param;
			} else {
				$obj = clone $this->obj;
				$obj->set_data($param);
				if($this->init_index!==null)
					$this->data[$obj->get($this->init_index)] = $obj;
				else
					$this->data[$obj->id()] = $obj;
			}
		}

		public function dbobj_clone()
		{
			return clone $this->obj;
		}

		/**
		 * get or set a value on all DBObjects
		 */

		public function __get($var)
		{
			$array = array();
			foreach($this->data as &$obj)
				$array[$obj->id()] = $obj->$var;
			return $array;
		}

		public function __set($var, $value)
		{
			foreach($this->data as &$obj)
				$obj->$var = $value;
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */

		public function rewind() { return reset($this->data); }
		public function current() { return current($this->data); }
		public function key() { return key($this->data); }
		public function next() { return next($this->data); }
		public function valid() { return $this->current() !== false; }

		/**
		 * ArrayAccess implementation (see PHP SPL)
		 */

		public function offsetExists($offset) { return isset($this->data[$offset]); }
		public function offsetGet($offset) { return $this->data[$offset]; }
		public function offsetSet($offset, $value)
		{
			if($offset===null)
				$this->data[] = $value;
			else
				$this->data[$offset] = $value;
		}
		public function offsetUnset($offset) { unset($this->data[$offset]); }
	}

?>
