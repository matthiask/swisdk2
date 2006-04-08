<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	define('DB_REL_UNSPECIFIED', 0);
	define('DB_REL_SINGLE', 1);
	define('DB_REL_MANY', 2);
	define('DB_REL_MANYTOMANY', 3);

	/**
	 * DBObject
	 *
	 * All database access goes through this class. It provides a lightweight
	 * yet extensible, object oriented and secure data layer.
	 *
	 * Its main functionality does not depend on other SWISDK packages. For now,
	 * it only uses SwisdkError::handle for error conditions.
	 */

	/**
	 * Examples are worth more than thousand words
	 * 
	 *
	 * 
	 * minimal example:
	 * ***********************************************************************
	 *
	 * // CREATE TABLE tbl_news (
	 * // 	news_id INT NOT NULL AUTO_INCREMENT,
	 * // 	news_title VARCHAR(255),
	 * // 	news_text TEXT,
	 * // 	PRIMARY KEY(news_id));
	 * // 
	 *
	 * // [...]
	 *
	 * //
	 * // This is already sufficient to display a news listing with the intro
	 * // and a separate view which features the whole news text.
	 * // 
	 * 
	 * $id = intval($_GET['id']);
	 * if($id && ($do = DBObject::find('News', $id))) {
	 * 	// ID was valid
	 * 	display_news_entry($do->data());
	 * } else {
	 * 	$container = DBOContainer::find('News');
	 * 	foreach($container as &$obj) {
	 * 		display_news_entry_listing($obj->data());
	 * 	}
	 * }
	 *
	 * function display_news_entry_listing($data)
	 * {
	 * 	echo '<h2><a href="?id="' . $data['news_id'] . '">'
	 * 		. $data['news_title'] . '</a></h2>';
	 * 	echo '<p>' . $data['news_intro'] . '</p>';
	 * }
	 *
	 * function display_news_entry($data)
	 * {
	 * 	echo '<h2>' . $data['news_title'] . '</h2>';
	 * 	echo '<p>' . $data['news_intro'] . '</p>';
	 * 	echo '<p>' . $data['news_text'] . '</p>';
	 * }
	 *
	 * // [...]
	 *
	 *
	 * 
	 * This example demonstrates how you can manipulate the database through
	 * the DBObject
	 * ***********************************************************************
	 * 
	 * // get user with ID 2
	 * $user = DBObject::find('User', 2);
	 * // change email address ...
	 * $user->email = 'mk@irregular.ch';
	 * // and store the changed record
	 * $user->store();
	 *
	 * print_r($user->data());;
	 * 
	 * // change the same record using a different DBObject (no locking
	 * // of DB records!)
	 * $blah = DBObject::find('User', 2);
	 * $blah->email = 'matthias@spinlock.ch';
	 * $blah->store();
	 * 
	 * // verify that the modification has not changed our original DBObject,
	 * // but that we can get the modifications by refresh()ing our first object
	 * print_r($user->data());
	 * $user->refresh();
	 * print_r($user->data());
	 *
	 * // insert a new record into the database
	 * $obj = DBObject::create('User');
	 * $obj->login = 'alkdjkjhsa';
	 * $obj->name = 'suppe';
	 * $obj->forename = 'kuerbis';
	 * $obj->email = 'kuerbis@example.com';
	 * $obj->password = md5('testpassword');
	 * $obj->insert();
	 *
	 *
	 * 
	 * Relations example: Image Collection
	 * ***********************************************************************
	 *
	 * Every picture has exactly one author, but the author might have made
	 * multiple pictures. The following two statements express the described
	 * relation (Note: you only need to use one possibility)
	 *
	 * DBObject::belongs_to('Picture', 'Author');
	 * DBObject::has_many('Author', 'Picture');
	 *
	 * The corresponding CREATE TABLE commands for MySQL would be:
	 * 
	 * CREATE TABLE tbl_author (
	 * 	author_id INT NOT NULL AUTO_INCREMENT,
	 * 	author_name VARCHAR(255),
	 * 	PRIMARY KEY(author_id));
	 *
	 * CREATE TABLE tbl_picture (
	 * 	picture_id INT NOT NULL AUTO_INCREMENT,
	 * 	picture_author_id INT,
	 * 	picture_filename VARCHAR(255)
	 * 	PRIMARY KEY(picture_id));
	 *
	 * There are multiple categories (People, Events, Places...). Every picture
	 * may have 0-n categories:
	 *
	 * DBObject::n_to_m('Picture', 'Category');
	 *
	 * CREATE TABLE tbl_category (
	 * 	category_id INT NOT NULL AUTO_INCREMENT,
	 * 	category_title VARCHAR(255)
	 * 	PRIMARY KEY(category_id));
	 *
	 * CREATE TABLE tbl_picture_to_category (
	 * 	picture_id INT,
	 * 	category_id INT);
	 *
	 * You must pass the two classes to DBObject::n_to_m() in the same order as
	 * in the table name.
	 *
	 * Complete code example (That's right, you don't need to explicitly derive
	 * the Author, Picture and Category classes):
	 *
	 * DBObject::belongs_to('Picture', 'Author');
	 * DBObject::n_to_m('Picture', 'Category');
	 *
	 * // get author ID 42 from database
	 * $author = DBObject::create('Author', 42);
	 *
	 * // get all pictures that he made
	 * $pictures = $author->related('Picture');
	 *
	 * // loop over all pictures and get their categories
	 * foreach($pictures as &$picture) {
	 * 	$categories = $picture->related('Category');
	 * 	// [...] do something with it, display a gallery or whatever
	 * }
	 * 
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
		public function shortname($tok)	{ return str_replace($this->prefix,'',$tok); }
		public function _class()	{ return $this->class; }
		public function relations()
		{
			if(isset(DBObject::$relations[$this->class]))
				return DBObject::$relations[$this->class];
			return array();
		}

		/**
		 * main DB handle (holds the mysqli instance in the current version of DBObject)
		 */
		protected static $dbhandle = null;

		/**
		 * This would be the only important variable: All others are simply here to
		 * serve the data!
		 */
		protected $data = array();

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
		private function _setup_dbvars()
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
			if(class_exists($class)) {
				return new $class();
			}

			$obj = new DBObject(false);
			$obj->class = $class;
			$obj->_setup_dbvars();
			return $obj;
		}

		/**
		 * factory function
		 *
		 * @return: DBObject
		 *
		 * This function returns a DBObject of type $class if the entry
		 * $id exists in the database.
		 *
		 * TODO add possibility to initialize on other fields (not only primary key)
		 */
		public static function &find($class, $params)
		{
			$obj = DBObject::create($class);
			if(is_array($params)) {
				$where = array(' WHERE 1 ');
				foreach($params as $k=>$v)
					$where[] = $k.'\''.DBObject::db_escape($v).'\' ';
				$obj->data = DBObject::db_get_row('SELECT * FROM '
					.$obj->table.implode(' AND ',$where));
				if($obj->data && count($obj->data))
					return $obj;
				$null = null;
				return $null;
			} else {
				$obj->id = $params;
				if($obj->refresh())
					return $obj;
				$null = null;
				return $null;
			} 
		}

		/**
		 * refresh (or load) data from database using the stored primary id
		 */
		public function refresh()
		{
			$this->data = DBObject::db_get_row('SELECT * FROM '.$this->table.' WHERE '.$this->primary.'='.$this->id());
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
			if(isset($this->data[$this->primary]) && $this->data[$this->primary]) {
				return $this->update();
			} else {
				return $this->insert();
			}
		}

		/**
		 * Explicitly request an update. This function has undefined behaviour
		 * if the primary key does not exist.
		 */
		public function update()
		{
			DBObject::db_start_transaction();
			DBObject::db_query('UPDATE ' . $this->table . ' SET '
				. $this->_vals_sql() . ' WHERE '
				. $this->primary . '=' . $this->id());
			$this->_update_relations();
			DBObject::db_commit();
			$this->dirty = false;
		}

		/**
		 * Explicitly request an insert be done. This should always succeed.
		 */
		public function insert()
		{
			DBObject::db_start_transaction();
			unset($this->data[$this->primary]);
			DBObject::db_query('INSERT INTO ' . $this->table
				. ' SET ' . $this->_vals_sql());
			$this->_update_relations();
			DBObject::db_commit();
			$this->dirty = false;
		}

		/**
		 * this helper should always be executed inside a transaction (that
		 * is actually the case when you use update() or insert(), the only
		 * place where this helper is used right now)
		 */
		private function _update_relations()
		{
			if(!isset(DBObject::$relations[$this->class]))
				return;
			foreach(DBObject::$relations[$this->class] as &$rel) {
				if($rel['type']==DB_REL_MANYTOMANY) {
					DBObject::db_query('DELETE FROM '.$rel['table'].' WHERE '
						.$this->primary.'='.$this->id());
					if(count($this->data[$rel['field']])) {
						$sql = 'INSERT INTO '.$rel['table'].' ('.$this->primary;
						$sql .= ','.$rel['foreign'].') VALUES ('.$this->id().',';
						$sql .= implode('),('.$this->id().',', $this->data[$rel['field']]);
						$sql .= ')';
						DBObject::db_query($sql);
					}
				}
			}
		}

		/**
		 * Private helper for update() and insert(). This function takes care
		 * of SQL injections by properly escaping every string that hits the
		 * database.
		 */
		private function _vals_sql()
		{
			$dbh = DBObject::db();
			$vals = array();
			foreach($this->data as $k => &$v) {
				// do not include n-to-m relations in query!
				// TODO better detection whether field came from this
				// table or not
				if(strpos($k,$this->prefix)===0)
					$vals[] = $k . '=\'' . $dbh->escape_string($v) . '\'';
			}
			return implode(',', $vals);
		}

		/**
		 * Delete the current object from the database.
		 */
		public function delete()
		{
			DBObject::db_query('DELETE FROM ' . $this->table
				. ' WHERE ' . $this->primary . '=' . $this->id());
			//TODO: $this->data = array(); ?
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
		 *
		 * TODO: add funky DBOContainer::find()-syntax for this case
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
			// do not set reverse mapping if user passed an explicit field
			// specification
			if($options)
				return;
			DBObject::$relations[$c2][$c1] =
				array('type' => DB_REL_MANY, 'field' => $field,
					'class' => $c1, 'table' => $o1->table(),
					'foreign_key' => $o1->primary());
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
				'join' => $o2->table().'.'.$o2->primary().'='.$table.'.'.$o2->primary(),
				'field' => $options, 'class' => $c2, 'foreign' => $o2->primary());
			DBObject::$relations[$c2][$rel1] = array(
				'type' => DB_REL_MANYTOMANY, 'table' => $table,
				'join' => $o1->table().'.'.$o1->primary().'='.$table.'.'.$o1->primary(),
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
					// see also DBObject::get() (around line 790)
					if(isset($this->data[$rel['field']]))
						return DBObject::find($rel['class'], $this->data[$rel['field']]);
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
			$container->add_join($rel['table'], $rel['join']);
			$container->add_clause($rel['table'].'.'.$this->primary().'=', $this->id());
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
						$data = array_merge($data, $this->related($class)->data());
						break;
					case DB_REL_MANY:
						$data[$class] = $this->related_many($rel)->data();
						break;
					case DB_REL_MANYTOMANY:
						$data[$class] = $this->related_many_to_many($rel)->data();
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
		 * @return: the DB handle
		 */
		protected static function &db()
		{
			if(is_null(DBObject::$dbhandle)) {
				//FIXME do not hardcode connection params
				DBObject::$dbhandle = new mysqli('localhost', 'root', '5h9tiSLosax', 'jungegru_stopoffroader');
				if(mysqli_connect_errno())
					SwisdkError::handle(new DBError("Connect failed: " . mysqli_connect_error()));
			}

			return DBObject::$dbhandle;
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
		public static function db_query($sql)
		{
			$dbh = DBObject::db();
			$result = $dbh->query($sql);
			if($dbh->errno)
				SwisdkError::handle(new DBError("Database error: " . $dbh->error, $sql));
			return $result;
		}

		/**
		 * Return the result of the passed SQL query as an array of associative
		 * values.
		 */
		public static function db_get_row($sql)
		{
			return DBObject::db_query($sql)->fetch_assoc();
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
		 * $titles = DBObject::db_get_array('SELECT id,title FROM table',array('id','title'));
		 */
		public static function db_get_array($sql, $result_key=null)
		{
			$res = DBObject::db_query($sql);
			$array = array();
			if($result_key) {
				if(is_array($result_key) && (($key = $result_key[0]) && ($val = $result_key[1]))) {
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
		public static function db_escape($str)
		{
			return DBObject::db()->escape_string($str);
		}

		public static function db_escape_ref(&$str)
		{
			$str = DBObject::db()->escape_string($str);
		}

		/**
		 * Wrap DB transaction functions
		 */
		public static function db_start_transaction()
		{
			DBObject::db()->autocommit(false);
		}

		public static function db_commit()
		{
			$dbh = DBObject::db();
			$dbh->commit();
			$dbh->autocommit(true);
		}

		public static function db_rollback()
		{
			$dbh = DBObject::db();
			$dbh->rollback();
			$dbh->autocommit(true);
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
		public function &data()
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

		/**
		 * simple element accessors. You don't have to write the table prefix
		 * if you use these functions.
		 *
		 * See also http://www.php.net/manual/en/language.oop5.overloading.php
		 */
		public function __get($var)
		{
			return $this->data[$this->name($var)];
		}

		public function __set($var, $value)
		{
			$this->dirty = true;
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
				// FIXME It seems that I assumed that I'll always get a DBOContainer
				// back from DBObject::related(). That is NOT always the case. (DB_REL_SINGLE)
				$this->data[$var] = $this->related($var)->ids();
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
		
		public function &field_list()
		{
			if(!isset(DBObject::$field_list[$this->class])) {
				$rows = DBObject::db_get_array('SHOW COLUMNS FROM '.$this->table());
				DBObject::$field_list[$this->class] = $rows;
			}
			return DBObject::$field_list[$this->class];
		}
	}

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
		 * SQL builder variables. see add_clause(), add_join(), init() and friends
		 */
		protected $clause_sql = ' WHERE 1 ';
		protected $order_columns = array();
		protected $limit = '';
		protected $joins = '';

		protected $fulltext_search = null;

		public function &object() { return $this->obj; }

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
			return new DBOContainer(DBObject::create($class));
		}

		/**
		 * Example:
		 *
		 * DBOContainer::find('News', array('news_active!=0', 'news_start_dttm>', time()));
		 *
		 * TODO params parsing does not really work yet. The example is totally bogus
		 */
		public static function &find($class, $params=null)
		{
			$container = null;
			if(is_string($class)) {
				$container = new DBOContainer(DBObject::create($class));
			} else {
				$container = new DBOContainer($class);
			}
			if(is_array($params))
				$this->add_clause_array($params);
			$container->init();
			return $container;
		}

		/**
		 * Build SQL query and fill up DBObject array
		 */
		public function init()
		{
			$sql = 'SELECT * FROM ' . $this->obj->table() . $this->joins . $this->clause_sql
				. $this->_fulltext_clause()
				. implode(',', $this->order_columns) . $this->limit;
			$result = DBObject::db_query($sql);
			while($row = $result->fetch_assoc()) {
				$obj = DBObject::create($this->class);
				$obj->set_data($row);
				$this->data[$obj->id()] = $obj;
			}
		}

		/**
		 * here you can pass SQL fragments. The data will be automatically escaped so you can pass
		 * anything you like.
		 *
		 * $doc->add_clause('pen_color=', $_POST['color']);
		 * $doc->add_clause('pen_length>', $_POST['min-length']);
		 */
		public function add_clause($clause, $data=null, $binding = 'AND')
		{
			if(is_null($data)) {
				$this->clause_sql .= $binding.' '.$clause.' ';
			} else if(is_array($data)) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $clause, $matches, PREG_PATTERN_ORDER);
				if(isset($matches[1])) {
					array_walk_recursive($data, array('DBObject', 'db_escape_ref'));
					$p = array();
					$q = array();
					foreach($matches[1] as $v) {
						$p[] = '/\{' . $v . '\}/';
						if(is_array($data[$v])) {
							$q[] = '('.implode(',', $data[$v]).')';
						} else {
							$q[] = $data[$v];
						}
					}
					$this->clause_sql .= $binding .' '.preg_replace($p, $q, $clause);
				}
			} else {
				$this->clause_sql .= $binding.' '.$clause . DBObject::db_escape($data);
			}
		}

		public function add_clause_array($params)
		{
			foreach($params as $k => $v)
				$this->add_clause($k, $v);
		}

		/**
		 * $doc->add_order_column('news_start_dttm', 'DESC');
		 */
		public function add_order_column($column, $dir=null)
		{
			$this->order_columns[] = $column
				. ($dir=='DESC'?' DESC':' ASC');
		}

		/**
		 * $doc->set_limit(10);
		 */
		public function set_limit($p1, $p2=null)
		{
			if(is_null($p2)) {
				$this->limit = ' LIMIT ' . $p1;
			} else {
				$this->limit = ' LIMIT ' . $p1 . ',' . $p2;
			}
		}

		/**
		 * $doc->add_join('tbl_users', 'pen_user_id=user_id');
		 */
		public function add_join($table, $clause)
		{
			$this->joins .= ' LEFT JOIN ' . $table . ' ON ' . $clause;
		}

		/**
		 * Set fulltext search clause
		 */
		public function set_fulltext($clause=null)
		{
			$this->fulltext_search = $clause;
		}

		protected function _fulltext_clause()
		{
			if($this->fulltext_search
				&& count($fields = $this->obj->_fulltext_fields())) {
				
				$sql = ' AND (';
				$search = DBObject::db_escape($this->fulltext_search);
				$sql .= implode(' LIKE \'%' . $search . '%\' OR ', $fields);
				$sql .= ' LIKE \'%' . $search . '%\')';
				return $sql;
			}
			return ' ';
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

		/**
		 * TODO: this is not really thought over...
		 *
		 * can be used to call delete, store etc. on the contained
		 * DBObjects
		 */
		public function __call($method, $args)
		{
			foreach($this->data as &$obj) {
				call_user_func_array(array(&$obj,$method), $args);
			}
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
