<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Form
	 *
	 * Object-oriented form building package
	 *
	 * Form strongly depends on DBObject for its inner workings (you don't
	 * necessarily need a database table for every form, though!)
	 */

	require_once MODULE_ROOT . 'inc.data.php';
	require_once MODULE_ROOT . 'inc.layout.php';

	define('FORMRENDERER_VISIT_DEFAULT', 0);
	define('FORMRENDERER_VISIT_START', 1);
	define('FORMRENDERER_VISIT_END', 2);

	class Form implements Iterator {

		/**
		 * array of formboxes (always at least one)
		 */
		protected $boxes = array();

		/**
		 * main form title
		 */
		protected $title;

		/**
		 * form id. Should be unique for the whole page
		 */
		protected $form_id;

		/**
		 * should the FormItem's names be mangled so that they are _really_
		 * unique (f.e. edit multiple records of the same type on one page)
		 */
		protected $unique = false;

		public function __construct($dbobj=null)
		{
			if($dbobj)
				$this->bind($dbobj);
		}

		public function title() { return $this->title; }
		public function set_title($title=null) { $this->title = $title; }
		public function enable_unique() { $this->unique = true; }

		public function id()
		{
			if(!$this->form_id)
				$this->generate_form_id();
			return $this->form_id;
		}

		/**
		 * generate an id for this form
		 *
		 * the id is used to track which form has been submitted if there
		 * were multiple forms on one page. See also is_valid()
		 */
		public function generate_form_id()
		{
			$this->form_id = Form::to_form_id($this->dbobj());
		}

		/**
		 * take a DBObject and return a form id
		 */
		public static function to_form_id($dbo, $id=0)
		{
			$id = $dbo->id();
			return '__swisdk_form_'.$dbo->table().'_'.($id?$id:0);
		}

		/**
		 * return the FormBox with the given ID (this ID has no further
		 * meaning)
		 *
		 * This function should also be used to create FormBoxes (FormML
		 * returns FormMLBox)
		 */
		public function box($id=0)
		{
			if(!$id && count($this->boxes))
				return reset($this->boxes);

			if(!isset($this->boxes[$id])) {
				$this->boxes[$id] = new FormBox();
				$this->boxes[$id]->set_name($id);
				if($this->unique)
					$this->boxes[$id]->enable_unique();
				if($this->dbobj_tmp) {
					$this->boxes[$id]->bind($this->dbobj_tmp);
					$this->dbobj_tmp = null;
				} else
					if($obj = $this->dbobj())
						$this->boxes[$id]->bind($obj);
			}
			return $this->boxes[$id];
		}

		/**
		 * easy form usage:
		 *
		 * forward these calls to the default FormBox
		 */
		public function dbobj()
		{
			return $this->box()->dbobj();
		}

		protected $dbobj_tmp;

		public function bind($dbobj)
		{
			if(!count($this->boxes))
				$this->dbobj_tmp = $dbobj;
			else
				$this->box()->bind($dbobj);
		}

		/**
		 * refresh all FormItem's values (read values from DBObject)
		 */
		public function refresh()
		{
			foreach($this->boxes as &$box)
				$box->refresh();
		}

		public function add()
		{
			$args = func_get_args();
			return call_user_func_array(array($this->box(), 'add'), $args);
		}

		public function add_auto()
		{
			$args = func_get_args();
			return call_user_func_array(array($this->box(), 'add_auto'), $args);
		}

		public function add_rule()
		{
			$args = func_get_args();
			return call_user_func_array(array($this->box(), 'add_rule'), $args);
		}

		public function rules()
		{
			return $this->box()->rules();
		}

		/**
		 * search and return a FormItem
		 */
		public function item($name)
		{
			foreach($this->boxes as &$box)
				if($item =& $box->item($name))
					return $item;
			return null;
		}

		/**
		 * @return the Form html
		 */
		public function html($arg = 'TableFormRenderer')
		{
			$renderer = null;
			if($arg instanceof FormRenderer)
				$renderer = $arg;
			else if(class_exists($arg))
				$renderer = new $arg;
			else
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Invalid renderer specification: %s'), $arg)));
			$this->accept($renderer);

			return $renderer->html();
		}

		/**
		 * accept the FormRenderer
		 */
		public function accept($renderer)
		{
			//
			// this guard entry serves two purposes
			// 1. When there are more than one form on one page, this entry
			//    can be used to tell, which form was submitted.
			// 2. The ID should be unique and not predictable. This is used
			//    as a safeguard against CSRF attacks
			//
			$this->add(new HiddenInput('__guard_'.$this->id()))->set_value(guardToken());

			$renderer->visit($this, FORMRENDERER_VISIT_START);
			foreach($this->boxes as &$box)
				$box->accept($renderer);
			$renderer->visit($this, FORMRENDERER_VISIT_END);
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			// has this form been submitted (or was it another form on the same page)
			if(!isset($_POST['__guard_'.$this->id()]))
				return false;
			if($_POST['__guard_'.$this->id()]!=guardToken()) {
				$this->box()->add_message(dgettext('swisdk', 'Could not validate form submission'));
				return false;
			}

			$valid = true;
			// loop over all FormBox es
			foreach($this->boxes as &$box)
				if(!$box->is_valid())
					$valid = false;
			return $valid;
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */

		public function rewind()	{ return reset($this->boxes); }
		public function current()	{ return current($this->boxes); }
		public function key()		{ return key($this->boxes); }
		public function next()		{ return next($this->boxes); }
		public function valid()		{ return $this->current() !== false; }
	}

	/**
	 * The FormBox is the basic grouping block of a Form
	 *
	 * There may be 1-n FormBoxes in one Form
	 */
	class FormBox implements Iterator, ArrayAccess {

		/**
		 * the title of this Box
		 */
		protected $title;

		/**
		 * holds all FormItems and FormBoxes that are part of this
		 * FormBox
		 */
		protected $items = array();

		/**
		 * holds references to all FormBoxes which are stored in the
		 * $items array
		 */
		protected $boxrefs = array();

		/**
		 * holds the DBObject bound to this FormBox
		 */
		protected $dbobj;

		/**
		 * same as comment form Form::$unique
		 */
		protected $unique = false;

		/**
		 * validation message
		 */
		protected $message;

		/**
		 * Form and FormBox rules
		 */
		protected $rules = array();

		/**
		 * FormBox Id
		 */
		protected $formbox_id;

		/**
		 * FormBox name
		 *
		 * this may be used to keep track of different FormBoxes
		 * It is currently (060605) only used for the DBTableView
		 * FormRenderer
		 */
		protected $name;
		public function name() { return $this->name; }
		public function set_name($name) { $this->name = $name; }

		/**
		 * @param $dbobj: the DBObject bound to the Form
		 */
		public function __construct($dbobj=null)
		{
			if($dbobj)
				$this->bind($dbobj);
		}

		public function enable_unique() { $this->unique = true; }
		public function set_title($title=null) { $this->title = $title; }
		public function title() { return $this->title; }

		/**
		 * @param dbobj: a DBObject
		 */
		public function bind($dbobj)
		{
			$this->formbox_id = Form::to_form_id($dbobj).'_formbox_'.$this->name;
			$this->dbobj = $dbobj;
		}

		/**
		 * @return the bound DBObject
		 */
		public function &dbobj()
		{
			return $this->dbobj;
		}

		public function id()
		{
			return $this->formbox_id;
		}

		/**
		 * add a validation message to the FormBox (will be displayed after
		 * everything else)
		 */
		public function message()		{ return $this->message; }
		public function set_message($message)	{ $this->message = $message; }
		public function add_message($message)
		{
			if($this->message)
				$this->message .= "\n<br />".$message;
			else
				$this->message = $message;
		}

		public function add_rule(FormRule $rule)
		{
			$this->rules[] = $rule;
			return $this;
		}

		public function &rules()
		{
			return $this->rules;
		}

		/**
		 * refresh all FormItem's values (read values from DBObject)
		 */
		public function refresh()
		{
			foreach($this->items as &$item)
				$item->refresh($this->dbobj());
		}

		/**
		 * add a new element to this FormBox
		 *
		 * add(field) // default FormItem is TextInput
		 * add(field, FormItem)
		 * add(field, FormItem, title)
		 * add(relspec, title)
		 * add(FormItem)
		 * add(FormBox)
		 *
		 * returns the newly added FormItem
		 */
		public function add()
		{
			$args = func_get_args();

			if(count($args)<2) {
				if($args[0] instanceof FormBox) {
					$this->items[] = $args[0];
					$this->boxrefs[] = $args[0];
					return $args[0];
				} else if($args[0] instanceof FormItem) {
					return $this->add_initialized_obj($args[0]);
				} else {
					return $this->add_obj($args[0],
						new TextInput());
				}
			} else if($args[1] instanceof FormItem) {
				return call_user_func_array(
					array(&$this, 'add_obj'),
					$args);
			} else {
				return call_user_func_array(
					array(&$this, 'add_dbobj_ref'),
					$args);
			}
		}

		/**
		 * add an element to the form
		 *
		 * Usage example (these might "just do the right thing"):
		 *
		 * $form->add_auto('start_dttm', 'Publication date');
		 * $form->add_auto('title');
		 *
		 * NOTE! The bound DBObject MUST point to a valid table if
		 * you want to use this function.
		 */
		public function add_auto($field, $title=null)
		{
			require_once MODULE_ROOT.'inc.builder.php';
			static $builder = null;
			if($builder===null)
				$builder = new FormBuilder();
			if(is_array($field)) {
				foreach($field as $f)
					$builder->create_auto($this, $f, null);
			} else
				return $builder->create_auto($this, $field, $title);
		}

		/**
		 * handle add(FormItem) case
		 */
		protected function add_initialized_obj($obj)
		{
			$obj->set_preinitialized();
			$obj->set_form_box($this);
			if($this->unique)
				$obj->enable_unique();
			$obj->init_value($this->dbobj());
			if($obj->name())
				$this->items[$obj->name()] =& $obj;
			else
				$this->items[] =& $obj;
			return $obj;
		}

		/**
		 * handle add(field, FormItem) and add(field, FormItem, title) cases
		 */
		protected function add_obj($field, $obj, $title=null)
		{
			$dbobj = $this->dbobj();

			if($title===null)
				$title = $dbobj->pretty($field);

			$obj->set_title($title);
			$obj->set_name($field);
			$obj->set_form_box($this);
			if($this->unique)
				$obj->enable_unique();
			$obj->init_value($dbobj);

			$this->items[$field] = $obj;

			return $obj;
		}

		/**
		 * handle add(relspec, title) case
		 */
		protected function add_dbobj_ref($relspec, $title=null)
		{
			$relations = $this->dbobj()->relations();
			if(isset($relations[$relspec])) {
				switch($relations[$relspec]['type']) {
					case DB_REL_SINGLE:
						$f = $this->add_obj($title, new DropdownInput(),
							$relations[$relspec]['field']);
						$f->set_items(DBOContainer::find(
							$relations[$relspec]['class']));
						break;
					case DB_REL_N_TO_M:
						$f = $this->add_obj($title, new Multiselect(),
							$relations[$relspec]['field']);
						$f->set_items(DBOContainer::find(
							$relations[$relspec]['class']));
						break;
					case DB_REL_MANY:
						SwisdkError::handle(new BasicSwisdkError(sprintf(
							dgettext('swisdk', 'Cannot edit relation of type DB_REL_MANY! relspec: %s'),
							$relspec)));
					default:
						SwisdkError::handle(new BasicSwisdkError(sprintf(
							dgettext('swisdk', 'Oops. Unknown relation type %s'), $relspec)));
				}
			}
		}

		/**
		 * @return the FormBox html
		 *
		 * NOTE! This is not used when calling Form::html()
		 */
		public function html($arg = 'TableFormRenderer')
		{
			$renderer = null;
			if($arg instanceof FormRenderer)
				$renderer = $arg;
			else if(class_exists($arg))
				$renderer = new $arg;
			else
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Invalid renderer specification: %s'), $arg)));
			$this->accept($renderer);

			return $renderer->html();
		}

		/**
		 * accept the FormRenderer
		 */
		public function accept($renderer)
		{
			$renderer->visit($this, FORMRENDERER_VISIT_START);
			foreach($this->items as &$item)
				$item->accept($renderer);
			$renderer->visit($this, FORMRENDERER_VISIT_END);
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			$valid = true;
			// loop over FormRules
			foreach($this->rules as &$rule)
				if(!$rule->is_valid($this))
					$valid = false;
			// loop over all Items
			foreach($this->items as &$item)
				if(!$item->is_valid())
					$valid = false;
			return $valid;
		}

		/**
		 * @return the formitem with name $name
		 */
		public function item($name)
		{
			if(isset($this->items[$name]))
				return $this->items[$name];
			foreach($this->boxrefs as &$box)
				if($item =& $box->item($name))
					return $item;
			return null;
		}


		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */

		public function rewind()	{ return reset($this->items); }
		public function current()	{ return current($this->items); }
		public function key()		{ return key($this->items); }
		public function next()		{ return next($this->items); }
		public function valid()		{ return $this->current() !== false; }

		/**
		 * ArrayAccess implementation (see PHP SPL)
		 */

		public function offsetExists($offset) { return isset($this->items[$offset]); }
		public function offsetGet($offset) { return $this->items[$offset]; }
		public function offsetSet($offset, $value)
		{
			if($offset===null)
				$this->items[] = $value;
			else
				$this->items[$offset] = $value;
		}
		public function offsetUnset($offset) { unset($this->items[$offset]); }
	}

	/**
	 * Multi-language forms are implemented by binding the parent DBObject and the
	 * translation DBObject to two FormBoxes, which are both part of the main
	 * Form
	 */

	class FormML extends Form {
		public function box($id=null)
		{
			if(!$id && count($this->boxes))
				return reset($this->boxes);

			if(!isset($this->boxes[$id])) {
				$this->boxes[$id] = new FormMLBox();
				$this->boxes[$id]->set_name($id);
				if($this->unique)
					$this->boxes[$id]->enable_unique();
				if($this->dbobj_tmp) {
					$this->boxes[$id]->bind($this->dbobj_tmp);
					$this->dbobj_tmp = null;
				} else
					if($obj = $this->dbobj())
						$this->boxes[$id]->bind($obj);
			}
			return $this->boxes[$id];
		}

	}

	class FormMLBox extends FormBox {
		// this is only a type marker for the FormBuilder
	}

	require_once MODULE_ROOT.'inc.form.items.php';
	require_once MODULE_ROOT.'inc.form.validation.php';
	require_once MODULE_ROOT.'inc.form.renderer.php';
	require_once MODULE_ROOT.'inc.form.behavior.php';

?>
