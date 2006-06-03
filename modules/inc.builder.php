<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>,
	*		Moritz Zumbhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.tableview.php';

	abstract class BuilderBase {
		
		/**
		 * this function tries to handle everything that gets thrown at it
		 *
		 * it first searches the relations and then the field list for
		 * matches.
		 */
		public function create_field($field, $title = null)
		{
			$dbobj = $this->dbobj();
			if($title === null)
				$title = $this->pretty_title($field, $dbobj);
			$relations = $dbobj->relations();

			if(isset($relations[$fname=$field])
					||isset($relations[$fname=$dbobj->name($field)])) {
				switch($relations[$fname]['type']) {
					case DB_REL_SINGLE:
						return $this->create_rel_single($fname, $title,
							$relations[$fname]['class']);
					case DB_REL_MANYTOMANY:
						return $this->create_rel_manytomany($fname, $title,
							$relations[$fname]['class']);
					default:
						SwisdkError::handle(new FatalError(
							'Cannot handle'));
				}
			}

			$fields = $dbobj->field_list();

			if(isset($fields[$fname=$field])
					||isset($fields[$fname=$dbobj->name($field)])) {
				$finfo = $fields[$fname];

				// set default value if it was set in database
				if($dbobj->get($fname)===null && ($d = $fields[$fname]['Default']))
					$dbobj->set($fname, $d);

				if(strpos($fname,'dttm')!==false) {
					return $this->create_date($fname, $title);
				} else if(strpos($finfo['Type'], 'text')!==false) {
					return $this->create_textarea($fname, $title);
				} else if($finfo['Type']=='tinyint(1)') {
					return $this->create_bool($fname, $title);
				} else if(strpos($finfo['Type'], 'enum')===0) {
					return $this->create_enum($fname, $title,
						$this->_extract_enum_values($finfo['Type']));
				} else {
					return $this->create_text($fname, $title);
				}
			}
		}

		public function pretty_title($field, &$dbobj)
		{
			return ucwords(str_replace('_', ' ',
				preg_replace('/^('.$dbobj->_prefix()
					.')?(.*?)(_id|_dttm)?$/', '\2', $field)));
		}

		/**
		 * @return a DBObject instance of the correct class
		 */
		abstract public function dbobj();

		/**
		 * create a FormItem/Column for a relation of type has_a or
		 * belongs_to
		 */
		abstract public function create_rel_single($fname, $title, $class);

		/**
		 * create a FormItem/Column for a relation of type n-to-m
		 *
		 * XXX this does not work correctly for TableViews right now
		 */
		abstract public function create_rel_manytomany($fname, $title, $class);

		/**
		 * create a date widget
		 */
		abstract public function create_date($fname, $title);

		/**
		 * create a textarea widget (f.e. length-limited for TableView)
		 */
		abstract public function create_textarea($fname, $title);

		/**
		 * checkbox or true/false column
		 */
		abstract public function create_bool($fname, $title);

		/**
		 * special handling of enum fields
		 */
		abstract public function create_enum($fname, $title, $values);

		/**
		 * everything else
		 */
		abstract public function create_text($fname, $title);

		/**
		 * helper which parses the MySQL enum field type description
		 * and returns an array of all enums
		 *
		 * enum('a','b','c') => array('a'=>'a', 'b'=>'b', 'c'=>'c')
		 */
		protected function _extract_enum_values($string)
		{
			$array = explode('\',\'', substr($string, 6,
				strlen($string)-8));
			return array_combine($array, $array);
		}
	}

	/**
	 * TODO use default value from DB when constructing form? (not only for enums)
	 */

	class FormBuilder extends BuilderBase {
		public function build(&$form)
		{
			if(($form instanceof FormML) || ($form instanceof FormMLBox))
				return $this->build_ml($form);
			else
				return $this->build_simple($form);
		}

		/**
		 * this is used by FormBox::add_auto
		 */
		public function create_auto(&$form, $field, $title = null)
		{
			$this->form = $form;
			return $this->create_field($field, $title);
		}

		public function build_simple(&$form, $submitbtn = true)
		{
			$this->form = $form;
			$dbobj = $form->dbobj();
			$fields = array_keys($dbobj->field_list());
			$ninc_regex = '/^'.$dbobj->_prefix()
				.'(id|creation_dttm)$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname);

			$relations = $dbobj->relations();
			foreach($relations as $key => &$data) {
				if($data['type']==DB_REL_MANYTOMANY)
					$this->create_field($key);
			}

			// FIXME do not autogenerate fields which were
			// created inside form_hook
			$this->form_hook($form);
			if($submitbtn)
				$this->form->add(new SubmitButton());
		}

		public function build_ml(&$form)
		{
			$this->build_simple($form, false);

			$dbobj =& $form->dbobj();
			$box = null;
			// FIXME this is hacky. FormBox should have a box() method too?:w
			if($form instanceof FormBox)
				$box = $form->add(new FormMLBox());
			else
				$box =& $form->box($dbobj->language());
			$dbobjml =& $dbobj->dbobj();
			$box->bind($dbobjml);

			// work on the language form box (don't need to keep a
			// reference to the main form around)
			$this->form = $box;

			$fields = array_keys($dbobjml->field_list());

			// maybe this should be configurable? Someone might want to
			// change the language of some entry, or might want to reparent
			// the translation
			$ninc_regex = '/^'.$dbobjml->_prefix()
				.'(id|creation_dttm|language_id|'
				.$dbobj->primary().')$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname);

			// FIXME do not autogenerate fields which were
			// created inside form_hook
			$this->form_hook_ml($form);
			$this->form->add(new SubmitButton());
		}

		public function form_hook(&$form)
		{
			// customize that
			//$this->form->add('item_type_id', new TextInput());
		}

		public function form_hook_ml(&$form)
		{
			// customize that
		}

		public function dbobj()
		{
			return $this->form->dbobj();
		}

		public function create_rel_single($fname, $title, $class)
		{
			$obj = new DropdownInput();
			$dc = DBOContainer::find($class);
			$choices = array();
			foreach($dc as $o) {
				$items[$o->id()] = $o->title();
			}
			$obj->set_items($items);
			return $this->form->add($fname, $obj, $title);
		}

		/**
		 * you could also display a list of checkboxes here...
		 */
		public function create_rel_manytomany($fname, $title, $class)
		{
			$obj = new Multiselect();
			$dc = DBOContainer::find($class);
			$items = array();
			foreach($dc as $o) {
				$items[$o->id()] = $o->title();
			}
			$obj->set_items($items);
			return $this->form->add($fname, $obj, $title);
		}

		public function create_date($fname, $title)
		{
			return $this->form->add($fname, new DateInput(), $title);
		}

		public function create_textarea($fname, $title)
		{
			return $this->form->add($fname, new Textarea(), $title);
		}

		public function create_bool($fname, $title)
		{
			return $this->form->add($fname, new CheckboxInput(), $title);
		}

		public function create_enum($fname, $title, $values)
		{
			$obj = new DropdownInput();
			$obj->set_items($values);
			return $this->form->add($fname, $obj, $title);
		}

		public function create_text($fname, $title)
		{
			return $this->form->add($fname, new TextInput(), $title);
		}
	}

	class TableViewBuilder extends BuilderBase {
		protected $tv;
		protected $dbobj;

		public function build(&$tableview)
		{
			$this->tv = $tableview;
			$this->dbobj = $tableview->dbobj()->dbobj();

			if($tableview->dbobj()->dbobj() instanceof DBObjectML)
				return $this->build_ml();
			else
				return $this->build_simple();
		}

		public function build_simple($finalize = true)
		{
			$dbobj = $this->dbobj();
			$fields = array_keys($dbobj->field_list());
			$ninc_regex = '/^'.$dbobj->_prefix()
				.'(password)$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname, null);

			$relations = $dbobj->relations();
			foreach($relations as $key => &$data) {
				if($data['type']==DB_REL_MANYTOMANY)
					$this->create_field($key, null);
			}

			// FIXME do not autogenerate fields which were
			// created inside form_hook
			$this->tableview_hook($form);
			if($finalize)
				$this->tv->append_column(new CmdsTableViewColumn(
					Swisdk::config_value('runtime.controller.url'),
					$this->tv->dbobj()->dbobj()->primary()));
		}

		public function build_ml()
		{
			$this->build_simple(false);

			$primary = $this->dbobj->primary();
			$this->dbobj = $this->dbobj->dbobj();
			$fields = array_keys($this->dbobj->field_list());
			$ninc_regex = '/^'.$this->dbobj->_prefix()
				.'(id|password|language_id|'.$primary.')$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname);

			$relations = $this->dbobj->relations();
			foreach($relations as $key => &$data) {
				if($data['type']==DB_REL_MANYTOMANY)
					$this->create_field($key, 'blah');
			}

			// FIXME do not autogenerate fields which were
			// created inside form_hook
			$this->tableview_hook_ml($form);
			$this->tv->append_column(new CmdsTableViewColumn(
				Swisdk::config_value('runtime.controller.url'),
				$primary));
		}

		public function tableview_hook(&$form)
		{
			// customize that
			//$this->form->add('item_type_id', new TextInput());
		}

		public function tableview_hook_ml(&$form)
		{
			// customize that
		}

		public function dbobj()
		{
			return $this->dbobj;
		}

		public function create_rel_single($fname, $title, $class)
		{
			$this->tv->append_column(new DBTableViewColumn(
				$title, $fname, $class));
		}

		public function create_rel_manytomany($fname, $title, $class)
		{
			$this->tv->append_column(new DBTableViewColumn(
				$title, $fname, $class));
		}

		public function create_date($fname, $title)
		{
			$this->tv->append_column(
				new DateTableViewColumn($title, $fname));
		}

		public function create_textarea($fname, $title)
		{
			$this->tv->append_column(
				new TextTableViewColumn($title, $fname, 40));
		}

		public function create_bool($fname, $title)
		{
			$this->tv->append_column(
				new BoolTableViewColumn($title, $fname));
		}

		public function create_enum($fname, $title, $values)
		{
			$this->tv->append_column(
				new EnumTableViewColumn($title, $fname, $values));
		}

		public function create_text($fname, $title)
		{
			$this->tv->append_column(
				new TextTableViewColumn($title, $fname, 40));
		}
	}

?>
