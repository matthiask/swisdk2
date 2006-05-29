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
		
		public function create_field($field, $title = null)
		{
			$dbobj = $this->dbobj();
			if($title === null)
				$title = $this->pretty_title($field, $dbobj);
			$fields = $dbobj->field_list();
			$relations = $dbobj->relations();

			if(isset($relations[$fname=$field])
					||isset($relations[$fname=$dbobj->name($field)])) {
				switch($relations[$fname]['type']) {
					case DB_REL_SINGLE:
						$this->create_rel_single($fname, $title,
							$relations[$fname]['class']);
						break;
					case DB_REL_MANYTOMANY:
						$this->create_rel_many($fname, $title,
							$relations[$fname]['class']);
						break;
				}
			} else if(isset($fields[$fname=$field])
					||isset($fields[$fname=$dbobj->name($field)])) {
				$finfo = $fields[$fname];
				if(strpos($fname,'dttm')!==false) {
					$this->create_date($fname, $title);
				} else if(strpos($finfo['Type'], 'text')!==false) {
					$this->create_textarea($fname, $title);
				} else if($finfo['Type']=='tinyint(1)') {
					$this->create_bool($fname, $title);
				} else {
					$this->create_text($fname, $title);
				}
			}
		}

		public function pretty_title($field, &$dbobj)
		{
			return ucwords(str_replace('_', ' ',
				preg_replace('/^('.$dbobj->_prefix()
					.')?(.*?)(_id|_dttm)?$/', '\2', $field)));
		}

		abstract public function dbobj();
		abstract public function create_rel_single($fname, $title, $class);
		abstract public function create_rel_many($fname, $title, $class);
		abstract public function create_date($fname, $title);
		abstract public function create_textarea($fname, $title);
		abstract public function create_bool($fname, $title);
		abstract public function create_text($fname, $title);
	}

	class FormBuilder extends BuilderBase {
		public function build(&$form)
		{
			if($form instanceof FormML)
				return $this->build_ml($form);
			else
				return $this->build_simple($form);
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
			$this->form->add($fname, $obj, $title);
		}

		public function create_rel_many($fname, $title, $class)
		{
			$obj = new Multiselect();
			$dc = DBOContainer::find($class);
			$items = array();
			foreach($dc as $o) {
				$items[$o->id()] = $o->title();
			}
			$obj->set_items($items);
			$this->form->add($fname, $obj, $title);
		}

		public function create_date($fname, $title)
		{
			$this->form->add($fname, new DateInput(), $title);
		}

		public function create_textarea($fname, $title)
		{
			$this->form->add($fname, new Textarea(), $title);
		}

		public function create_bool($fname, $title)
		{
			$this->form->add($fname, new CheckboxInput(), $title);
		}

		public function create_text($fname, $title)
		{
			$this->form->add($fname, new TextInput(), $title);
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
				.'(creation_dttm|password)$/';
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
				.'(id|creation_dttm|password|language_id|'.$primary.')$/';
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

		public function create_rel_many($fname, $title, $class)
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

		public function create_text($fname, $title)
		{
			$this->tv->append_column(
				new TextTableViewColumn($title, $fname, 40));
		}
	}

?>
