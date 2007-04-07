<?php
	/*
	*	Copyright (c) 2006-2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.layout.php';

	class TableFormRenderer extends FormRenderer {
		protected $grid;

		public function html()
		{
			return $this->html_start
				.$this->grid()->html()
				.$this->html_end;
		}

		protected function &grid()
		{
			if(!$this->grid)
				$this->grid = new Layout_Grid();
			return $this->grid;
		}

		protected function _render($obj, $field_html, $row_class = null)
		{
			$grid = $this->grid();
			$y = $grid->height();
			if($row_class)
				$grid->set_row_class($y, $row_class);
			$grid->add_item(0, $y, $this->_title_html($obj));
			$grid->add_item(1, $y,
				'<div style="float:left;">'.$field_html."</div>\n"
				.$this->_info_html($obj)
				.$this->_message_html($obj));
		}

		protected function _render_bar($obj, $html, $row_class = null)
		{
			$grid = $this->grid();
			$y = $grid->height();
			if($row_class)
				$grid->set_row_class($y, $row_class);
			$grid->add_item(0, $y, $html, 2, 1);
		}
	}

?>
