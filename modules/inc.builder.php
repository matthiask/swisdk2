<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>,
	*		Moritz Zumbhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.tableview.php';

	/**
	 * Form and TableView builder
	 *
	 * use informations from the database to automatically add FormItems resp.
	 * TableViewColumns
	 *
	 * NOTE! You can customize the Builders here, but it's probably easier to
	 * modify add your modifications in your own AdminModules or AdminComponents
	 *
	 * You can still use the autodetection features of the BuilderBase with
	 * Form::add_auto or TableView::append_auto
	 */

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
			$field_list = $dbobj->field_list();
			if(!isset($field_list[$field])&&isset($field_list[$tmp=$dbobj->name($field)]))
				$field = $tmp;
			if($title === null)
				$title = $dbobj->pretty($field);

			// field from main table
			switch(isset($field_list[$field])?$field_list[$field]:null) {
				case DB_FIELD_BOOL:
					return $this->create_bool($field, $title);
				case DB_FIELD_STRING:
				case DB_FIELD_INTEGER:
					return $this->create_text($field, $title);
				case DB_FIELD_LONGTEXT:
					return $this->create_textarea($field, $title);
				case DB_FIELD_DATE:
					return $this->create_date($field, $title);
				case DB_FIELD_FOREIGN_KEY|(DB_REL_SINGLE<<10):
					$relations = $dbobj->relations();
					return $this->create_rel_single($field, $title,
						$relations[$field]['foreign_class']);
			}

			// field from a related table
			$relations = $dbobj->relations();
			if(isset($relations[$field]['type'])) {
				switch($relations[$field]['type']) {
					case DB_REL_MANY:
						return $this->create_rel_many($field, $title,
							$relations[$field]['foreign_class']);
					case DB_REL_N_TO_M:
						return $this->create_rel_manytomany($field, $title,
							$relations[$field]['foreign_class']);
					case DB_REL_3WAY:
						return $this->create_rel_3way($field, $title,
							$relations[$field]['foreign_class'],
							$relations[$field]['foreign_primary']);
					case DB_REL_TAGS:
						return $this->create_rel_tags($field, $title);
				}
			}
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
		 * create a FormItem/Column for a relation of type has_many
		 */
		abstract public function create_rel_many($fname, $title, $class);

		/**
		 * create a FormItem/Column for a relation of type n-to-m
		 */
		abstract public function create_rel_manytomany($fname, $title, $class);

		/**
		 * create a FormItem/Column for a relation of type 3way
		 */
		abstract public function create_rel_3way($fname, $title, $class, $field);

		/**
		 * create a FormItem/Column for tags
		 */
		abstract public function create_rel_tags($fname, $title);

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
		 * everything else
		 */
		abstract public function create_text($fname, $title);
	}

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

		/**
		 * default builder function
		 */
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
				if($data['type']==DB_REL_N_TO_M
						||$data['type']==DB_REL_3WAY
						||$data['type']==DB_REL_TAGS)
					$this->create_field($key);
			}

			if($submitbtn)
				$this->form->box('zzz_last')->add(new SubmitButton());
		}

		/**
		 * builder function for multilanguage forms
		 */
		public function build_ml(&$form)
		{
			$this->build_simple($form, false);

			$dbobj =& $form->dbobj();
			$box = null;
			// FIXME this is hacky. FormBox should have a box() method too?
			$dbobjml = $dbobj->dbobj();
			if($dbobjml instanceof DBOContainer) {
				$languages = Swisdk::all_languages();

				foreach($languages as $lid => &$l) {
					$key = $l['language_key'];
					$box = $form->box($key);
					if(!isset($dbobjml[$lid]))
						$dbobjml[$lid] =
							DBObject::create($dbobj->_class().'Content');
					$dbo =& $dbobjml[$lid];
					$dbo->set_owner($dbobj);
					$dbo->language_id = $lid;
					$box->bind($dbo);
					$box->set_title($key);
					$this->form = $box;

					$fields = array_keys($dbo->field_list());
					$ninc_regex = '/^'.$dbo->_prefix()
						.'(id|creation_dttm|language_id|'
						.$dbobj->primary().')$/';
					foreach($fields as $fname)
						if(!preg_match($ninc_regex, $fname))
							$this->create_field($fname);
				}
			} else {
				if($form instanceof FormBox)
					$box = $form->add(new FormMLBox());
				else
					$box =& $form->box($dbobj->language());
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
			}

			$this->form->box('zzz_last')->add(new SubmitButton());
		}

		public function dbobj()
		{
			return $this->form->dbobj();
		}

		public function create_rel_single($fname, $title, $class)
		{
			$obj = new DropdownInput();
			$obj->set_items(DBOContainer::find($class));
			if(strpos($fname, '_parent_id')==strlen($fname)-10)
				$obj->add_null_item();
			return $this->form->add($fname, $obj, $title);
		}

		public function create_rel_many($fname, $title, $class)
		{
			// nothing happens, has_many is not handled
		}

		/**
		 * you could also display a list of checkboxes here...
		 */
		public function create_rel_manytomany($fname, $title, $class)
		{
			$obj = new Multiselect();
			$obj->set_items(DBOContainer::find($class));
			return $this->form->add($fname, $obj, $title);
		}

		public function create_rel_3way($fname, $title, $class, $field)
		{
			return $this->form->add($fname, new ThreewayInput(), $title);
		}

		public function create_rel_tags($fname, $title)
		{
			return $this->form->add($fname, new TagInput(), $title);
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

		/**
		 * mainly used by TableView::append_auto
		 */
		public function create_auto(&$tableview, $field, $title = null)
		{
			$this->tv = $tableview;
			$this->dbobj = $tableview->dbobj()->dbobj();
			return $this->create_field($field, $title);
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
				if($data['type']==DB_REL_N_TO_M
						||$data['type']==DB_REL_3WAY
						||$data['type']==DB_REL_TAGS)
					$this->create_field($key, null);
			}

			if($finalize)
				$this->tv->append_column(new CmdsTableViewColumn(
					$this->tv->dbobj()->dbobj()->primary(),
					Swisdk::config_value('runtime.controller.url')));
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
				if($data['type']==DB_REL_N_TO_M
						||$data['type']==DB_REL_3WAY
						||$data['type']==DB_REL_TAGS)
					$this->create_field($key);
			}

			$this->tv->append_column(new CmdsTableViewColumn($primary,
				Swisdk::config_value('runtime.controller.url')));
		}

		public function dbobj()
		{
			return $this->dbobj;
		}

		public function create_rel_single($fname, $title, $class)
		{
			$this->tv->append_column(new DBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_many($fname, $title, $class)
		{
			$this->tv->append_column(new ManyDBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_manytomany($fname, $title, $class)
		{
			$this->tv->append_column(new ManyToManyDBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_3way($fname, $title, $class, $field)
		{
			// TODO show information from choices ($field) too
			$this->tv->append_column(new ManyToManyDBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_tags($fname, $title)
		{
			$this->tv->append_column(new ManyToManyDBTableViewColumn(
				$fname, $title, 'Tag', $this->dbobj));
		}

		public function create_date($fname, $title)
		{
			$this->tv->append_column(
				new DateTableViewColumn($fname, $title));
		}

		public function create_textarea($fname, $title)
		{
			$this->tv->append_column(
				new TextTableViewColumn($fname, $title, 40));
		}

		public function create_bool($fname, $title)
		{
			$this->tv->append_column(
				new BoolTableViewColumn($fname, $title));
		}

		public function create_text($fname, $title)
		{
			$this->tv->append_column(
				new TextTableViewColumn($fname, $title, 40));
		}
	}

?>
