<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Returns the Form as a nested array
	 */
	class ArrayFormRenderer extends FormRenderer {
		protected $array = array();
		protected $stack = array();
		protected $ref;

		public function __construct()
		{
			$this->ref = 0;
			$this->stack[0] =& $this->array;
		}

		protected function visit_FormBox_start($obj)
		{
			$name = $obj->name();
			$this->stack[$this->ref][$name] = array();
			$this->stack[$this->ref+1] =& $this->stack[$this->ref][$name];
			$this->ref++;
			parent::visit_FormBox_start($obj);
		}

		protected function visit_FormBox_end($obj)
		{
			parent::visit_FormBox_end($obj);
			$this->ref--;
		}

		protected function _render($obj, $field_html, $row_class = null)
		{
			$this->stack[$this->ref][$obj->name()] = array(
				'type' => get_class($obj),
				'name' => $obj->name(),
				'html' => $field_html,
				'title' => $this->_title_html($obj),
				'message' => $this->_message_html($obj),
				'info' => $this->_info_html($obj));
		}

		protected function _render_bar($obj, $html, $row_class = null)
		{
			if($obj instanceof Form)
				$this->array['form'] = array(
					'type' => get_class($obj),
					'html' => $html);
			else if($obj instanceof FormItem)
				$this->_render($obj, $html, $row_class);
			else
				$this->stack[$this->ref][] = array(
					'type' => get_class($obj),
					'html' => $html);
		}

		public function fetch_array()
		{
			$this->array['form'] = array_merge($this->array['form'], array(
				'start' => $this->html_start,
				'end' => $this->html_end));
			return $this->array;
		}
	}

?>
