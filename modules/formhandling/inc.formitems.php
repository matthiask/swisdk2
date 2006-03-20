<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class Swisdk_Form_Value {
		public function __construct($name, $value=null)
		{
			$this->name = $name;
			$this->value = $value;
			// developer might want to force adaption of new value. This code should be extended
			if($input = getInput($name)) {
				/*
				if($this->isValid($value)) {
					$this->value = $value;
				}
				*/
				$this->value = $input;
			}
		}
		
		public function get_name()
		{
			return $this->name;
		}
		
		public function set_name($name)
		{
			$this->name = $name;
		}

		public function get_value()
		{
			return $this->value;
		}
		
		public function set_value($value)
		{
			$this->value = $value;
		}
		
		protected $name;
		protected $value;
	}
	
	abstract class Swisdk_Form_BoolValue extends Swisdk_Form_Value {
		public function get_value()
		{
			if($this->value) {
				return true;
			}
			return false;
		}
	}
	
	abstract class Swisdk_Form_ChoiceValue extends Swisdk_Form_Value {
		public function __construct($name, $value = null, $choices = array())
		{
			parent::__construct($name, $value);
			$this->set_choices($choices);
		}
		
		public function get_choices()
		{
			return $this->choices;
		}
		
		public function set_choices($choices)
		{
			$this->choices = $choices;
		}
		
		protected $choices = array();
	}
	
	// this class is not intended for direct use
	abstract class Swisdk_Form_InputEntry extends Swisdk_Form_Value implements Swisdk_Layout_Item {
		public function helper_get_html($type)
		{
			return '<input type="' . $type . '" id="' . $this->get_name() . '" name="' . $this->get_name()
					. '" value="' . (string)$this->get_value() . '" />';
		}
	}
	
	class Swisdk_Form_TextEntry extends Swisdk_Form_InputEntry {
		public function get_html()
		{
			return parent::helper_get_html('text');
		}
	}

	class Swisdk_Form_TextareaEntry extends Swisdk_Form_InputEntry {
		public function get_html()
		{
			return '<textarea id="' . $this->get_name() . '" name="' . $this->get_name() . '">' . $this->get_value() . '</textarea>';
		}
	}
	
	class Swisdk_Form_HiddenEntry extends Swisdk_Form_InputEntry {
		public function get_html()
		{
			return parent::helper_get_html('hidden');
		}
	}
	
	class Swisdk_Form_PasswordEntry extends Swisdk_Form_InputEntry {
		public function get_html()
		{
			return parent::helper_get_html('password');
		}
	}
	
	class Swisdk_Form_CheckBoxEntry extends Swisdk_Form_BoolValue implements Swisdk_Layout_Item {
		public function get_html()
		{
			return '<input type="checkbox" id="' . $this->get_name() . '" name="' . $this->get_name()
					. '"' . ($this->get_value()?' checked="checked"':'') . '" />';
		}
	}
	
	class Swisdk_Form_ComboBoxEntry extends Swisdk_Form_ChoiceValue implements Swisdk_Layout_Item {
		public function get_html()
		{
			$html = '<select id="' . $this->get_name() . '" name="' . $this->get_name() . '">';
			if(count($this->choices)) {
				$value_ = $this->get_value();
				foreach($this->choices as $value => &$description) {
					$html .= '<option ';
					if($value==$value_)
						$html .= 'selected="selected" ';
					$html .= 'value="' . $value . '">' . $description . '</option>';
				}
			}
			return $html . '</select>';
		}
	}
	
	class Swisdk_Form_SubmitButton extends Swisdk_Form_Value implements Swisdk_Layout_Item {
		public function get_html()
		{
			if($value = $this->get_value()) {
				$value = ' value="' . $value . '"';
			}
			if($name = $this->get_name()) {
				$name = ' name="' . $name . '" id="' . $name . '"';
			}
			return '<input type="submit"' . $name . $value . ' />';
		}
	}
	
	class Swisdk_Form_Label implements Swisdk_Layout_Item {
		public function __construct($text)
		{
			$this->text = $text;
		}
		
		public function get_text()
		{
			return $this->text;
		}
		
		public function set_text($text)
		{
			$this->text = $text;
		}
		
		public function get_html()
		{
			return $this->text;
		}
		
		protected $text;
	}
	
?>
