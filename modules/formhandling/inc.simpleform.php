<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >
	*	Copyright (c) 2005, ProjectPflanzschulstrasse
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/
	
	require_once MODULE_ROOT . 'formhandling/inc.simpleform.validation.php';
	require_once MODULE_ROOT . 'formhandling/inc.simpleform.formitems.php';
	
	class Swisdk_SimpleForm {
		public function __construct($name='_form', $method='POST', $action=null)
		{
			$this->types = array(
				'hidden' => 'Swisdk_SimpleForm_HiddenEntry',
				'text' => 'Swisdk_SimpleForm_TextEntry',
				'password' => 'Swisdk_SimpleForm_PasswordEntry',
				'checkbox' => 'Swisdk_SimpleForm_CheckBox',
				'combobox' => 'Swisdk_SimpleForm_ComboBox',
				'submit' => 'Swisdk_SimpleForm_SubmitButton'
			);
			
			$this->name = $name;
			$this->method = $method;
			if($action===null) {
				$this->action = $_SERVER['REQUEST_URI'];
			} else {
				$this->action = $action;
			}
			
			$this->grid = new Swisdk_Layout_Grid();
			
			$this->grid->set_column_class(0, 'label');
			$this->grid->set_column_class(1, 'entry');
			$this->grid->set_column_class(2, 'error');
		}
		
		public function &add($type, $name, $label=null, $value=null)
		{
			if(isset($this->types[$type])) {
				$class = $this->types[$type];
				$tmp = func_get_args();
				$tmp = array_slice($tmp, 4);
				$item = new $class($this->grid, $name, $label, $value, isset($tmp[0])?$tmp[0]:null);
				$this->container[$name] =& $item;
				
				// special treatment for the 'hidden' type
				if($type=='hidden') {
					$this->container_hidden[] =& $item;
				}
				return $item;
			}
			
			$msg = 'Unable to find type ' . $type . ' in SimpleForm';
			die($msg);
			return null;
		}
		
		public function register_type($typename, $class)
		{
			$this->types[$typename] = $class;
		}
		
		public function get_html()
		{
			$html = '<form id="' . $this->name . '" id="' . $this->name
					. '" method="' . $this->method . '" action="'
					. $this->action . '">';
			if(count($this->container_hidden)) {
				foreach($this->container_hidden as &$item) {
					$html .= $item->get_entry()->get_html();
				}
			}
			
			$html .= $this->grid->get_html();
			
			return $html . '</form>';
		}
		
		public function &get_values()
		{
			$values = array();
			foreach($this->container as &$item) {
				$entry =& $item->get_entry();
				if($entry->get_name())
					$values[ $entry->get_name() ] = $entry->get_value();
			}
			return $values;
		}

		public function set_values($values)
		{
			foreach($this->container as &$item) {
				$entry =& $item->get_entry();
				if($val =& $values[$entry->get_name()]) {
					$entry->set_value($val);
				}
			}
		}
		
		public function &get_element($name)
		{
			return $this->container[$name];
		}
		
		public function &get_grid()
		{
			return $this->grid;
		}
		
		public function is_valid()
		{
			$valid = true;
			foreach($this->container as &$item) {
				if(!$item->is_valid()) {
					$valid = false;
				}
			}
			return $valid;
		}
		
		protected $types;
		
		protected $grid;
		protected $container, $container_hidden;
		protected $name, $method, $action;
	}
	
?>
