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
	 * Form strongly depends on DBObject for its inner workings
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
	 * The Form automatically writes its values into the provided
	 * DBObject.
	 *
	 *
	 * example without database
	 * ************************
	 *
	 * $form = new Form();
	 * $form->bind(DBObject::create('ContactForm')); // cannot autogenerate (obviously!)
	 * $form->set_title('contact form');
	 * $form->add('Sender'); // default FormItem is TextInput; use it
	 * $form->add('Title');
	 * $form->add('Text', new Textarea());
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
	 */

	require_once MODULE_ROOT . 'inc.data.php';
	require_once MODULE_ROOT . 'inc.layout.php';

	/**
	 * The FormBox is the basic grouping block of a Form
	 *
	 * There may be 1-n FormBoxes in one Form
	 */
	class FormBox {
		protected $items;
		protected $title;

		/**
		 * @param $dbobj: the DBObject bound to the Form
		 */
		public function __construct(&$dbobj)
		{
			$this->dbobj = $dbobj;
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
				if($args[0] instanceof FormItem) {
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

		protected function add_initialized_obj($obj)
		{
			$obj->init_value($this->dbobj());
			if($obj->name())
				$this->items[$obj->name()] =& $obj;
			else
				$this->items[] =& $obj;
			return $obj;
		}

		protected function add_obj($title, $obj, $field=null)
		{
			if($field===null) {
				$field = $this->dbobj()->name(
					$obj->field_name($title));
			}

			$obj->set_title($title);
			$obj->set_name($field);
			$obj->set_message('blah?');
			$obj->init_value($this->dbobj());

			$this->items[$field] = $obj;

			return $obj;
		}

		protected function add_dbobj_ref($title, $relspec)
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
						//TODO better error handling (warning)
						echo 'Cannot edit relation of type DB_REL_MANY.';
					default:
						echo 'Oops. Unknown relation type.';
				}
			}
		}

		/**
		 * @return html portion of form
		 */
		public function html()
		{
			$grid = new Layout_Grid();
			$hidden_html = '';

			if($this->title)
				$grid->add_item(0, $grid->height(), $this->title, 3, 1);
			foreach($this->items as &$item) {
				// special treatment of HiddenInput fields
				if($item instanceof HiddenInput)
					$hidden_html .= $item->html();
				else
					$item->render($grid);
			}
			return $hidden_html . $grid->html();
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			foreach($this->items as &$item)
				if(!$item->is_valid())
					return false;
			return true;
		}
	}

	class Form extends FormBox {
		public function __construct()
		{
		}

		/**
		 * holds the DBObject bound to this Form
		 */
		protected $dbobj;

		/**
		 * @return the bound DBObject
		 */
		public function &dbobj()
		{
			if(!$this->dbobj)
				SwisdkError::handle(new BasicSwisdkError(
					'No DBObject bound to form!'));
			return $this->dbobj;
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

		public function bind_ref(&$dbobj, $autogenerate=false)
		{
			$this->dbobj = $dbobj;
			if($autogenerate)
				$this->autogenerate();
			$this->generate_form_id();
		}

		/**
		 * @return the Form html
		 */
		public function html()
		{
			$id = $this->add(new HiddenInput());
			$id->set_name($this->form_id);
			$id->set_value(1);

			$html = '<form method="post" action="'.$_SERVER['REQUEST_URI'].'">';
			$html .= parent::html();
			$html .= '</form>';
			return $html;
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			// has this form been submitted (or was it another form on the same page)
			if(!isset($_REQUEST[$this->form_id]))
				return false;
			// loop over FormRules
			foreach($this->rules as &$rule)
				if(!$rule->is_valid(&$this))
					return false;
			// loop over each items own validation rules
			return parent::is_valid();
		}

		public function add_rule(FormRule $rule)
		{
			$this->rules[] = $rule;
		}

		protected $rules = array();

		protected $form_id;

		public function generate_form_id()
		{
			//TODO use form_id as name of array for all values in the form!
			//that way, we can add multiple forms of the same type on one site
			//
			//e.g. "__swisdk_form_tbl_item_1[item_id]" instead of "item_id" alone
			$id = $this->dbobj->id();
			$this->form_id = '__swisdk_form_'.$this->dbobj->table().'_'.($id?$id:0);
		}

		/**
		 * Use the DBObject's field list and the relations to build a Form
		 */
		public function autogenerate()
		{
			$fields = $this->dbobj->field_list();
			$relations_ = $this->dbobj->relations();

			$relations = array();
			foreach($relations_ as $class => &$r) {
				$relations[$r['field']] = $r;
			}

			foreach($fields as &$field) {
				$fname = $field['Field'];
				$sn = $this->dbobj->shortname($fname);
				if($fname==$this->dbobj->primary()) {
					$this->add('ID', new HiddenInput());
					continue;
				}
				if(isset($relations[$fname])) {
					switch($relations[$fname]['type']) {
						case DB_REL_SINGLE:
							$f = $this->add($sn, new DropdownInput(), $fname);
							$dc = DBOContainer::find($relations[$fname]['class']);
							$choices = array();
							foreach($dc as $o) {
								$items[$o->id()] = $o->title();
							}
							$f->set_items($items);
							break;
						case DB_REL_MANY:
							$f = $form->add($sn, new Multiselect(), $fname);
							$dc = DBOContainer::find($relations[$fname]['class']);
							$items = array();
							foreach($dc as $o) {
								$items[$o->id()] = $o->title();
							}
							$f->set_items($items);
							break;
					}
				} else if(strpos($fname,'dttm')!==false) {
					$this->add($sn, new DateInput(), $fname);
				} else if(strpos($field['Type'], 'text')!==false) {
					$this->add($sn, new Textarea(), $fname);
				} else {
					$this->add($sn, new TextInput(), $fname);
				}
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
		 * helper for Form::add_obj()
		 *
		 * TODO: documentation for the field naming rules
		 *
		 * Examples for a DBObject of class 'Item':
		 *
		 * TextInput with title 'Title' will be item_title
		 * Textarea with title 'Description' will be item_description
		 * DateInput with title 'Creation' will be item_creation_dttm
		 */
		public function field_name($title)	{ return strtolower($title); } 

		/**
		 * accessors and mutators
		 */
		public function value()			{ return $this->value; }
		public function set_value($value)	{ $this->value = $value; } 
		public function name()			{ return $this->name; }
		public function set_name($name)		{ $this->name = $name; } 
		public function title()			{ return $this->title; }
		public function set_title($title)	{ $this->title = $title; } 
		public function message()		{ return $this->message; }
		public function set_message($message)	{ $this->message = $message; }

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
			$grid->add_item(0, $this->render_y, $this->title());
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

			if(isset($_POST[$name]))
				$dbobj->set($name, stripslashes($_POST[$name]));

			$this->set_value($dbobj->get($name));
		}

		public function add_rule(FormItemRule $rule)
		{
			$this->rules[] = $rule;
		}

		public function is_valid()
		{
			foreach($this->rules as &$rule)
				if(!$rule->is_valid(&$this))
					return false;
			return true;
		}
	}

	class TextInput extends FormItem {
		protected $type = 'text';
		protected function field_html()
		{
			return '<input type="'.$this->type.'" name="'.$this->name().'" id="'
				.$this->name().'"  value="'.$this->value().'"/>';
		}
	}

	/**
	 * hidden fields get special treatment (see also FormBox::html())
	 *
	 * re-use TextInput::field_html() (not clean, but I was too lazy to write the
	 * html construction code twice)
	 */
	class HiddenInput extends TextInput {
		protected $type = 'hidden';

		public function html()
		{
			return $this->field_html();
		}
	}

	class PasswordInput extends TextInput {
		protected $type = 'password';
	}

	class Textarea extends FormItem {
		protected function field_html()
		{
			//TODO make size configurable (user should be able to pass attributes anyway)
			return '<textarea rows="20" cols="60" name="'.$this->name().'" id="'.$this->name().'">'
				.$this->value().'</textarea>';
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
			$html = '<select name="'.$this->name().'" id="'.$this->name().'">';
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
			$html = '<select name="'.$this->name().'[]" id="'.$this->name().'" multiple="multiple">';
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
		protected function field_html()
		{
			return '<input type="submit" />';
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

			$display_value = strftime("%d. %B %Y : %H:%M", $value);

			$html.=<<<EOD
<input type="hidden" name="$name" id="$name" value="$value" />
<span id="$span_name">$display_value</span> <img src="/scripts/calendar/img.gif" id="$trigger_name"
	style="cursor: pointer; border: 1px solidred;" title="Date selector"
	onmouseover="this.style.background='red';" onmouseout="this.style.background=''" />
<script type="text/javascript">
Calendar.setup({
	inputField  : "$name",
	ifFormat    : "%s",
	displayArea : "$span_name",
	daFormat    : "%d. %B %Y : %H:%M",
	button      : "$trigger_name",
	singleClick : true,
	electric    : false,
	showsTime   : true,
	step        : 1
});
</script>
EOD;
			return $html;
		}
	}

	abstract class FormRule {
		abstract public function is_valid(Form &$form);
	}

	class EqualFieldsRule extends FormRule {
		public function __construct($field1, $field2)
		{
			$this->field1 = $field1;
			$this->field2 = $field2;
		}
		public function is_valid(Form &$form)
		{
			$dbobj = $form->dbobj();
			return $dbobj->get($this->field1) == $dbobj->get($this->field2);
		}

		protected $field1;
		protected $field2;
	}


	abstract class FormItemRule {
		abstract public function is_valid(FormItem &$item);
	}

	class RequiredRule extends FormItemRule {
		public function is_valid(FormItem &$item)
		{
			return $item->value()!='';
		}
	}

	class NumericRule extends FormItemRule {
		public function is_valid(FormItem &$item)
		{
			return is_numeric($item->value());
		}
	}

	class RegexRule extends FormItemRule {
		public function __construct($regex)
		{
			$this->regex = $regex;
		}
		
		public function is_valid(FormItem &$item)
		{
			return preg_match($this->regex, $item->value());
		}

		protected $regex;
	}

	class EmailRule extends RegexRule {
		public function __construct()
		{
			$this->regex = '/^((\"[^\"\f\n\r\t\v\b]+\")|([\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+(\.'
				. '[\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+)*))@((\[(((25[0-5])|(2[0-4][0-9])'
				. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.'
				. '((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
				. '|([0-1]?[0-9]?[0-9])))\])|(((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))'
				. '\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
				. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9])))|'
				. '((([A-Za-z0-9\-])+\.)+[A-Za-z\-]+))$/';
		}
	}

	class CallbackRule extends FormItemRule {
		public function __construct($callback)
		{
			$this->callback = $callback;
		}

		public function is_valid(FormItem &$item)
		{
			return call_user_func($this->callback, $item);
		}

		protected $callback;
	}

?>
