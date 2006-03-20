<?php
	/**
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here:
	*	http://www.gnu.org/licenses/gpl.html
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
	 *
	 * There is a long and commented example at the bottom of this file.
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
		 * This array describes the relations with other tables
		 *
		 * TODO: add description of relation table
		 */
		protected $relations = array();

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
		 * If you have no special needs (relations, DB not implementing
		 * my naming scheme) you don't even need to explicitly derive your
		 * own class from DBObject, instead you can simply pass the class
		 * name to this function.
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
		 * DB relations. The only part that's really hard to understand and
		 * also the only part that is absolutely undocumented right now.
		 *
		 * TODO: add documentation. No, really!
		 *
		 * See also the long example at the bottom of this file
		 */
		public function &get_related($key, $reltype=DB_REL_UNSPECIFIED)
		{
			switch($reltype) {
				case DB_REL_SINGLE:
					return get_related_object($key);
				case DB_REL_MANY:
					return get_related_container($key);
				case DB_REL_MANYTOMANY:
					return get_related_container_many_to_many($key);
				default:
					die('DBObject::get_related: relation type unknown');
			}
		}

		/**
		 * helper function for all get_related_* functions. Finds the entry in
		 * the relations table which describes how to fetch related objects
		 * or containers.
		 */
		protected function find_rel_entry($key, $reltype=DB_REL_UNSPECIFIED)
		{
			if($reltype && ($rel =& $this->relations[$reltype])) {
				if(array_key_exists($key, $rel)) {
					return array($rel[$key], $key);
				} else if(($k = array_search($key, $rel))!==false){
					return array($key, $k);
				}
			} else {
				foreach($this->relations as $reltype => &$rel) {
					if(array_key_exists($key, $rel)) {
						return array($rel[$key], $key);
					} else if(($k = array_search($key, $rel))!==false){
						return array($key, $k);
					}
				}
			}

			return array(null,null);
		}

		/**
		 * Normally, you would use get_related(), not these helper functions. They are
		 * publicly accessible anyway, if you know exactly what you need to do.
		 */

		/**
		 * Return a single related object. This is mostly used on the N side of a
		 * 1-N relation.
		 *
		 * Example:
		 * Every pen in a classroom belongs to a pupil. If you have a Pen DBObject,
		 * you would use this function to get its owner.
		 *
		 * $pen = DBObject::find('Pen', 42); // 42 being the unique ID of the key
		 * $owner = $pen->get_related_object('Pupil');
		 *
		 * In terms of SQL fields, the relation would probably be expressed as follows:
		 * tbl_pen:   (pen_id, pen_pupil_id, ... [further attributes of this pen])
		 * tbl_pupil: (pupil_id, ... [further attributes of the pupil])
		 */
		public function &get_related_object($key)
		{
			list($class, $key) = $this->find_rel_entry($key, DB_REL_SINGLE);
			if($class) {
				return DBObject::find($class, $this->$key);
			}
			return null;
		}

		/**
		 * Return a related container. This function may be seen as the opposite of
		 * get_related_object()
		 *
		 * To use the pen-and-pupil-example again:
		 *
		 * $pupil = DBObject::find('Pupil', 13);
		 * $pens = $pupil->get_related_container('Pen');
		 *
		 * $pens is now a DBOContainer holding all Pens that this Pupil owns.
		 */
		public function &get_related_container($key)
		{
			list($class, $key) = $this->find_rel_entry($key, DB_REL_MANY);
			if($class) {
				$obj = DBObject::create($class);
				list(,$relkey) = $obj->find_rel_entry($this->class, DB_REL_SINGLE);

				return DBOContainer::find($obj, array($relkey => $this->id()));
			}
		}

		/**
		 * Return the other side of a N-to-M relation.
		 *
		 * Example:
		 * There are many different music styles and many different listeners. Every
		 * music style is listened by 0-X people, and everyone listens to 0-Y music
		 * styles.
		 *
		 * tbl_music_style: (music_style_id, music_style_name, ...)
		 * tbl_listener:    (listener_id, listener_name, ...)
		 * tbl_listener_to_music_style: (music_style_id, listener_id)
		 *
		 * It is not too nice that the user of the DBObject classes has to know whether
		 * it is a N-to-M relation or a 1-to-N relation. He just wants to get a container
		 * of related objects.
		 * TODO: think about these last two sentences.
		 */
		public function &get_related_container_many_to_many($key)
		{
			list($class, $key) = $this->find_rel_entry($key, DB_REL_MANYTOMANY);
			if($class) {
				$obj = DBObject::create($class);
				return DBOContainer::find_many_to_many($this, $class, array($key => $this->id()));
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
		 * See DBObject::get_related_container_many_to_many()
		 */
		public static function &find_many_to_many($sourceobj, $class, $params)
		{
			$container = null;
			$p = each($params);

			if(is_string($class)) {
				$container = new DBOContainer(DBObject::create($class));
			} else {
				$container = new DBOContainer($class);
			}
			$key = $container->obj->primary();
			$container->add_join($p[0], $p[0] . '.' . $key . '=' . $container->obj->table() . '.' . $key);
			$container->add_clause($p[0] . '.' . $sourceobj->primary() . '=', $p[1]);
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

	/*
	 * Examples are worth more than thousand words:
	 * 
	 

	minimal example:

	// you do not even need to write out the News class
	// for the example below. It is just a simple
	// demonstration how you can automatize things
	// by overriding member functions
	//
	// CREATE TABLE tbl_news (
	// 	news_id INT NOT NULL AUTO_INCREMENT,
	// 	news_title VARCHAR(255),
	// 	news_text TEXT,
	// 	PRIMARY KEY(news_id));
	// 

	// Class declaration
	class News extends DBObject {
		protected $class = __CLASS__; // necessary boilerplate if you override the class
		public function insert()
		{
			$this->creation_dttm = time();
			return parent::insert();
		}
	}

	// Example usage

	// [...]

	$id = intval($_GET['id']);
	if($id && ($do = DBObject::find('News', $id))) {
		// ID was valid
		display_news_entry($do->data());
	} else {
		$container = DBOContainer::find('News');
		foreach($container as &$obj) {
			display_news_entry($obj->data());
		}
	}

	function display_news_entry($data)
	{
		echo '<h2>' . $data['news_title'] . '</h2>';
		echo '<p>' . $data['news_text'] . '</p>';
	}

	// [...]





	a somewhat longer and more extensive example:

	class User extends DBObject {
		protected $class = __CLASS__;
		protected $table = 'tbl_users'; // naming scheme not consistent with DBObject rules
	}

	class Customer extends DBObject {
		protected $class = __CLASS__;
		
		protected $relations = array(
			DB_REL_SINGLE => array(
				'seller' => 'User'
			),
			DB_REL_MANY => array(
				'CustomerContact'
			),
			DB_REL_MANYTOMANY => array(
				'tbl_customer_to_user' => 'User'
			)
		);
	}

	class CustomerContact extends DBObject {
		protected $class = __CLASS__;

		protected $relations = array(
			DB_REL_SINGLE => array(
				'customer_id' => 'Customer' // this sucks totally. Why do I have to write everything two times (for both objects)
			)
		);
	}

	// get customer contact with ID 40
	$cc = DBObject::find('CustomerContact', 40);

	// get customer
	$customer = $cc->get_related_object('Customer');
	
	// get a list of all customer contacts. The customer with the ID 40
	// is part of the list we got back.
	$container = $customer->get_related_container('CustomerContact');

	// now, get all consultants for this customer
	$c2 = $customer->get_related_container_many_to_many('User');
	echo "Kunde:\n";
	print_r($customer->data());
	echo "\nKontakte:\n";
	print_r($c2->data());

	// we have done no modifications, but do update() every DBObject anyway
	// (see __call() for more informations about how this works)
	$container->update();

	// get user with ID 2
	$user = DBObject::find('User', 2);

	// change email address ...
	$user->email = 'mk@irregular.ch';

	// and store the changed record
	$user->store();

	print_r($user->data());;

	// change the same record using a different DBObject (no locking
	// of DB records!)
	$blah = DBObject::find('User', 2);
	$blah->email = 'matthias@spinlock.ch';
	$blah->store();
	
	// verify that the modification has not changed our original DBObject,
	// but that we can get the modifications by refresh()ing our first object
	print_r($user->data());
	$user->refresh();
	print_r($user->data());

	// insert a new record into the database
	$obj = new User();

	$obj->login = 'alkdjkjhsa';
	$obj->name = 'suppe';
	$obj->forename = 'kuerbis';
	$obj->email = 'kuerbis@example.com';
	$obj->insert();

	*/

?>
