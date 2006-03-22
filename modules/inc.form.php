<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class FormBox {
		protected $items;
		protected $title;

		public function __construct(&$dbobj)
		{
			$this->dbobj = $dbobj;
		}

		public function set_title($title=null)
		{
			$this->title = $title;
		}

		/**
		 * This function has (at least) three overloads:
		 *
		 * add(FormItem)
		 * add(Title, FormItem)
		 * add(Title, RelSpec) // See DBObject for the relation specs
		 *
		 */
		public function add()
		{
			$args = func_get_args();

			if(count($args)<2) {
				if($args[0] instanceof FormItem) {
					return $this->add_obj(null, $args[0]);
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

		protected function add_dbobj_ref($title, $refspec)
		{
			$relations = $this->dbobj()->relations();
			print_r($relations[$refspec]);
		}

		public function render(&$grid)
		{
			if($this->title)
				$grid->add_item(0, $grid->height(), $this->title, 3, 1);
			foreach($this->items as &$item) {
				$item->render($grid);
			}
		}
	}

	class Form extends FormBox {
		public function __construct()
		{
		}

		protected $dbobj;

		public function &dbobj()
		{
			if(!$this->dbobj)
				SwisdkError::handle(new BasicSwisdkError(
					'No DBObject bound to form!'));
			return $this->dbobj;
		}

		public function bind($dbobj, $autogenerate=false)
		{
			$this->dbobj = $dbobj;
			if($autogenerate)
				$this->autogenerate();
		}

		public function bind_ref(&$dbobj, $autogenerate=false)
		{
			$this->dbobj = $dbobj;
			if($autogenerate)
				$this->autogenerate();
		}

		public function html()
		{
			$grid = new Layout_Grid();
			$this->render($grid);

			$html = '<form method="post">';
			$html .= $grid->html();
			$html .= '</form>';
			return $html;
		}

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
						/*
						case DB_REL_MANY:
							$f = $form->add('multiselect', $fname,
								$this->dbobj->shortname($fname));
							$dc = DBOContainer::find($relations[$fname]['class']);
							$choices = array();
							foreach($dc as $o) {
								$choices[$o->id()] = $o->title();
							}
							$f->entry()->set_choices($choices);
							break;
						*/
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

	class FormItem {

		protected $name;
		protected $title;
		protected $message;

		protected $value;

		/**
		 * helper for Form::add_obj()
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
			$grid->add_item(0, $this->render_y, $this->title());
		}

		protected function render_field(&$grid)
		{
			$grid->add_item(1, $this->render_y, $this->field_html());
		}

		protected function render_message(&$grid)
		{
			$grid->add_item(2, $this->render_y, $this->message());
		}

		protected function field_html()
		{
			return '#OVERRIDE';
		}

		public function init_value($dbobj)
		{
			$name = $this->name();

			if($val = getInput($name))
				$dbobj->set($name, $val);

			$this->set_value($dbobj->get($name));
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

	class HiddenInput extends TextInput {
		protected $type = 'hidden';

		public function render(&$grid)
		{
			// do nothing
		}
	}

	class PasswordInput extends TextInput {
		protected $type = 'password';
	}

	class Textarea extends FormItem {
		protected function field_html()
		{
			return '<textarea name="'.$this->name().'" id="'.$this->name().'">'
				.$this->value().'</textarea>';
		}
	}

	class DropdownInput extends FormItem {
		protected function field_html()
		{
			$html = '<select name="'.$this->name().'" id="'.$this->name().'">';
			foreach($this->items as $k => $v) {
				$html .= '<option value="'.$k.'">'.$v.'</option>';
			}
			$html .= '</select>';
			return $html;
		}

		public function set_items($items)
		{
			$this->items = $items;
		}

		protected $items=array();
	}

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
		public function field_name($title) { return strtolower($title) . '_dttm'; }
		//public function title() { return $this->title . '-Date'; }

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

?>
