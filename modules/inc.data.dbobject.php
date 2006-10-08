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

	require_once MODULE_ROOT.'inc.event-broadcaster.php';

	class DBObject extends Broadcaster implements Iterator, ArrayAccess {

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

		// reverse of DBObject::name()
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
		 * main DB handle (holds the PDO instances)
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
		 * unset this flag if you do not want some fields to be updated
		 * automatically prior to update or insertion (creation_dttm,
		 * update_dttm, author_id, ...)
		 */
		protected $auto_update_fields = true;

		public function disable_auto_update()
		{
			$this->auto_update_fields = false;
		}

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
				$obj->set($k, $v);
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
			$this->listener_call('refresh');
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
			$this->listener_call('store');
			$this->auto_update_fields();

			$dbh = DBObject::db($this->db_connection_id);
			$vals = array();
			$fields = array_keys($this->field_list());
			foreach($fields as $field) {
				if($field==$this->primary)
					continue;
				if(isset($this->data[$field]))
					$vals[] = $field.'='
						.$dbh->quote($this->data[$field]);
			}
			$vals_sql = implode(',', $vals);

			DBObject::db_start_transaction($this->db_connection_id);
			$res = DBObject::db_query('UPDATE ' . $this->table . ' SET '
				. $vals_sql . ' WHERE '
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
		public function insert($force_primary = false)
		{
			$this->listener_call('store');
			$this->auto_update_fields(true);

			$dbh = DBObject::db($this->db_connection_id);
			$keys = array();
			$vals = array();
			$fields = array_keys($this->field_list());
			foreach($fields as $field) {
				if($field==$this->primary && !$force_primary)
					continue;
				if(isset($this->data[$field])) {
					$keys[] = $field;
					$vals[] = $dbh->quote($this->data[$field]);
				}
			}
			$vals_sql = '('.implode(',', $keys).') VALUES ('.implode(',', $vals).')';

			DBObject::db_start_transaction($this->db_connection_id);
			$res = DBObject::db_query('INSERT INTO ' . $this->table . $vals_sql,
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
		 */
		protected function _update_relations()
		{
			if(!isset(DBObject::$relations[$this->class]))
				return true;
			foreach(DBObject::$relations[$this->class] as &$rel) {
				if($rel['type']==DB_REL_N_TO_M) {
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
					if(is_array($this->data[$field])
							&& count($this->data[$field])) {
						$ids = array();
						foreach($this->data[$field] as $id)
							if($id = intval($id))
								$ids[] = $id;
						if(!count($ids))
							return true;
						$sql = 'INSERT INTO '.$rel['table']
							.' ('.$this->primary.','
							.$rel['foreign'].') VALUES ('
							.$this->id().','
							.implode('),('.$this->id().',',
							$ids).')';
						if(DBObject::db_query($sql,
								$this->db_connection_id)===false)
							return false;
					}
				} else if($rel['type']==DB_REL_3WAY) {
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
					if(is_array($this->data[$field])
							&& count($this->data[$field])) {
						$frags = array();
						foreach($this->data[$field] as $v1 => $v2)
							if(($id1 = intval($v1))
									&& ($id2 = intval($v2)))
								$frags[] = $id1.','.$id2;
						if(!count($frags))
							return true;
						$o3 = DBObject::create($rel['choices']);
						$sql = 'INSERT INTO '.$rel['table']
							.' ('.$this->primary.','
							.$rel['foreign'].','
							.$o3->primary().') VALUES ('
							.$this->id().','
							.implode('),('.$this->id().',',
							$frags).')';
						if(DBObject::db_query($sql,
								$this->db_connection_id)===false)
							return false;
					}
				}
			}

			return true;
		}

		/**
		 * automatically fill up update_dttm, creation_dttm, author_id
		 * et al.
		 */
		protected function auto_update_fields($new = false)
		{
			if(!$this->auto_update_fields)
				return;
			$fields = $this->field_list();
			$dttm_regex = '/_('.($new?'creation|':'').'update)_dttm$/';
			$author_regex = '/_(author|creator)_id$/';
			foreach($fields as $field => $type) {
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
			$this->listener_call('delete');
			if(!$this->id())
				return true;
			DBObject::db_start_transaction($this->db_connection_id);
			$id = $this->id();
			$ret = DBObject::db_query('DELETE FROM ' . $this->table
				. ' WHERE ' . $this->primary . '=' . $id,
				$this->db_connection_id);
			$this->unset_primary();
			if(isset(DBObject::$relations[$this->class])) {
				foreach(DBObject::$relations[$this->class] as &$rel) {
					if($rel['type']==DB_REL_N_TO_M
							|| $rel['type']==DB_REL_3WAY) {
						if(!DBObject::db_query('DELETE FROM '
								.$rel['table'].' WHERE '
								.$this->primary
								.'='.$id,
								$this->db_connection_id)) {
							DBObject::db_rollback(
								$this->db_connection_id);
							return false;
						}
					}
				}
			}

			// custom content actions
			if($unlink_fields = Swisdk::config_value(
					sprintf('content.%s.%s.delete.unlink_file',
						$this->db_connection_id, $this->class))) {
				$unlink_fields = array_map('trim', explode(',', $unlink_fields));
				foreach($unlink_fields as $field)
					@unlink(DATA_ROOT.'upload/'.$this->data[$field]);
			}

			DBObject::db_commit($this->db_connection_id);
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
				'type' => DB_REL_N_TO_M, 'table' => $table,
				'join' => $o2->table().'.'.$o2->primary().'='
					.$table.'.'.$o2->primary(),
				'field' => $options, 'class' => $c2, 'foreign' => $o2->primary());
			DBObject::$relations[$c2][$rel1] = array(
				'type' => DB_REL_N_TO_M, 'table' => $table,
				'join' => $o1->table().'.'.$o1->primary().'='
					.$table.'.'.$o1->primary(),
				'field' => $options, 'class' => $c1, 'foreign' => $o1->primary());
		}

		/**
		 * DBObject::threeway('Article', 'Realm', 'Role');
		 */
		public static function threeway($c1, $c2, $c3, $options = null)
		{
			$o1 = DBObject::create($c1);
			$o2 = DBObject::create($c2);

			$rel = $c2;
			if($options!==null)
				$rel = $options;

			$table = 'tbl_'.$o1->name('to_'.$o2->name(''));
			$table = substr($table, 0, strlen($table)-1);

			DBObject::$relations[$c1][$rel] = array(
				'type' => DB_REL_3WAY, 'table' => $table,
				'join' => $o2->table().'.'.$o2->primary().'='
					.$table.'.'.$o2->primary(),
				'class' => $c2, 'choices' => $c3,
				'foreign' => $o2->primary(),
				'field' => $rel);
		}

		/**
		 * get related DBObject or DBOContainer (depending on relation type)
		 *
		 * @param class: class of related object OR name given to the relation (n-to-m)
		 * @param params: additional params for find, related_many or related_many_to_many
		 */
		public function related($class, $params=null)
		{
			$rel =& DBObject::$relations[$this->class][$class];
			switch($rel['type']) {
				case DB_REL_SINGLE:
					if(isset($this->data[$rel['field']])) {
						if(!is_array($params))
							return DBObject::find($rel['class'],
								$this->data[$rel['field']]);
						else
							return DBObject::find($rel['class'],
							array_merge($params,
								array($rel['foreign'].'=',
								$this->data[$rel['field']])));
					} else
						return DBObject::create($rel['class']);
				case DB_REL_MANY:
					return $this->related_many($rel, $params);
				case DB_REL_N_TO_M:
					return $this->related_many_to_many($rel, $params);
				case DB_REL_3WAY:
					return $this->related_3way($rel, $params);
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

		protected function related_3way(&$rel, $params=null)
		{
			return $this->related_many_to_many($rel, $params);
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
				$prefix = 'db'.($connection_id=='db'?'':'.'.$connection_id);
				$driver = Swisdk::config_value($prefix.'.driver');
				if($driver=='sqlite') {
					DBObject::$dbhandle[$connection_id] = new PDO('sqlite:'
						.Swisdk::config_value($prefix.'.dbfile'));
				} else if($driver=='mysql') {
					$dbname = Swisdk::config_value($prefix.'.dbname');
					// backward compatibility:
					if(!$dbname)
						$dbname = Swisdk::config_value($prefix.'.database');
					DBObject::$dbhandle[$connection_id] = new PDO(
						sprintf('mysql:host=%s;dbname=%s',
							Swisdk::config_value($prefix.'.host'),
							$dbname),
						Swisdk::config_value($prefix.'.username'),
						Swisdk::config_value($prefix.'.password'));
				}
				if(Swisdk::config_value('error.debug_mode'))
					DBObject::$dbhandle[$connection_id]->setAttribute(
					PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			}

			return DBObject::$dbhandle[$connection_id];
		}

		public function db_driver()
		{
			return DBObject::db($this->db_connection_id)->getAttribute(PDO::ATTR_DRIVER_NAME);
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
			Swisdk::log($sql, 'db');
			$dbh = DBObject::db($connection_id);
			$result = $dbh->query($sql);
			DBObject::$error_obj = null;
			if($result===false) {
				$error = new DBError(sprintf(dgettext('swisdk', 'Database error: %s'),
					implode(', ', $dbh->errorInfo())), $sql);
				if(DBObject::$handle_error)
					SwisdkError::handle($error);
				else
					DBObject::$error_obj = $error;
				return false;
			}
			if($result instanceof PDOStatement)
				$result->setFetchMode(PDO::FETCH_ASSOC);
			return $result;
		}

		public static function db_create_temporary_table($class, $fields)
		{
			$dbo = DBObject::create($class);
			$p = $dbo->_prefix();
			$sql = 'CREATE TEMPORARY TABLE '.$dbo->table().' ('
				.$p.implode(' INTEGER, '.$p, $fields).' INTEGER)';
			return DBObject::db_query($sql);
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
			return $res->fetch();
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
					while($row = $res->fetch())
						$array[$row[$key]] = $row[$val];
				} else {
					while($row = $res->fetch())
						$array[$row[$result_key]] = $row;
				}
			} else {
				while($row = $res->fetch())
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
		public static function db_escape($str,
			$connection_id = DB_CONNECTION_DEFAULT)
		{
			return DBObject::db($connection_id)->quote($str);
		}

		/**
		 * this function is used internally by DBOContainer::add_cl
		 */
		public static function db_escape_ref($key, &$str,
			$connection_id = DB_CONNECTION_DEFAULT)
		{
			$str = DBObject::db($connection_id)->quote($str);
		}

		/**
		* returns the primary key value of the last inserted row
		*/
		public static function db_insert_id($connection_id = DB_CONNECTION_DEFAULT)
		{
			return DBObject::db($connection_id)->lastInsertId();
		}

		/**
		 * Wrap DB transaction functions
		 */

		protected static $in_transaction = array(DB_CONNECTION_DEFAULT => 0);

		public static function db_start_transaction($connection_id = DB_CONNECTION_DEFAULT)
		{
			if(DBObject::$in_transaction[$connection_id]==0)
				DBObject::db($connection_id)->beginTransaction();
			DBObject::$in_transaction[$connection_id]++;
		}

		public static function db_commit($connection_id = DB_CONNECTION_DEFAULT)
		{
			DBObject::$in_transaction[$connection_id]--;
			if(DBObject::$in_transaction[$connection_id]<=0) {
				$dbh = DBObject::db($connection_id);
				$dbh->commit();
				DBObject::$in_transaction[$connection_id] = 0;
			}
		}

		public static function db_rollback($connection_id = DB_CONNECTION_DEFAULT)
		{
			DBObject::$in_transaction[$connection_id]--;
			if(DBObject::$in_transaction[$connection_id]<=0) {
				$dbh = DBObject::db($connection_id);
				$dbh->rollBack();
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
			$this->listener_call('clear');
			$this->dirty = false;
			$this->data = array();
		}

		public function unset_primary()
		{
			$this->dirty = true;
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
			return isset($this->data[$this->name($var)]);
		}

		public function __unset($var)
		{
			unset($this->data[$this->name($var)]);
		}

		/**
		 * if you really want to use the long names...
		 */
		public function get($var)
		{
			if(isset($this->data[$var])) {
				return $this->data[$var];
			}

			$relations = $this->relations();

			if(isset($relations[$var])) {
				$obj = $this->related($var);
				switch($relations[$var]['type']) {
					case DB_REL_SINGLE:
						$this->data[$var] = $obj->id();
						break;
					case DB_REL_MANY:
					case DB_REL_N_TO_M:
						$this->data[$var] = $obj->ids();
						break;
					case DB_REL_3WAY:
						$this->data[$var] = $obj->collect_full(
							DBObject::create($relations[$var]['class'])->primary(),
							DBObject::create($relations[$var]['choices'])->primary());
						break;
				}
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
		 * ArrayAccess implementation (see PHP SPL)
		 */
		public function offsetExists($var)
		{
			$relations = $this->relations();
			return isset($this->data[$var]) || isset($relations[$var]);
		}

		public function offsetGet($var)
		{
			return $this->get($var);
		}

		public function offsetSet($var, $value)
		{
			return $this->set($var, $value);
		}

		public function offsetUnset($var)
		{
			unset($this->data[$var]);
		}

		/**
		 * Various helpers
		 */

		protected static $_fulltext_fields = array();
		protected static $_field_list = array();
		protected static $_tables = array();

		public function &_fulltext_fields()
		{
			if(!isset(DBObject::$_fulltext_fields[$this->db_connection_id]))
				DBObject::$_fulltext_fields[$this->db_connection_id] = array();
			$fulltext_fields =& DBObject::$_fulltext_fields[$this->db_connection_id];
			if(!isset($fulltext_fields[$this->class])) {
				$fulltext_fields[$this->class] = array();
				$rows = $this->field_list();
				foreach($rows as $field => $type) {
					if(in_array($type, array(
							DB_FIELD_STRING, DB_FIELD_LONGTEXT)))
						$fulltext_fields[$this->class][] = $field;
				}
			}
			return $fulltext_fields[$this->class];
		}

		public function &field_list()
		{
			if(isset(DBObject::$_field_list[$this->db_connection_id][$this->class]))
				return DBObject::$_field_list[$this->db_connection_id][$this->class];
			$fl = array();
			$driver = $this->db_driver();
			$relations = $this->relations();

			if($driver=='mysql') {
				$columns = DBObject::db_get_array('SHOW COLUMNS FROM '
					.$this->table(), array('Field','Type'));
				$fl = array();
				foreach($columns as $field => $type) {
					// relations?
					if(isset($relations[$field])) {
						$fl[$field] = DB_FIELD_FOREIGN_KEY
							|($relations[$field]['type']<<10);
						continue;
					}

					// determine field type
					if(stripos($field,'dttm')!==false) {
						$fl[$field] = DB_FIELD_DATE;
					} else if(stripos($type, 'text')!==false) {
						$fl[$field] = DB_FIELD_LONGTEXT;
					} else if($type=='tinyint(1)') {
						$fl[$field] = DB_FIELD_BOOL;
					} else if(stripos($type, 'int')!==false) {
						$fl[$field] = DB_FIELD_INTEGER;
					} else {
						$fl[$field] = DB_FIELD_STRING;
					}
				}
				$field_list[$this->class] = $fl;

			} else if($driver=='sqlite') {
				$columns = DBObject::db_get_array('PRAGMA table_info(\''
					.$this->table().'\')', array('name', 'type'));
				foreach($columns as $field => $type) {
					// relations?
					if(isset($relations[$field])) {
						$fl[$field] = DB_FIELD_FOREIGN_KEY
							|($relations[$field]['type']<<10);
						continue;
					}

					// determine field type
					if(stripos($field,'dttm')!==false) {
						$fl[$field] = DB_FIELD_DATE;
					} else if(stripos($type, 'BLOB')!==false) {
						$fl[$field] = DB_FIELD_LONGTEXT;
					} else if($type=='BOOL') {
						$fl[$field] = DB_FIELD_BOOL;
					} else if(stripos($type, 'INTEGER')!==false) {
						$fl[$field] = DB_FIELD_INTEGER;
					} else {
						$fl[$field] = DB_FIELD_STRING;
					}

				}
				$field_list[$this->class] = $fl;
			} else {
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Cannot act on PDO DB type %s'), $driver)));
			}

			DBObject::$_field_list[$this->db_connection_id][$this->class] = $fl;
			return DBObject::$_field_list[$this->db_connection_id][$this->class];
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
			if(!Swisdk::config_value('error.debug_mode'))
				return;
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
