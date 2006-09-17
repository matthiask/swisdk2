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
					_('Cannot assign language to DBObjectML in LANGUAGE_ALL mode')));
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
				if($this->language == LANGUAGE_ALL) {
					if($id = $this->id())
						$this->obj = DBOContainer::find($this->tclass, array(
							$this->primary.'=' => $id,
							':index' => 'language_id'));
					else
						$this->obj = DBOContainer::create($this->tclass);
				} else {
					$language = $this->language;
					if($language == LANGUAGE_DEFAULT)
						$language = Swisdk::language();

					if($id = $this->id())
						$this->obj = DBObject::find($this->tclass, array(
							$this->primary.'=' => $id,
							'language_id=' => $language));
					if(!$this->obj) {
						$this->obj = DBObject::create($this->tclass);
						$this->obj->language_id = $this->language;
					}
				}
				$this->obj->set_db_connection($this->db_connection_id);
			}
			return $this->obj;
		}

		protected function _setup_dbvars()
		{
			parent::_setup_dbvars();
			if($this->tclass===null)
				$this->tclass = $this->class.'Content';
			DBObject::has_many($this->class, $this->tclass);
			DBObject::has_a($this->tclass, 'Language');
		}

		public static function create($class, $language = LANGUAGE_DEFAULT)
		{
			if(class_exists($class))
				return new $class();

			$obj = new DBObjectML(false);
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
				$obj->$k = $v;
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
			// XXX have to think about this a bit...
			// this is used when freshly initializing a DBObjectML
			if($this->obj)
				$this->obj->refresh();
			return parent::refresh();
		}

		public function store()
		{
			if(!$this->dirty()&&!$this->obj->dirty())
				return false;
			if(isset($this->data[$this->primary]) && $this->data[$this->primary])
				return $this->update();
			else
				return $this->insert();
		}

		public function update()
		{
			DBObject::db_start_transaction($this->db_connection_id);
			if(parent::update()===false||!$this->obj->update()) {
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
			$p = DBObject::create($this->tclass)->_prefix();
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
			$dbobj =& $this->dbobj();
			foreach($data as $k => $v) {
				if(strpos($k, $p)===0)
					$dbobj->set($k, $v);
				else
					$this->set($k, $v);
			}
		}

		public function __get($var)
		{
			if($var=='id') {
				if(isset($this->data[$this->primary]))
					return $this->data[$this->primary];
				return null;
			}
			$dbo = $this->dbobj();
			$fields = $dbo->field_list();
			if(isset($fields[$name = $dbo->name($var)]))
				return $dbo->get($name);
			if(isset($this->data[$name = $this->name($var)]))
				return $this->data[$name];
			return null;
		}

		public function __set($var, $value)
		{
			if($var=='id')
				return ($this->data[$this->primary] = $value);
			$dbo = $this->dbobj();
			$fields = $dbo->field_list();
			if(isset($fields[$name = $dbo->name($var)]))
				return $dbo->set($name, $value);
			else
				return $this->data[$this->name($var)] = $value;
		}
	}

?>
