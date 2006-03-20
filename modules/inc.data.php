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
	 * $pictures = $author->get_related('Picture');
	 *
	 * // loop over all pictures and get their categories
	 * foreach($pictures as &$picture) {
	 * 	$categories = $picture->get_related('Category');
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
		 * various helpers and variable accessors (these variables are not mutable)
		 */
		public function table()		{ return $this->table; }
		public function primary()	{ return $this->primary; }
		public function name($tok)	{ return $this->prefix . $tok; }
		public function _class()	{ return $this->class; }

		/**
		 * main DB handle (holds the mysqli instance in the current version of DBObject)
		 */
		protected static $dbhandle = null;

		/**
		 * Static relations table
		 */
		private static $relations = array();

		/**
		 * relation definition functions
		 */
		public static function belongs_to($c1, $c2, $options = array())
		{
			$o1 = DBObject::create($c1);
			$o2 = DBObject::create($c2);
			$key = $o1->name($o2->name('id'));

			DBObject::$relations[$c1][$c2] =
				array('type' => DB_REL_SINGLE, 'foreign_id_key' => $key);
			DBObject::$relations[$c2][$c1] =
				array('type' => DB_REL_MANY, 'foreign_id_key' => $key);
		}

		public static function has_many($c1, $c2, $options = array())
		{
			DBObject::belongs_to($c2, $c1, $options);
		}

		public static function n_to_m($c1, $c2, $options = array())
		{
			$o1 = DBObject::create($c1);
			$o2 = DBObject::create($c2);

			$table = 'tbl_'.$o1->name('to_'.$o2->name(''));
			$table = substr($table, 0, strlen($table)-1);

			DBObject::$relations[$c1][$c2] = array(
				'type' => DB_REL_MANYTOMANY, 'link' => $table,
				'join' => $o2->table().'.'.$o2->primary().'='.$table.'.'.$o2->primary()
			);
			DBObject::$relations[$c2][$c1] = array(
				'type' => DB_REL_MANYTOMANY, 'link' => $table,
				'join' => $o1->table().'.'.$o1->primary().'='.$table.'.'.$o1->primary()
			);
		}

		public function get_related($class)
		{
			$rel =& DBObject::$relations[$this->class][$class];
			switch($rel['type']) {
				case DB_REL_SINGLE:
					return DBObject::find($class, $this->data[$rel['foreign_id_key']]);
				case DB_REL_MANY:
					$container = DBOContainer::create($class);
					$container->add_clause($rel['foreign_id_key'].'=',
						$this->id());
					$container->init();
					return $container;
				case DB_REL_MANYTOMANY:
					$container = DBOContainer::create($class);
					$container->add_join($rel['link'], $rel['join']);
					$container->add_clause($rel['link'].'.'.$this->primary().'=', $this->id());
					$container->init();
					return $container;
			}
		}

		/**
		 * This would be the only important variable: All others are simply here to
		 * serve the data!
		 */
		protected $data = array();

		/**
		 * @access: private
		 * @return: the DB handle
		 *
		 * This function would be private if PHP knew friend classes
		 */
		public static function &db()
		{
			if(is_null(DBObject::$dbhandle)) {
				DBObject::$dbhandle = new mysqli('localhost', 'root', 'bl4b', 'bugs');
				if(mysqli_connect_errno())
					SwisdkError::handle(new DBError("Connect failed: " . mysqli_connect_error()));
			}

			return DBObject::$dbhandle;
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
		 */
		public static function &find($class, $id)
		{
			$obj = DBObject::create($class);
			$obj->id = $id;
			if($obj->refresh()) {
				return $obj;
			}
			$null = null;
			return $null;
		}

		/**
		 * refresh (or load) data from database using the stored primary id
		 */
		public function refresh()
		{
			$dbh = DBObject::db();
			if($res = $dbh->query('SELECT * FROM '.$this->table.' WHERE '.$this->primary.'='.$this->id())) {
				if($this->data = $res->fetch_assoc())
					return true;
			}
			return false;
			// TODO SwisdkError::handle() ? should probably not be a fatal error
		}

		/**
		 * Store the DBObject. Automatically determines if it should create
		 * a new record or if it should update an existing one.
		 *
		 * TODO: use INSERT ... ON DUPLICATE KEY UPDATE ? need to read more...
		 */
		public function store()
		{
			if(isset($this->data[$this->primary])) {
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
			DBObject::db_query('UPDATE ' . $this->table . ' SET '
				. $this->_get_vals_sql() . ' WHERE '
				. $this->primary . '=' . $this->id());
		}

		/**
		 * Explicitly request an insert be done. This should always succeed.
		 */
		public function insert()
		{
			unset($this->data[$this->primary]);
			DBObject::db_query('INSERT INTO ' . $this->table
				. ' SET ' . $this->_get_vals_sql());
		}

		/**
		 * Private helper for update() and insert(). This function takes care
		 * of SQL injections by properly escaping every string that hits the
		 * database.
		 */
		private function _get_vals_sql()
		{
			$dbh = DBObject::db();
			$vals = array();
			foreach($this->data as $k => &$v) {
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

		/**
		 * @return: the value of the primary key
		 */
		public function id()
		{
			return intval($this->data[$this->primary]);
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
			$this->data = array_merge($this->data, $data);
		}

		/**
		 * Clear the contents of this DBObject
		 */
		public function clear()
		{
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
			return ($this->data[$this->name($var)] = $value);
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */
		public function rewind() { reset($this->data); }
		public function current() { return current($this->data); }
		public function key() { return key($this->data); }
		public function next() { return next($this->data); }
		public function valid() { return $this->current() !== false; }
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
		 */
		public static function &find($class, $params=null)
		{
			$container = null;
			if(is_string($class)) {
				$container = new DBOContainer(DBObject::create($class));
			} else {
				$container = new DBOContainer($class);
			}
			if($params) {
				$p = each($params);
				$container->add_clause($container->obj->name($p[0]), $p[1]);
			}
			$container->init();
			return $container;
		}

		/**
		 * Build SQL query and fill up DBObject array
		 */
		public function init()
		{
			$dbh = DBObject::db();
			$sql = 'SELECT * FROM ' . $this->obj->table() . $this->joins . $this->clause_sql
				. ' ' . implode(',', $this->order_columns) . $this->limit;
			$result = $dbh->query($sql);
			if($dbh->errno)
				SwisdkError::handle(new DBError("Database error: " . $dbh->error, $sql));
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
					array_walk_recursive($data, '_escape_string');
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
		 * TODO: this is not really thought over...
		 */
		public function __call($method, $args)
		{
			foreach($this->data as &$obj) {
				call_user_func_array(array($obj,$method), $args);
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

	function _escape_string(&$str)
	{
		static $dbh = null;
		if($dbh===null)
			$dbh = DBObject::db();
		$str = '\''.$dbh->escape_string($str).'\'';
	}


?>
