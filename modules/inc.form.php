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

	/**
	 * Examples
	 *
	 *
	 * minimal example
	 * ***************
	 *
	 * DBObject::belongs_to('Item', 'Project');
	 * DBObject::has_a('Item', 'ItemSeverity');
	 * DBObject::has_a('Item', 'ItemState');
	 * DBObject::has_a('Item', 'ItemItemPriority');
	 *
	 * $item = DBObject::find('Item', 42);
	 * $form = new Form();
	 * $form->bind_ref($item, true); // autogenerate form
	 * $form->set_title('Edit Item');
	 * $form->add(new SubmitButton());
	 *
	 * echo $form->html();
	 *
	 * // after form submission and validation, simply do:
	 *
	 * $item->store();
	 *
	 * or
	 *
	 * $form->dbobj()->store();
	 *
	 * The Form automatically writes its values into the bound DBObject.
	 *
	 *
	 *
	 * example without database
	 * ************************
	 *
	 * $form = new Form();
	 * $form->bind(DBObject::create('ContactForm')); // cannot autogenerate (obviously!)
	 * $form->set_title('contact form');
	 * $form->add('sender'); // default FormItem is TextInput; use it
	 * $form->add('title');
	 * $form->add('text', new Textarea());
	 * $form->add(new SubmitButton());
	 *
	 * // now, to get the values, use:
	 *
	 * $values = $form->dbobj()->data();
	 *
	 * You should now have an associative array of the form
	 * array(
	 * 	'contact_form_sender' => '...',
	 * 	'contact_form_title' => '...',
	 * 	'contact_form_text' => '...'
	 * );
	 * 
	 *
	 * 
	 * micro-example for language aware forms
	 * **************************************
	 *
	 * $form = new FormML();
	 * $form->bind(DBObjectML::find('News', 1));
	 * $form->autogenerate(); // might also pass true as second parameter to bind()
	 * $form->add(new SubmitButton());
	 * if($form->is_valid()) {
	 * 	echo 'valid!';
	 * 	$form->dbobj()->store();
	 * } else {
	 * 	echo $form->html();
	 * }
	 *
	 * DBObject-conforming tables need to be created for 'News', 'NewsContent' and
	 * 'Language' DBObjects for this snippet to work
	 */

	require_once MODULE_ROOT . 'inc.data.php';
	require_once MODULE_ROOT . 'inc.layout.php';

	/**
	 * this specialization of Layout_Grid allows the form code to add
	 * html fragments before the Grid table.
	 *
	 * This is mostly used for hidden input fields and other bookkeeping
	 * information. It might also be used for javascript etc. later on.
	 */
	class Form_Grid extends Layout_Grid {
		protected $html = '';

		public function add_html($html)
		{
			$this->html .= $html;
		}

		public function html()
		{
			return $this->html . parent::html();
		}
	}

	/**
	 * The FormBox is the basic grouping block of a Form
	 *
	 * There may be 1-n FormBoxes in one Form
	 */
	class FormBox implements Iterator {
		protected $title;
		protected $items = array();
		protected $boxrefs = array();

		/**
		 * holds the DBObject bound to this FormBox
		 */
		protected $dbobj;

		/**
		 * @param $dbobj: the DBObject bound to the Form
		 */
		public function __construct($dbobj)
		{
			$this->dbobj = $dbobj;
		}

		/**
		 * @return the bound DBObject
		 */
		public function &dbobj()
		{
			return $this->dbobj;
		}

		/**
		 * set the (optional) title of a FormBox
		 */
		public function set_title($title=null)
		{
			$this->title = $title;
		}

		/**
		 * This function has (at least) three overloads:
		 *
		 * add(Title, FormItem)
		 * add(Title, RelSpec) // See DBObject for the relation specs
		 * add(FormItem)
		 *
		 * Examples:
		 *
		 * $form->add('Title', new TextInput());
		 * $form->add('Creation', new DateInput());
		 * $form->add('Priority', 'ItemPriority');
		 * $form->add(new SubmitButton());
		 */
		public function add()
		{
			$args = func_get_args();

			if(count($args)<2) {
				if($args[0] instanceof FormBox) {
					$this->items[] = $args[0];
					$this->boxrefs[] = $args[0];
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
			$dbobj = $this->dbobj();
			$fields = $dbobj->field_list();
			$relations = $dbobj->relations();

			$obj = null;

			if(isset($relations[$fname=$field])||isset($relations[$fname=$dbobj->name($field)])) {
				switch($relations[$fname]['type']) {
					case DB_REL_SINGLE:
						$obj = new DropdownInput();
						$dc = DBOContainer::find($relations[$fname]['class']);
						$choices = array();
						foreach($dc as $o) {
							$items[$o->id()] = $o->title();
						}
						$obj->set_items($items);
						break;
					case DB_REL_MANY:
						$obj = new Multiselect();
						$dc = DBOContainer::find($relations[$fname]['class']);
						$items = array();
						foreach($dc as $o) {
							$items[$o->id()] = $o->title();
						}
						$obj->set_items($items);
						break;
				}
			} else if(isset($fields[$fname=$field])||isset($fields[$fname=$dbobj->name($field)])) {
				$finfo = $fields[$fname];
				if(strpos($fname,'dttm')!==false) {
					$obj = new DateInput();
				} else if(strpos($finfo['Type'], 'text')!==false) {
					$obj = new Textarea();
				} else if($finfo['Type']=='tinyint(1)') {
					// mysql suckage. It does not really know
					// a bool type, only tinyint(1)
					$obj = new CheckboxInput();
				} else {
					$obj = new TextInput();
				}
			}

			if($obj)
				return $this->add_obj($fname, $obj, $title);
		}

		protected function pretty_title($fname)
		{
			return ucwords(str_replace('_', ' ',
				preg_replace('/^('.$this->dbobj()->_prefix().')?(.*?)(_id|_dttm)?$/',
					'\2', $fname)));
		}

		protected function add_initialized_obj($obj)
		{
			$obj->init_value($this->dbobj());
			if($obj->name())
				$this->items[$obj->name()] =& $obj;
			else
				$this->items[] =& $obj;
			return $obj;
		}

		protected function add_obj($field, $obj, $title=null)
		{
			$dbobj = $this->dbobj();

			if($title===null)
				$title = $this->pretty_title($field);

			/*
			$field = $this->dbobj()->name(
				$obj->field_name($title));
			*/

			$obj->set_title($title);
			$obj->set_name($field);
			$obj->init_value($dbobj);

			$this->items[$field] = $obj;

			return $obj;
		}

		protected function add_dbobj_ref($relspec, $title=null)
		{
			$relations = $this->dbobj()->relations();
			if(isset($relations[$relspec])) {
				switch($relations[$relspec]['type']) {
					case DB_REL_SINGLE:
						$f = $this->add_obj($title, new DropdownInput(), $relations[$relspec]['field']);
						$dc = DBOContainer::find($relations[$relspec]['class']);
						$choices = array();
						foreach($dc as $o) {
							$items[$o->id()] = $o->title();
						}
						$f->set_items($items);
						break;
					case DB_REL_MANYTOMANY:
						$f = $this->add_obj($title, new Multiselect(), $relations[$relspec]['field']);
						$dc = DBOContainer::find($relations[$relspec]['class']);
						$items = array();
						foreach($dc as $o) {
							$items[$o->id()] = $o->title();
						}
						$f->set_items($items);
						break;
					case DB_REL_MANY:
						SwisdkError::handle(new BasicSwisdkError(
							'Cannot edit relation of type DB_REL_MANY! relspec: '.$relspec));
					default:
						SwisdkError::handle(new BasicSwisdkError(
							'Oops. Unknown relation type '.$relspec));
				}
			}
		}

		/**
		 * Use the DBObject's field list and the relations to build a Form
		 */
		protected function fname($name)
		{
			return $name;
		}

		/**
		 * Inspect the field_list of the bound DBObject and automatically
		 * build a form with most fields in the field_list
		 */
		public function autogenerate($fields=null, $ninc_regex=null)
		{
			if($fields===null)
				$fields = array_keys($this->dbobj->field_list());
			if($ninc_regex===null)
				$ninc_regex = '/^'.$this->dbobj->_prefix().'(id|creation_dttm)$/';
			foreach($fields as $fname) {
				if(!preg_match($ninc_regex, $fname))
					$this->add_auto($fname);
			}
		}

		/**
		 * @return html portion of form
		 */
		public function html()
		{
			$grid = new Form_Grid();
			$this->render($grid);
			return $grid->html();
		}

		/**
		 * render the contained FormItems and the nested FormBoxes onto
		 * the layout grid
		 */
		public function render($grid)
		{
			if($this->title)
				$grid->add_item(0, $grid->height(), $this->title, 3, 1);
			foreach($this->items as &$item) {
				// special treatment of HiddenInput fields
				if($item instanceof HiddenInput)
					$grid->add_html($item->html());
				else
					$item->render($grid);
			}
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			$valid = true;
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
	}

	class Form extends FormBox {
		public function __construct()
		{
		}

		/**
		 * @param dbobj: a DBObject
		 * @param autogenerate: automatically generate Form from relations
		 * 	and field names of the DBObject
		 */
		public function bind($dbobj, $autogenerate=false)
		{
			$this->dbobj = $dbobj;
			if($autogenerate)
				$this->autogenerate();
			$this->generate_form_id();
		}

		public function bind_ref($dbobj, $autogenerate=false)
		{
			$this->dbobj = $dbobj;
			if($autogenerate)
				$this->autogenerate();
			$this->generate_form_id();
		}

		public function id()
		{
			return $this->form_id;
		}

		/**
		 * @return the Form html
		 */
		public function html()
		{
			$id = $this->add(new HiddenInput());
			$id->set_name($this->form_id);
			$id->set_value(1);

			$html = '<form method="post" action="'.$_SERVER['REQUEST_URI'].'" name="'.$this->form_id."\">\n";
			$html .= parent::html();
			if($this->message)
				$html .= '<p>'.$this->message.'</p>';
			$html .= '</form>';
			return $html;
		}

		/**
		 * add a validation message to the form
		 */
		protected $message;
		public function message()		{ return $this->message; }
		public function set_message($message)	{ $this->message = $message; }
		public function add_message($message)
		{
			if($this->message)
				$this->message .= "\n<br />".$message;
			else
				$this->message = $message;
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			// has this form been submitted (or was it another form on the same page)
			if(!isset($_REQUEST[$this->form_id]))
				return false;

			$valid = true;
			// loop over FormRules
			foreach($this->rules as &$rule)
				if(!$rule->is_valid(&$this))
					$valid = false;
			// loop over each items own validation rules
			return parent::is_valid() && $valid;
		}

		public function add_rule(FormRule $rule)
		{
			$this->rules[] = $rule;
		}

		/**
		 * array of all FormRules
		 *
		 * Note! The FormItems do store their FormItemRules themselves
		 */
		protected $rules = array();

		protected $form_id;

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

		public static function to_form_id($tok, $id=0)
		{
			if($tok instanceof DBObject) {
				$id = $tok->id();
				return '__swisdk_form_'.$tok->table().'_'.($id?$id:0);
			}
			return '__swisdk_form_'.$tok.'_'.($id?$id:0);
		}
	}

	/**
	 * FormML and DBObjectML are even more tightly coupled to each other.
	 * For now, there is no possibility to use multilanguage forms without
	 * a backing database.
	 *
	 * FIXME: multi-language forms without DB should be possible
	 */

	/**
	 * The FormMLBox does some additional name munging to make it possible
	 * to display FormItems for the same fields for multiple languages
	 * at the same time.
	 */
	class FormMLBox extends FormBox {
		protected function fname($name)
		{
			if($this->language)
				return '__language'.$this->language.'_'.$name;
			return $name;
		}

		public $language;
	}

	class FormML extends Form {
		public function autogenerate($fields=null, $ninc_regex=null)
		{
			parent::autogenerate($fields, null);
			$dbobj = $this->dbobj->dbobj();
			if($ninc_regex===null)
				$ninc_regex = '/^'.$dbobj->_prefix().'((language_|'.$this->dbobj->_prefix().')?id|creation_dttm)$/';
			if($dbobj instanceof DBOContainer) {
				// FIXME this does not work for a new DBObjectML (the
				// DBOContainer is empty so the languages cannot be
				// enumerated)
				foreach($dbobj as &$obj) {
					$box = new FormMLBox($obj);
					$box->language = $obj->language_id;
					$box->autogenerate($fields, $ninc_regex);
					$box->set_title('Language: '.$obj->language_id);
					$this->add($box);
				}
			} else {
				$box = new FormMLBox($dbobj);
				$box->language = $dbobj->language_id;
				$box->autogenerate($fields, $ninc_regex);
				$box->set_title('Language: '.$this->dbobj->language());
				$this->add($box);
			}
		}
	}

	/**
	 * base class of all form items
	 */
	class FormItem {

		/**
		 * the name (DBObject field name)
		 */
		protected $name;

		/**
		 * user-readable title
		 */
		protected $title;

		/**
		 * message (f.e. validation errors)
		 */
		protected $message;

		/**
		 * the value (ooh!)
		 */
		protected $value;

		/**
		 * validation rule objects
		 */
		protected $rules = array();

		/**
		 * additional html attributes
		 */
		protected $attributes = array();

		/**
		 * helper for Form::add_obj()
		 *
		 * Examples for a DBObject of class 'Item':
		 *
		 * TextInput with title 'Title' will be item_title
		 * Textarea with title 'Description' will be item_description
		 * DateInput with title 'Creation' will be item_creation_dttm
		 *
		 * This function must not add the prefix (item_), because that will
		 * be added later. It should add _dttm for DateInput, however.
		 */
		public function field_name($title)	{ return strtolower($title); } 

		public function __construct($name=null)
		{
			if($name)
				$this->name = $name;
		}

		/**
		 * accessors and mutators
		 */
		public function value()			{ return $this->value; }
		public function set_value($value)	{ $this->value = $value; } 
		public function name()			{ return $this->name; }
		public function set_name($name)		{ $this->name = $name; } 
		public function title()			{ return $this->_stripit($this->title); }
		public function set_title($title)	{ $this->title = $title; } 
		public function message()		{ return $this->message; }
		public function set_message($message)	{ $this->message = $message; }
		public function add_message($message)
		{
			if($this->message)
				$this->message .= "\n<br />".$message;
			else
				$this->message = $message;
		}

		/**
		 * XXX UGLY :-(
		 *
		 * internal hack, implementation detail of MLForm that found its
		 * way into the standard form code... I hate it. But it works.
		 * And the user does not havel to care.
		 *
		 * This strips the part of the FormItem name that makes it possible
		 * to display multiple FormItems of the same fields in the same form.
		 */
		protected function _stripit($str)
		{
			return preg_replace('/__language([0-9]+)_/', '', $str);
		}

		/**
		 * get an array of html attributes
		 */
		public function attributes()
		{
			return $this->attributes;
		}

		public function set_attributes($attributes)
		{
			$this->attributes = array_merge($this->attributes, $attributes); 
		}

		/**
		 * helper function which composes a html-compatible attribute
		 * string
		 */
		protected function attribute_html()
		{
			$html = ' ';
			foreach($this->attributes as $k => $v)
				$html .= $k.'="'.htmlspecialchars($v).'" ';
			return $html;
		}

		/**
		 * the y position of this formitem (used while rendering with the
		 * default FormItem::render() and FormItem::render_*() methods)
		 */
		protected $render_y;

		public function render(&$grid)
		{
			$this->render_y = $grid->height();
			$this->render_title(&$grid);
			$this->render_field(&$grid);
			$this->render_message(&$grid);
		}

		protected function render_title(&$grid)
		{
			// put the title into the first column
			$grid->add_item(0, $this->render_y, 
				sprintf('<label for="%s">%s</label>', $this->name(), $this->title()));
		}

		protected function render_field(&$grid)
		{
			// put the form item into the second column
			$grid->add_item(1, $this->render_y, $this->field_html());
		}

		protected function render_message(&$grid)
		{
			// put the message into the third column
			$grid->add_item(2, $this->render_y, $this->message());
		}

		/**
		 * this is the function you should override if you want to use the
		 * default rendering mechanism for FormItems.
		 *
		 * This function is not abstract because I do not want to force
		 * everybody into using the default renderer (see SubmitButton)
		 */
		protected function field_html()
		{
			return '#OVERRIDE';
		}

		public function init_value($dbobj)
		{
			$name = $this->name();
			$sname = $this->_stripit($name);

			if(($val = getInput($name))!==null) {
				if(is_array($val))
					$dbobj->set($sname, $val);
				else
					$dbobj->set($sname, stripslashes($val));
			}

			$this->set_value($dbobj->get($sname));
		}

		public function add_rule(FormItemRule $rule)
		{
			$this->rules[] = $rule;
		}

		public function is_valid()
		{
			$valid = true;
			foreach($this->rules as &$rule)
				if(!$rule->is_valid(&$this))
					$valid = false;
			return $valid;
		}
	}

	abstract class SimpleInput extends FormItem {
		protected $type = '#INVALID';
		protected function field_html()
		{
			return sprintf('<input type="%s" name="%s" id="%s" value="%s" %s />',
				$this->type, $this->name(), $this->name(),
				$this->value(), $this->attribute_html());
		}
	}

	class TextInput extends SimpleInput {
		protected $type = 'text';
		protected $attributes = array('size' => 60);
	}

	/**
	 * hidden fields get special treatment (see also FormBox::html())
	 */
	class HiddenInput extends TextInput {
		protected $type = 'hidden';

		public function html()
		{
			return $this->field_html();
		}
	}

	class PasswordInput extends SimpleInput {
		protected $type = 'password';
		protected $attributes = array('size' => 60);
	}

	class CheckboxInput extends FormItem {
		protected $type = 'checkbox';

		public function init_value($dbobj)
		{
			$name = $this->name();
			$sname = $this->_stripit($name);

			if(isset($_POST['__check_'.$name])) {
				if(isset($_POST[$name]) && $_POST[$name])
					$dbobj->set($sname, 1);
				else
					$dbobj->set($sname, 0);
			}

			$this->set_value($dbobj->get($sname));
		}

		protected function field_html()
		{
			$name = $this->name();
			return sprintf('<input type="checkbox" name="%s" id="%s" %s /><input type="hidden" name="__check_'.$name.'" value="1" />',
				$this->type, $name, $name,
				($this->value()?'checked="checked" ':' ').$this->attribute_html());
		}
	}

	class Textarea extends FormItem {
		protected $attributes = array('rows' => 12, 'cols' => 60);

		protected function field_html()
		{
			$name = $this->name();
			return sprintf('<textarea name="%s" id="%s" %s>%s</textarea>',
				$name, $name, $this->attribute_html(), $this->value());
		}
	}

	class RichTextarea extends FormItem {
		protected $attributes = array('style' => 'width:800px;height:300px;');
		protected function field_html()
		{
			$name = $this->name();
			$value = $this->value();
			$attributes = $this->attribute_html();
			$html = <<<EOD
<textarea name="$name" id="$name" $attributes>$value</textarea>
<script type="text/javascript" src="/scripts/util.js"></script>
<script type="text/javascript" src="/scripts/fckeditor/fckeditor.js"></script>
<script type="text/javascript">
function load_editor_$name(){
var oFCKeditor = new FCKeditor('$name');
oFCKeditor.BasePath = '/scripts/fckeditor/';
oFCKeditor.Height = 450;
oFCKeditor.Width = 750;
oFCKeditor.ReplaceTextarea();
}
add_event(window,'load',load_editor_$name);
</script>
EOD;
			return $html;
		}
	}

	/**
	 * base class for all FormItems which offer a choice between several items
	 */
	class SelectionFormItem extends FormItem {
		public function set_items($items)
		{
			$this->items = $items;
		}

		protected $items=array();
	}

	class DropdownInput extends SelectionFormItem {
		protected function field_html()
		{
			$html = '<select name="'.$this->name().'" id="'.$this->name().'"'
				.$this->attribute_html().'>';
			$value = $this->value();
			foreach($this->items as $k => $v) {
				$html .= '<option ';
				if($value==$k)
					$html .= 'selected="selected" ';
				$html .= 'value="'.$k.'">'.$v.'</option>';
			}
			$html .= '</select>';
			return $html;
		}
	}

	class Multiselect extends SelectionFormItem {
		protected function field_html()
		{
			$html = '<select name="'.$this->name().'[]" id="'.$this->name()
				.'" multiple="multiple"'.$this->attribute_html().'>';
			$value = $this->value();
			if(!$value)
				$value = array();
			foreach($this->items as $k => $v) {
				$html .= '<option ';
				if(in_array($k,$value))
					$html .= 'selected="selected" ';
				$html .= 'value="'.$k.'">'.$v.'</option>';
			}
			$html .= '</select>';
			return $html;
		}

		public function value()
		{
			$val = parent::value();
			if(!$val)
				return array();
			return $val;
		}
	}

	/**
	 * base class for all FormItems which want to occupy a whole line (no title, no message)
	 */
	class FormBar extends FormItem {
		public function render(&$grid)
		{
			$grid->add_item(0, $grid->height(), $this->field_html(), 3, 1);
		}
	}

	class SubmitButton extends FormBar {
		protected $attributes = array('value' => 'Submit');
		protected function field_html()
		{
			return '<input type="submit" '.$this->attribute_html().'/>';
		}

		public function init_value($dbobj)
		{
			// i have no value
		}
	}

	class DateInput extends FormItem {
		/**
		 * see also comment at FormItem::field_name()
		 */
		public function field_name($title)
		{
			return strtolower($title) . '_dttm';
		}

		protected function field_html()
		{
			$html = '';
			static $js_sent = false;
			if(!$js_sent) {
				$js_sent = true;
				$html.=<<<EOD
<link rel="stylesheet" type="text/css" media="all" href="/scripts/calendar/calendar-win2k-1.css" title="win2k-cold-1" />
<script type="text/javascript" src="/scripts/calendar/calendar.js"></script>
<script type="text/javascript" src="/scripts/calendar/calendar-en.js"></script>
<script type="text/javascript" src="/scripts/calendar/calendar-setup.js"></script>
EOD;
			}

			$name = $this->name();
			$span_name = $this->name() . '_span';
			$trigger_name = $this->name() . '_trigger';
			$value = intval($this->value());
			if(!$value)
				$value = time();

			$display_value = strftime("%d. %B %Y : %H:%M", $value);

			$html.=<<<EOD
<input type="hidden" name="$name" id="$name" value="$value" />
<span id="$span_name">$display_value</span> <img src="/scripts/calendar/img.gif" id="$trigger_name"
	style="cursor: pointer; border: 1px solid red;" title="Date selector"
	onmouseover="this.style.background='red';" onmouseout="this.style.background=''" />
<script type="text/javascript">
Calendar.setup({
	inputField  : "$name",
	ifFormat    : "%s",
	displayArea : "$span_name",
	daFormat    : "%d. %B %Y : %H:%M",
	button      : "$trigger_name",
	singleClick : true,
	showsTime   : true,
	step        : 1
});
</script>
EOD;
			return $html;
		}
	}

	abstract class FormRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
		}

		public function is_valid(Form &$form)
		{
			if($this->is_valid_impl($form))
				return true;
			$form->add_message($this->message);
			return false;
		}

		protected function is_valid_impl(Form &$form)
		{
			return false;
		}

		protected $message;
	}

	class EqualFieldsRule extends FormRule {
		protected $message = 'The two related fields are not equal';

		public function __construct($field1, $field2, $message = null)
		{
			$this->field1 = $field1;
			$this->field2 = $field2;
			parent::__construct($message);
		}

		protected function is_valid_impl(Form &$form)
		{
			$dbobj = $form->dbobj();
			return $dbobj->get($this->field1) == $dbobj->get($this->field2);
		}

		protected $field1;
		protected $field2;
	}


	abstract class FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
		}

		public function is_valid(FormItem &$item)
		{
			if($this->is_valid_impl($item))
				return true;
			$item->add_message($this->message);
			return false;
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return false;
		}

		protected $message;
	}

	class RequiredRule extends FormItemRule {
		protected $message = 'Value required';

		protected function is_valid_impl(FormItem &$item)
		{
			return $item->value()!='';
		}
	}

	class NumericRule extends FormItemRule {
		protected $message = 'Value must be numeric';

		protected function is_valid_impl(FormItem &$item)
		{
			return is_numeric($item->value());
		}
	}

	class RegexRule extends FormItemRule {
		protected $message = 'Value does not validate';

		public function __construct($regex, $message = null)
		{
			$this->regex = $regex;
			parent::__construct($message);
		}
		
		protected function is_valid_impl(FormItem &$item)
		{
			return preg_match($this->regex, $item->value());
		}

		protected $regex;
	}

	class EmailRule extends RegexRule {
		public function __construct($message = null)
		{
			parent::__construct(
				'/^((\"[^\"\f\n\r\t\v\b]+\")|([\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+(\.'
				. '[\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+)*))@((\[(((25[0-5])|(2[0-4][0-9])'
				. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.'
				. '((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
				. '|([0-1]?[0-9]?[0-9])))\])|(((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))'
				. '\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
				. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9])))|'
				. '((([A-Za-z0-9\-])+\.)+[A-Za-z\-]+))$/',
				$message);
		}
	}

	class CallbackRule extends FormItemRule {
		public function __construct($callback, $message = null)
		{
			$this->callback = $callback;
			parent::__construct($message);
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return call_user_func($this->callback, $item);
		}

		protected $callback;
	}

	class EqualsRule extends FormItemRule {
		protected $message = 'Value does not validate';

		public function __construct($compare_value, $message = null)
		{
			$this->compare_value = $compare_value;
			parent::__construct($message);
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return $this->compare_value == $item->value();
		}

		protected $compare_value;
	}

	class MD5EqualsRule extends EqualsRule {
		protected function is_valid_impl(FormItem &$item)
		{
			return $this->compare_value == md5($item->value());
		}
	}

?>
