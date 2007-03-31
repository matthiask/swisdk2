<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class DBObjectML extends DBObject {
		protected $class = __CLASS__;

		/**
		 * translation object class
		 */
		protected $tclass = null;

		/**
		 * DBObject or DBOContainer with translation objects
		 */
		protected $obj;

		/**
		 * content DBObject instance
		 */
		protected $content_dbobj;

		/**
		 * the language id of this DBObjectML or one of the
		 * LANGUAGE_* constants
		 */
		protected $language;

		public function language()
		{
			if($this->language == LANGUAGE_DEFAULT)
				return Swisdk::language();
			return $this->language;
		}

		public function set_language($language)
		{
			if($this->language == LANGUAGE_ALL)
				SwisdkError::handle(new FatalError(
					dgettext('swisdk', 'Cannot assign language to DBObjectML in LANGUAGE_ALL mode')));
			if($language == LANGUAGE_DEFAULT)
				$this->language = Swisdk::language();
			$this->language = $language;
			$this->dbobj()->language_id = $this->language;
		}

		/**
		 * @return a DBObject or a DBOContainer depending on the value
		 * of $language above
		 *
		 * Creates and initializes the object if it does not exist already
		 */
		public function dbobj()
		{
			if(!$this->obj) {
				$tmp = $this->content_dbobj;
				if($this->language == LANGUAGE_ALL) {
					if($id = $this->id()) {
						$this->obj = DBOContainer::find($this->tclass, array(
							$tmp->name($this->primary).'=' => $id,
							':index' => $tmp->name('language_id')));
					} else
						$this->obj = DBOContainer::create($this->tclass);
				} else {
					$language = $this->language;
					if($language == LANGUAGE_DEFAULT)
						$language = Swisdk::language();

					if($id = $this->id())
						$this->obj = DBObject::find($this->tclass, array(
							$tmp->name($this->primary).'=' => $id,
							$tmp->name('language_id').'=' => $language));
					if(!$this->obj) {
						$this->obj = DBObject::create($this->tclass);
						$this->obj->language_id = $this->language;
					}
				}
				$this->obj->set_db_connection($this->db_connection_id);
			}
			return $this->obj;
		}

		public function &content_dbobj()
		{
			return $this->content_dbobj;
		}

		public function translation($language_id)
		{
			if($this->language!=LANGUAGE_ALL)
				return null;

			$container = $this->dbobj();
			if(!$container->offsetExists($language_id)) {
				$obj = DBObject::create($this->tclass);
				$obj->set_owner($this);
				$obj->language_id = $language_id;
				$container[$language_id] = $obj;
			}

			return $container[$language_id];
		}

		protected function _setup_dbvars()
		{
			parent::_setup_dbvars();
			if($this->tclass===null)
				$this->tclass = $this->class.'Content';
			DBObject::has_many($this, $this->tclass);
			DBObject::has_a($this->tclass, 'Language');
			$this->content_dbobj = DBObject::create($this->tclass);
		}

		public function __construct($language = LANGUAGE_DEFAULT, $setup_dbvars = true)
		{
			$this->language = $language;
			if($setup_dbvars)
				$this->_setup_dbvars();
		}

		public static function create($class, $language = LANGUAGE_DEFAULT)
		{
			if(class_exists($class))
				return new $class($language);

			$obj = new DBObjectML($language, false);
			$obj->class = $class;
			$obj->_setup_dbvars();
			$obj->language = $language;
			return $obj;
		}

		public static function create_with_data($class, $data,
			$language = LANGUAGE_DEFAULT)
		{
			$obj = DBObjectML::create($class, $language);

			foreach($data as $k => $v)
				$obj->set($k, $v);
			return $obj;
		}

		public static function find($class, $params, $language = LANGUAGE_DEFAULT)
		{
			$obj = DBObjectML::create($class, $language);
			if($obj->_find($params))
				return $obj;
			return false;
		}

		protected function _find($params)
		{
			if(!parent::_find($params))
				return false;
			$this->dbobj();
			return true;
		}

		public function refresh()
		{
			// this is used when freshly initializing a DBObjectML
			if($this->obj)
				$this->obj->refresh();
			return parent::refresh();
		}

		public function store()
		{
			if(isset($this->data[$this->primary]) && $this->data[$this->primary])
				return $this->update();
			else
				return $this->insert();
		}

		public function update()
		{
			DBObject::db_start_transaction($this->db_connection_id);
			if(parent::update()===false||!$this->obj->store()) {
				DBObject::db_rollback($this->db_connection_id);
				return false;
			}
			DBObject::db_commit($this->db_connection_id);
			return true;
		}

		public function insert($force_primary = false)
		{
			DBObject::db_start_transaction($this->db_connection_id);
			if(parent::insert($force_primary)===false) {
				DBObject::db_rollback($this->db_connection_id);
				return false;
			}
			$this->obj->unset_primary();
			$this->obj->{$this->primary} = $this->id();
			if($this->obj->insert()===false) {
				DBObject::db_rollback($this->db_connection_id);
				return false;
			}
			DBObject::db_commit($this->db_connection_id);
			return true;
		}

		public function delete()
		{
			DBObject::db_start_transaction($this->db_connection_id);
			if($this->obj->delete()===false || parent::delete()===false) {
				DBObject::db_rollback($this->db_connection_id);
				return false;
			}
			DBObject::db_commit($this->db_connection_id);
			return true;
		}

		/**
		 * the returned array has the following structure:
		 *
		 * if $language is null:
		 * array(
		 * 	'news_id' => ...,
		 * 	'news_author' => ...,
		 * 	'translations' => array(
		 * 		1 => array(
		 * 			'news_content_id' => ...,
		 * 			'news_content_language_id' => 1,
		 * 			'news_content_title' => ...,
		 * 			'news_content_news_id' => ...,
		 * 			...
		 * 		),
		 * 		2 => array(
		 * 			...
		 * 		)
		 * 	)
		 * )
		 *
		 * otherwise:
		 * array(
		 * 	'news_id' => ...,
		 * 	'news_author' => ...,
		 * 	'news_content_id' => ...,
		 * 	'news_content_language_id' => ...,
		 * 	'news_content_title' => ...
		 * )
		 */
		public function data()
		{
			if($this->language == LANGUAGE_ALL)
				return array_merge(parent::data(),
					array('translations' => $this->dbobj()->data()));
			else
				return array_merge(parent::data(), $this->dbobj()->data());
		}

		public function set_data($data)
		{
			$p = $this->content_dbobj->_prefix();
			$lkey = $p.'language_id';
			if(isset($data['translations'])) {
				$this->language = LANGUAGE_ALL;
				$translations = $data['translations'];
				unset($data['translations']);
				parent::set_data($data);
				$dbobj =& $this->dbobj();
				foreach($translations as &$t) {
					$lid = $t[$lkey];
					if(isset($dbobj[$lid]))
						$dbobj[$lid]->set_data($t);
					else
						$dbobj->add($t);
				}
				return;
			}

			if(!isset($data[$lkey])) {
				parent::set_data($data);
				return;
			}

			$this->language = $data[$lkey];
			$dbobj = $this->dbobj();
			foreach($data as $k => $v) {
				if(strpos($k, $p)===0)
					$dbobj->set($k, $v);
				else
					$this->set($k, $v);
			}
		}

		public function __get($var)
		{
			if($val = $this->get($this->name($var)))
				return $val;
			return $this->dbobj()->$var;
		}

		public function __set($var, $value)
		{
			$dbo = $this->dbobj();
			$fields = $dbo->field_list();
			if(isset($fields[$name = $dbo->name($var)]))
				return $dbo->set($name, $value);
			return $this->set($this->name($var), $value);
		}

		public function get($var)
		{
			if($var==$this->primary) {
				if(isset($this->data[$this->primary]))
					return $this->data[$this->primary];
				return null;
			}
			$dbo = $this->dbobj();
			$fields = $dbo->field_list();
			if(isset($fields[$var]))
				return $dbo->get($var);

			return parent::get($var);
		}

		public function set($var, $value)
		{
			if($var==$this->primary)
				return ($this->data[$this->primary] = $value);
			$dbo = $this->dbobj();
			$fields = $dbo->field_list();
			if(isset($fields[$var]))
				return $dbo->set($var, $value);
			else
				return $this->data[$var] = $value;
		}

		public function &_fulltext_fields()
		{
			if(!isset(DBObject::$_fulltext_fields[$this->db_connection_id]
					[$this->class])) {
				$mine = parent::_fulltext_fields();
				DBObject::$_fulltext_fields[$this->db_connection_id]
					[$this->class] =
					array_merge($mine,
						$this->content_dbobj->_fulltext_fields());
			}

			return DBObject::$_fulltext_fields[$this->db_connection_id][$this->class];

		}

		public function _select_sql($joins)
		{
			$tmp = $this->content_dbobj;
			$lang_clause = '';
			if(($lang_id = $this->language())!=LANGUAGE_ALL)
				$lang_clause = ' AND '.$tmp->name('language_id').'='.$lang_id;
			return 'SELECT * FROM '.$this->table.' LEFT JOIN '.$tmp->table()
				.' ON '.$this->table.'.'.$this->primary.'='
					.$tmp->table().'.'.$tmp->name($this->primary)
				.$joins.' WHERE 1'.$lang_clause;
		}
	}

?>
