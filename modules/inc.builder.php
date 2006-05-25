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
			$fields = $dbobj->field_list();
			$relations = $dbobj->relations();

			if(isset($relations[$fname=$field])||isset($relations[$fname=$dbobj->name($field)])) {
				switch($relations[$fname]['type']) {
					case DB_REL_SINGLE:
						$this->create_rel_single($fname, $title, $relations[$fname]['class']);
						break;
					case DB_REL_MANY:
						$this->create_rel_many($fname, $title, $relations[$fname]['class']);
						break;
				}
			} else if(isset($fields[$fname=$field])||isset($fields[$fname=$dbobj->name($field)])) {
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
			$this->form = $form;
			$dbobj = $this->dbobj();
			$fields = array_keys($dbobj->field_list());
			$ninc_regex = '/^'.$dbobj->_prefix()
				.'(id|creation_dttm)$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname);

			// FIXME do not autogenerate fields which were
			// created inside form_hook
			$this->form_hook();
			$this->form->add(new SubmitButton());
		}

		public function form_hook()
		{
			// customize that
			//$this->form->add('item_type_id', new TextInput());
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
		protected $form_class = 'DBTableViewForm';

		public function tableview(&$tableview)
		{
			$this->tv = $tableview;
		}

		public function dbobj()
		{
			return $this->tv->dbobj()->dbobj();
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
				new TextTableViewColumn($title, $fname));
		}

		public function create_bool($fname, $title)
		{
			$this->tv->append_column(
				new BoolTableViewColumn($title, $fname));
		}

		public function create_text($fname, $title)
		{
			$this->tv->append_column(
				new TextTableViewColumn($title, $fname));
		}
	}

?>
