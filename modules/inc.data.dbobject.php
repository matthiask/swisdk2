<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * DBObject
	 *
	 * All database access goes through this class. It provides a lightweight
	 * yet extensible, object oriented and secure data layer.
	 *
	 * Its main functionality does not depend on other SWISDK packages. For now,
	 * it only uses SwisdkError::handle for error conditions.
	 */

	class DBObject implements Iterator {

		/**
		 * The PHP __CLASS__ macro is near to useless. It would be worth something
		 * if it would change in derived classes.
		 * You need to assign the class name by hand if you derive from DBObject
		 *
		 * This is boilerplate code.
		 */
		protected $class = __CLASS__;

		/**
		 * If you are happy with my table naming scheme, you don't need to change
		 * any of the following three variables.
		 *
		 * The naming rules are:
		 *
		 * Class:       CustomerContact
		 * Table:       tbl_customer_contact
		 * Prefix:      customer_contact_
		 * Primary ID:  customer_contact_id
		 *
		 * see also _setup_dbvars
		 */
		protected $table = null;
		protected $prefix = null;
		protected $primary = null;

		/**
		 * Bookkeeping variables
		 */

		/**
		 * Does the data in this DBObject differ from the database?
		 */
		protected $dirty = false;

		/**
		 * various helpers and variable accessors (these variables are not mutable)
		 */
		public function table()		{ return $this->table; }
		public function primary()	{ return $this->primary; }
		public function name($tok)	{ return $this->prefix . $tok; }
		public function unname($tok)
		{
			return preg_replace('/^'.$this->prefix.'/', '', $tok);
		}

		public function pretty($field)
		{
			if($field == $this->primary)
				return 'ID';
			return ucwords(str_replace('_', ' ', preg_replace(
				'/('.$this->prefix.')?(.*?)(_id|_dttm)?/',
				'\2',
				$field
			)));
		}

		public function shortname($tok)	{ return str_replace($this->prefix,'',$tok); }
		public function _class()	{ return $this->class; }
		public function _prefix()	{ return $this->prefix; }
		public function relations()
		{
			if(isset(DBObject::$relations[$this->class]))
				return DBObject::$relations[$this->class];
			return array();
		}

		public function dirty()		{ return $this->dirty; }

		/**
		 * main DB handle (holds the mysqli instance in the current version
		 * of DBObject)
		 */
		protected static $dbhandle = null;

		/**
		 * This would be the only important variable: All others are simply here to
		 * serve the data!
		 */
		protected $data = array();

		/**
		 * automatically pass on all errors to SwisdkError::handle
		 */
		protected static $handle_error = true;

		/**
		 * this variable holds the error object if
		 * handle_error was false and an error occurred
		 */
		protected static $error_obj=null;

		/**
		 * DB Connection ID (only used if you want multiple DB connections)
		 */
		protected $db_connection_id = DB_CONNECTION_DEFAULT;
		
		public function db_connection()
		{
			return $this->db_connection_id;
		}

		/**
		 * switch DB connection
		 *
		 * @param $name: config section for DB connection parameters
		 */
		public function set_db_connection($name)
		{
			$this->db_connection_id = $name;
		}

		/**
		 * automatically handle DB errors?
		 */
		public static function handle_error($handle = true)
		{
			DBObject::$handle_error = $handle;
		}

		public static function error()
		{
			return DBObject::$error_obj;
		}

		/**
		 * @param $setup_dbvars: Should the DB vars be determined or should we wait
		 * until later (See DBObject::create)
		 */
		protected function __construct($setup_dbvars = true)
		{
			if($setup_dbvars)
				$this->_setup_dbvars();
		}

		/**
		 * private helper function that implements my DB naming scheme
		 */
		protected function _setup_dbvars()
		{
			$tok = strtolower(preg_replace(
				'/([A-Z])/', '_\1', $this->class));
			if(is_null($this->table))
				$this->table = 'tbl' . $tok;
			if(is_null($this->prefix))
				$this->prefix = substr($tok,1) . '_';
			if(is_null($this->primary))
				$this->primary = $this->name('id');
		}

		/**
		 * factory function
		 *
		 * @return: DBObject of type $class
		 * 
		 * Use this function or DBObject::find
		 *
		 * If you have no special needs (DB not implementing my naming scheme,
		 * data validation and/or manipulation inside DBObject) you don't even
		 * need to explicitly derive your own class from DBObject, instead you
		 * can simply pass the class name to this function.
		 */
		public static function create($class)
		{
			if(class_exists($class))
				return new $class();

			$obj = new DBObject(false);
			$obj->class = $class;
			$obj->_setup_dbvars();
			return $obj;
		}

		public static function create_with_data($class, $data)
		{
			$obj = DBObject::create($class);

			foreach($data as $k => $v)
				$obj->$k = $v;
			return $obj;
		}

		/**
		 * factory function
		 *
		 * @return: DBObject
		 *
		 * This function returns a DBObject of type $class if the entry
		 * $id exists in the database.
		 */
		public static function find($class, $params)
		{
			$obj = DBObject::create($class);
			if($obj->_find($params))
				return $obj;
			return false;
		}

		protected function _find($params)
		{
			if(is_array($params)) {
				$where = array(' WHERE 1 ');
				$p = $this->prefix;
				$regex = '/^([:\(]|'.$p.')/';

				foreach($params as $k => $v)
					if(preg_match($regex, $k))
						$where[] = $k.DBObject::db_escape($v).' ';
					else
						$where[] = $p.$k.DBObject::db_escape($v).' ';
				$this->data = DBObject::db_get_row('SELECT * FROM '
					.$this->table.implode(' AND ', $where));
				if($this->data && count($this->data))
					return true;
			} else {
				$this->id = $params;
				if($this->refresh())
					return true;
			} 
		}

		/**
		 * refresh (or load) data from database using the stored primary id
		 */
		public function refresh()
		{
			if($this->id())
				$this->data = DBObject::db_get_row('SELECT * FROM '
					.$this->table.' WHERE '.$this->primary.'='.$this->id(),
					$this->db_connection_id);
			$this->dirty = false;
			return ($this->data && count($this->data));
		}

		/**
		 * Store the DBObject. Automatically determines if it should create
		 * a new record or if it should update an existing one.
		 */
		public function store()
		{
			if(!$this->dirty)
				return true;
			if(isset($this->data[$this->primary]) && $this->data[$this->primary])
				return $this->update();
			else
				return $this->insert();
		}

		/**
		 * Explicitly request an update. This function has undefined behaviour
		 * if the primary key does not exist.
		 */
		public function update()
		{
			$this->auto_update_fields();
			DBObject::db_start_transaction($this->db_connection_id);
			$res = DBObject::db_query('UPDATE ' . $this->table . ' SET '
				. $this->_vals_sql() . ' WHERE '
				. $this->primary . '=' . $this->id(),
				$this->db_connection_id);
			if($res===false || !$this->_update_relations()) {
				DBObject::db_rollback($this->db_connection_id);
				return false;
			}
			DBObject::db_commit($this->db_connection_id);
			$this->dirty = false;
			return true;
		}

		/**
		 * Explicitly request an insert be done. This should always succeed.
		 */
		public function insert()
		{
			$this->auto_update_fields(true);
			DBObject::db_start_transaction($this->db_connection_id);
			$this->unset_primary();
			$res = DBObject::db_query('INSERT INTO ' . $this->table
				. ' SET ' . $this->_vals_sql(),
				$this->db_connection_id);
			if($res===false) {
				DBObject::db_rollback($this->db_connection_id);
				return false;
			}
			$this->data[$this->primary] = DBObject::db_insert_id(
				$this->db_connection_id);
			if(!$this->_update_relations()) {
				DBObject::db_rollback($this->db_connection_id);
				return false;
			}
			DBObject::db_commit($this->db_connection_id);
			$this->dirty = false;
			return true;
		}

		/**
		 * this helper should always be executed inside a transaction (that
		 * is actually the case when you use update() or insert(), the only
		 * place where this helper is used right now)
		 *
		 * XXX oops, a transaction does not really help here. I should lock
		 * the table while the user is editing data
		 */
		protected function _update_relations()
		{
			if(!isset(DBObject::$relations[$this->class]))
				return true;
			foreach(DBObject::$relations[$this->class] as &$rel) {
				if($rel['type']==DB_REL_MANYTOMANY) {
					$field = $rel['field'];
					if(!$field||!isset($this->data[$field]))
						$field = $rel['class'];
					if(!isset($this->data[$field]))
						continue;
					$res = DBObject::db_query('DELETE FROM '.$rel['table']
						.' WHERE '.$this->primary.'='.$this->id(),
						$this->db_connection_id);
					if($res===false)
						return false;
					if(count($this->data[$field])) {
						$sql = 'INSERT INTO '.$rel['table']
							.' ('.$this->primary.','
							.$rel['foreign'].') VALUES ('
							.$this->id().','
							.implode('),('.$this->id().',',
							$this->data[$field]).')';
						if(DBObject::db_query($sql,
							$this->db_connection_id)===false)
							return false;
					}
				}
			}

			return true;
		}

		/**
		 * Private helper for update() and insert(). This function takes care
		 * of SQL injections by properly escaping every string that hits the
		 * database.
		 */
		protected function _vals_sql()
		{
			$dbh = DBObject::db($this->db_connection_id);
			$vals = array();
			$fields = array_keys($this->field_list());
			foreach($fields as $field) {
				if(isset($this->data[$field]))
					$vals[] = $field.'=\''
						.$dbh->escape_string($this->data[$field])
						.'\'';
			}
			return implode(',', $vals);
		}

		/**
		 * automatically fill up update_dttm, creation_dttm, author_id
		 * et al.
		 */
		protected function auto_update_fields($new = false)
		{
			$fields = array_keys($this->field_list());
			$dttm_regex = '/_('.($new?'creation|':'').'update)_dttm$/';
			$author_regex = '/_author_id$/';
			foreach($fields as $field) {
				if(preg_match($dttm_regex, $field))
					$this->set($field, time());
				else if($new && preg_match($author_regex, $field)
						&&!$this->get($field))
					$this->set($field, SessionHandler::user()->id());
			}
		}

		/**
		 * Delete the current object from the database.
		 */
		public function delete()
		{
			if(!$this->id())
				return true;
			$ret = DBObject::db_query('DELETE FROM ' . $this->table
				. ' WHERE ' . $this->primary . '=' . $this->id());
			$this->unset_primary();
			return $ret;
		}

		/**
		 * Static relations table
		 */
		private static $relations = array();

		/**
		 * relation definition functions
		 *
		 * Inside this function happens some black magic juggling of
		 * values to provide the following syntax to the user:
		 *
		 * Example:
		 * 
		 * An event has both an author and a contact person. They are both
		 * stored in tbl_user. We cannot use event_user_id because we have
		 * two referred users for every record.
		 *
		 * DBObject::belongs_to('Event', 'User', 'event_author_id');
		 * DBObject::belongs_to('Event', 'User', 'event_contact_id');
		 *
		 * Now you may use:
		 *
		 * $event = DBObject::find('Event', 42);
		 * $author = $event->related('event_author_id');
		 * $author = $event->related('event_contact_id');
		 * 
		 * Note! See how you are passing the field name instead of a DBObject
		 * class name now.
		 *
		 * If you want to get all events that some user authored you have
		 * to do it differently:
		 *
		 * $events = DBOContainer::create('Event');
		 * $events->add_clause('event_author_id=', 13);
		 * $events->init();
		 */
		public static function belongs_to($c1, $c2, $options = null)
		{
			$o1 = DBObject::create($c1);
			$o2 = DBObject::create($c2);
			$field = $options;
			$class = $c2;
			if(!$field) {
				$field = $o2->name('id');
				// avoid names such as item_item_priority_id
				if(strpos($field, $o1->prefix)!==0)
					$field = $o1->name($field);
			}
			if($options)
				$c2 = $field;

			DBObject::$relations[$c1][$c2] =
				array('type' => DB_REL_SINGLE, 'field' => $field,
					'class' => $class, 'table' => $o2->table(),
					'foreign_key' => $o2->primary());
			DBObject::$relations[$c1][$field] = DBObject::$relations[$c1][$c2];
			// do not set reverse mapping if user passed an explicit field
			// specification
			if($options)
				return;
			DBObject::$relations[$c2][$c1] =
				array('type' => DB_REL_MANY, 'field' => $field,
					'class' => $c1, 'table' => $o1->table(),
					'foreign_key' => $o1->primary());
			DBObject::$relations[$c2][$field] = DBObject::$relations[$c2][$c1];
		}

		public static function has_many($c1, $c2, $options = null)
		{
			DBObject::belongs_to($c2, $c1, $options);
		}

		public static function has_a($c1, $c2, $options = null)
		{
			DBObject::belongs_to($c1, $c2, $options);
		}

		public static function n_to_m($c1, $c2, $options = null)
		{
			$o1 = DBObject::create($c1);
			$o2 = DBObject::create($c2);

			$rel1 = $c1;
			$rel2 = $c2;

			if($options!==null) {
				$rel1 = $rel2 = $options;
			}

			$table = 'tbl_'.$o1->name('to_'.$o2->name(''));
			$table = substr($table, 0, strlen($table)-1);

			DBObject::$relations[$c1][$rel2] = array(
				'type' => DB_REL_MANYTOMANY, 'table' => $table,
				'join' => $o2->table().'.'.$o2->primary().'='
					.$table.'.'.$o2->primary(),
				'field' => $options, 'class' => $c2, 'foreign' => $o2->primary());
			DBObject::$relations[$c2][$rel1] = array(
				'type' => DB_REL_MANYTOMANY, 'table' => $table,
				'join' => $o1->table().'.'.$o1->primary().'='
					.$table.'.'.$o1->primary(),
				'field' => $options, 'class' => $c1, 'foreign' => $o1->primary());
		}

		/**
		 * get related DBObject or DBOContainer (depending on relation type)
		 *
		 * @param class: class of related object OR name given to the relation (n-to-m)
		 * @param params: additional params for related_many and related_many_to_many
		 * 		Is currently NOT used for DB_REL_SINGLE  (TODO: any sane thing I
		 * 		could do with it?)
		 */
		public function related($class, $params=null)
		{
			$rel =& DBObject::$relations[$this->class][$class];
			switch($rel['type']) {
				case DB_REL_SINGLE:
					// FIXME this is seriously broken... gah
					// see also DBObject::get() (around line 1000)
					if(isset($this->data[$rel['field']]))
						return DBObject::find($rel['class'],
							$this->data[$rel['field']]);
					else
						return DBOContainer::create($rel['class']);
				case DB_REL_MANY:
					return $this->related_many($rel, $params);
				case DB_REL_MANYTOMANY:
					return $this->related_many_to_many($rel, $params);
			}
		}

		protected function related_many(&$rel, $params=null)
		{
			$container = DBOContainer::create($rel['class']);
			if(!$this->id())
				return $container;
			$container->add_clause($rel['field'].'=',
				$this->id());
			if(is_array($params))
				$container->add_clause_array($params);
			$container->init();
			return $container;
		}

		protected function related_many_to_many(&$rel, $params=null)
		{
			$container = DBOContainer::create($rel['class']);
			if(!$this->id())
				return $container;
			$container->add_join($rel['table'], $rel['join']);
			$container->add_clause($rel['table'].'.'.$this->primary().'=',
				$this->id());
			if(is_array($params))
				$container->add_clause_array($params);
			$container->init();
			return $container;
		}

		public function all_related()
		{
			$data = array();
			$rels =& DBObject::$relations[$this->class];
			foreach($rels as $class => &$rel) {
				switch($rel['type']) {
					case DB_REL_SINGLE:
						$data = array_merge($data,
							$this->related($class)->data());
						break;
					case DB_REL_MANY:
						$data[$class] = $this->related_many($rel)->data();
						break;
					case DB_REL_MANYTOMANY:
						$data[$class] =
							$this->related_many_to_many($rel)->data();
						break;
				}
			}
			return $data;
		}

		public function all_data()
		{
			return array_merge($this->data(), $this->all_related());
		}

		/**
		 * functions to bind objects to each other without setting relations data
		 */

		/**
		 * $content = DBObject::create('NewsContent');
		 * $owner = DBObject::create('News');
		 * // ...
		 * $owner->store();
		 * $content->set_owner($owner);
		 * $content->store();
		 */
		public function set_owner(DBObject $obj)
		{
			$this->{$obj->primary()} = $obj->id();
		}

		/**
		 * $content = DBOConteiner::create('NewsContent');
		 * $owner = DBObject::create('News');
		 * // ...
		 * $content->add(obj1);
		 * $content->add(obj2);
		 * $content->add(obj3);
		 * // ...
		 * $owner->store();
		 * $owner->set_owned($content);
		 * $content->store();
		 *
		 * @param obj: DBObject or DBOContainer
		 */
		public function set_owned($obj)
		{
			$obj->{$this->primary()} = $this->id();
		}

		/**
		 * @return: the DB handle
		 */
		protected static function &db($connection_id = DB_CONNECTION_DEFAULT)
		{
			if(!isset(DBObject::$dbhandle[$connection_id])) {
				DBObject::$dbhandle[$connection_id] = new mysqli(
					Swisdk::config_value($connection_id.'.host'),
					Swisdk::config_value($connection_id.'.username'),
					Swisdk::config_value($connection_id.'.password'),
					Swisdk::config_value($connection_id.'.database')
				);
				if(mysqli_connect_errno())
					SwisdkError::handle(new DBError('Connect failed: '
						.mysqli_connect_error()));
			}

			return DBObject::$dbhandle[$connection_id];
		}

		/**
		 * Use the following functions if you need raw DB access or if the DBObject
		 * interface would be to cumbersome to do what you need to do.
		 */

		/**
		 * Execute a simple SQL statement and return the result. You should not rely
		 * on the format of the return value as it is implementation specific and might
		 * change when another DB access layer (PDO?) is used inside DBObject
		 */
		public static function db_query($sql, $connection_id = DB_CONNECTION_DEFAULT)
		{
			$dbh = DBObject::db($connection_id);
			$result = $dbh->query($sql);
			DBObject::$error_obj = null;
			if($dbh->errno) {
				$error = new DBError("Database error: ".$dbh->error, $sql);
				if(DBObject::$handle_error)
					SwisdkError::handle($error);
				else
					DBObject::$error_obj = $error;
				return false;
			}
			return $result;
		}

		/**
		 * Return the result of the passed SQL query as an array of associative
		 * values.
		 */
		public static function db_get_row($sql, $connection_id = DB_CONNECTION_DEFAULT)
		{
			$res = DBObject::db_query($sql, $connection_id);
			if($res===false)
				return $res;
			return $res->fetch_assoc();
		}
		
		/**
		 * Return multiple rows as a nested array.
		 *
		 * If you pass a string as second argument, this functions tries to use it
		 * as the key of the returned array.
		 * You may also pass an array with two elements, they will be used to return
		 * key-value pairs.
		 *
		 * Example usage:
		 *
		 * $titles = DBObject::db_get_array('SELECT * FROM table');
		 * $titles = DBObject::db_get_array('SELECT * FROM table', 'id');
		 * $titles = DBObject::db_get_array('SELECT id,title FROM table',
		 * 	array('id','title'));
		 */
		public static function db_get_array($sql, $result_key=null,
			$connection_id = DB_CONNECTION_DEFAULT)
		{
			$res = DBObject::db_query($sql, $connection_id);
			if($res===false)
				return $res;
			$array = array();
			if($result_key) {
				if(is_array($result_key) && (($key = $result_key[0])
						&& ($val = $result_key[1]))) {
					while($row = $res->fetch_assoc())
						$array[$row[$key]] = $row[$val];
				} else {
					while($row = $res->fetch_assoc())
						$array[$row[$result_key]] = $row;
				}
			} else {
				while($row = $res->fetch_assoc())
					$array[] = $row;
			}
			return $array;
		}

		/**
		 * Use this function if you need to SQL-conformly escape a string.
		 *
		 * Note that the shell, the SQL server and possibly other software have
		 * different requirements for escaping parameters. (Not so different,
		 * though. Perl does only now two escape functions and they work for
		 * everything...)
		 */
		public static function db_escape($str, $quote = true,
			$connection_id = DB_CONNECTION_DEFAULT)
		{
			if($quote!==false)
				return '\''.DBObject::db($connection_id)
					->escape_string($str).'\'';
			return DBObject::db($connection_id)->escape_string($str);
		}

		/**
		 * this function is used internally by DBOContainer::add_cl
		 */
		public static function db_escape_ref(&$str, $quote = true,
			$connection_id = DB_CONNECTION_DEFAULT)
		{
			$str = DBObject::db($connection_id)->escape_string($str);
			if($quote!==false)
				$str = '\''.$str.'\'';
		}

		/**
		*	Wraps the mysqli_insert_id of mysqli. 
		*   "Returns the auto generated id used in the last query"
		*	@see http://www.php.net/manual-lookup.php?pattern=mysqli_insert_id
		*/
		public static function db_insert_id($connection_id = DB_CONNECTION_DEFAULT) 
		{
			return DBObject::db($connection_id)->insert_id;
		}
		
		/**
		 * Wrap DB transaction functions
		 */

		protected static $in_transaction = array(DB_CONNECTION_DEFAULT => 0);

		public static function db_start_transaction($connection_id = DB_CONNECTION_DEFAULT)
		{
			if(DBObject::$in_transaction[$connection_id]==0)
				DBObject::db($connection_id)->autocommit(false);
			DBObject::$in_transaction[$connection_id]++;
		}

		public static function db_commit($connection_id = DB_CONNECTION_DEFAULT)
		{
			DBObject::$in_transaction[$connection_id]--;
			if(DBObject::$in_transaction[$connection_id]<=0) {
				$dbh = DBObject::db($connection_id);
				$dbh->commit();
				$dbh->autocommit(true);
				DBObject::$in_transaction[$connection_id] = 0;
			}
		}

		public static function db_rollback($connection_id = DB_CONNECTION_DEFAULT)
		{
			DBObject::$in_transaction[$connection_id]--;
			if(DBObject::$in_transaction[$connection_id]<=0) {
				$dbh = DBObject::db($connection_id);
				$dbh->rollback();
				$dbh->autocommit(true);
				DBObject::$in_transaction[$connection_id] = 0;
			}
		}

		/**
		 * @return: the value of the primary key
		 */
		public function id()
		{
			if(isset($this->data[$this->primary]))
				return intval($this->data[$this->primary]);
			return null;
		}

		/**
		 * @return: Value used in selectors. Default value is the title
		 */
		public function title()
		{
			return $this->title;
		}

		public function shorttitle()
		{
			return $this->title;
		}

		/**
		 * @return: DBObject data as an associative array
		 */
		public function data()
		{
			return $this->data;
		}

		/**
		 * Set all data in the DBObject at once. Does a merge
		 * with the previous values.
		 */
		public function set_data($data)
		{
			$this->dirty = true;
			$this->data = array_merge($this->data, $data);
		}

		/**
		 * Clear the contents of this DBObject
		 */
		public function clear()
		{
			$this->dirty = false;
			$this->data = array();
		}

		public function unset_primary()
		{
			unset($this->data[$this->primary]);
		}

		/**
		 * simple element accessors. You don't have to write the table prefix
		 * if you use these functions.
		 *
		 * See also http://www.php.net/manual/en/language.oop5.overloading.php
		 */
		public function __get($var)
		{
			$name = ($var=='id'?$this->primary:$this->name($var));
			if(isset($this->data[$name]))
				return $this->data[$name];
			return null;
		}

		public function __set($var, $value)
		{
			$this->dirty = true;
			if($var=='id')
				return ($this->data[$this->primary] = $value);
			else
				return ($this->data[$this->name($var)] = $value);
		}

		public function __isset($var)
		{
			// TODO I could also use the field list here
			return isset($this->data[$this->name($var)]);
		}

		public function __unset($var)
		{
			unset($this->data[$this->name($var)]);
		}

		/**
		 * if you really want to use the long names...
		 */
		public function get($var, $default=null)
		{
			if(isset($this->data[$var])) {
				return $this->data[$var];
			}

			$relations = $this->relations();

			if(isset($relations[$var])) {
				// FIXME It seems that I assumed that I'll always get a
				// DBOContainer back from DBObject::related(). That is
				// NOT always the case. (DB_REL_SINGLE)
				$obj = $this->related($var);
				if($obj instanceof DBObject)
					$this->data[$var] = $obj->id();
				else
					$this->data[$var] = $obj->ids();
				return $this->data[$var];
			}
		}

		public function set($var, $value)
		{
			$this->dirty = true;
			$this->data[$var] = $value;
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */
		public function rewind() { reset($this->data); }
		public function current() { return current($this->data); }
		public function key() { return key($this->data); }
		public function next() { return next($this->data); }
		public function valid() { return $this->current() !== false; }

		/**
		 * Various helpers
		 */

		protected static $fulltext_fields = array();
		protected static $field_list = array();
		protected static $_tables = array();

		public function &_fulltext_fields()
		{
			if(!isset(DBObject::$fulltext_fields[$this->class])) {
				DBObject::$fulltext_fields[$this->class] = array();
				$rows = $this->field_list();
				foreach($rows as &$row) {
					if(stripos($row['Type'], 'char')!==false
						|| stripos($row['Type'], 'text')!==false)
						DBObject::$fulltext_fields[$this->class][] =
							$row['Field'];
				}
			}
			return DBObject::$fulltext_fields[$this->class];
		}
		
		public function &field_list($field = null)
		{
			if(!isset(DBObject::$field_list[$this->class])) {
				$rows = DBObject::db_get_array('SHOW COLUMNS FROM '
					.$this->table(), 'Field');
				DBObject::$field_list[$this->class] = $rows;
			}
			if($field!==null
				&& isset(DBObject::$field_list[$this->class][$field]))
				return DBObject::$field_list[$this->class][$field];
			return DBObject::$field_list[$this->class];
		}

		public static function &tables()
		{
			if(DBObject::$_tables===null)
				DBObject::$_tables = DBObject::db_get_array('SHOW TABLES');
			return DBObject::$_tables;
		}

		public function _select_sql($joins)
		{
			return 'SELECT * FROM '.$this->table.$joins.' WHERE 1';
		}

		public static function dump()
		{
			echo '<pre>';
			echo "<b>field list</b>\n";
			print_r(DBObject::$field_list);
			echo "<b>relations</b>\n";
			print_r(DBObject::$relations);
			echo "<b>tables</b>\n";
			print_r(DBObject::$_tables);
			echo "<b>transaction</b>\n";
			print_r(DBObject::$in_transaction);
			echo "<b>handles</b>\n";
			print_r(DBObject::$dbhandle);
			echo '</pre>';
		}
	}

?>
