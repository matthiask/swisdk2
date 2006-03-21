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
		
		public function name()
		{
			return $this->name;
		}
		
		public function set_name($name)
		{
			$this->name = $name;
		}

		public function value()
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
		public function value()
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
		
		public function choices()
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
		public function helper_html($type)
		{
			return '<input type="' . $type . '" id="' . $this->name() . '" name="' . $this->name()
					. '" value="' . (string)$this->value() . '" />';
		}
	}
	
	class Swisdk_Form_TextEntry extends Swisdk_Form_InputEntry {
		public function html()
		{
			return parent::helper_html('text');
		}
	}

	class Swisdk_Form_TextareaEntry extends Swisdk_Form_InputEntry {
		public function html()
		{
			return '<textarea id="' . $this->name() . '" name="' . $this->name() . '">' . $this->value() . '</textarea>';
		}
	}
	
	class Swisdk_Form_HiddenEntry extends Swisdk_Form_InputEntry {
		public function html()
		{
			return parent::helper_html('hidden');
		}
	}
	
	class Swisdk_Form_PasswordEntry extends Swisdk_Form_InputEntry {
		public function html()
		{
			return parent::helper_html('password');
		}
	}
	
	class Swisdk_Form_CheckBoxEntry extends Swisdk_Form_BoolValue implements Swisdk_Layout_Item {
		public function html()
		{
			return '<input type="checkbox" id="' . $this->name() . '" name="' . $this->name()
					. '"' . ($this->value()?' checked="checked"':'') . '" />';
		}
	}
	
	class Swisdk_Form_ComboBoxEntry extends Swisdk_Form_ChoiceValue implements Swisdk_Layout_Item {
		public function html()
		{
			$html = '<select id="' . $this->name() . '" name="' . $this->name() . '">';
			if(count($this->choices)) {
				$value_ = $this->value();
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

	class Swisdk_Form_Multiselect extends Swisdk_Form_ChoiceValue implements Swisdk_Layout_Item {
		public function html()
		{
			$html = '<select multiple="multiple" id="' . $this->name() . '" name="' . $this->name() . '">';
			if(count($this->choices)) {
				$value_ = $this->value();
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
		public function html()
		{
			if($value = $this->value()) {
				$value = ' value="' . $value . '"';
			}
			if($name = $this->name()) {
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
		
		public function text()
		{
			return $this->text;
		}
		
		public function set_text($text)
		{
			$this->text = $text;
		}
		
		public function html()
		{
			return $this->text;
		}
		
		protected $text;
	}
	
?>
