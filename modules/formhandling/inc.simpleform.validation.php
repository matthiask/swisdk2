<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class Swisdk_SimpleForm_Validation {
		public static function create($rule)
		{
			if(isset(Swisdk_SimpleForm_Validation::$rules[$rule])) {
				return new Swisdk_SimpleForm_Validation::$rules[$rule];
			}
			
			die('Swisdk_SimpleForm_Validation: "' . $rule . '" rule unknown');
			return null;
		}
		
		protected static $rules = array(
			'required' => 'Swisdk_SimpleForm_Validation_RequiredRule',
			'numeric' => 'Swisdk_SimpleForm_Validation_NumericRule',
			'maxlength' => 'Swisdk_SimpleForm_Validation_MaxLengthRule',
			'minlength' => 'Swisdk_SimpleForm_Validation_MinLengthRule',
			'regex' => 'Swisdk_SimpleForm_Validation_RegexRule',
			'email' => 'Swisdk_SimpleForm_Validation_EmailRule',
			'callback' => 'Swisdk_SimpleForm_Validation_CallbackRule'
		);
	}

	interface Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null);
	}
	
	class Swisdk_SimpleForm_Validation_RequiredRule implements Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null)
		{
			/*unused*/$args;
			return $entry->entry()->value()!='';
		}
	}
	
	class Swisdk_SimpleForm_Validation_NumericRule implements Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null)
		{
			/*unused*/$args;
			return is_numeric($entry->entry()->value());
		}
	}
	
	class Swisdk_SimpleForm_Validation_MaxLengthRule implements Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null)
		{
			return strlen($entry->entry()->value())<=$args;
		}
	}
	
	class Swisdk_SimpleForm_Validation_MinLengthRule implements Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null)
		{
			return strlen($entry->entry()->value())>=$args;
		}
	}
	
	class Swisdk_SimpleForm_Validation_RegexRule implements Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null)
		{
			return preg_match($args, $entry->entry()->value())!=0;
		}
	}
	
	class Swisdk_SimpleForm_Validation_EmailRule implements Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null)
		{
			/*unused*/$args;
			
			// uh...  copied from QuickForm email validation rule
			$regex = '/^((\"[^\"\f\n\r\t\v\b]+\")|([\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+(\.'
					. '[\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+)*))@((\[(((25[0-5])|(2[0-4][0-9])'
					. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.'
					. '((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
					. '|([0-1]?[0-9]?[0-9])))\])|(((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))'
					. '\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
					. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9])))|'
					. '((([A-Za-z0-9\-])+\.)+[A-Za-z\-]+))$/';
			//$regex = '/.*@.*\..*/';

			return preg_match($regex, $entry->entry()->value())!=0;
		}
	}
	
	class Swisdk_SimpleForm_Validation_CallbackRule implements Swisdk_SimpleForm_Validation_Rule {
		public function is_valid(Swisdk_SimpleForm_Entry &$entry, &$args=null)
		{
			return $args($entry->entry()->value());
		}
	}
	
?>
