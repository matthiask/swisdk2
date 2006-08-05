<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	interface Layout_Item {
		public function html();
	}

	// bah... enums
	define('SL_HORIZONTAL', 1);
	define('SL_VERTICAL', 2);

	class Layout_Box implements Layout_Item {
		public function __construct($orientation)
		{
			$this->orientation = $orientation;
		}
		public function pack_start(Layout_Item &$item)
		{
			array_unshift($this->items, $item);
		}

		public function pack_end(Layout_Item &$item)
		{
			array_push($this->items, $item);
		}

		public function html()
		{
			if(!count($this->items))
				return null;
			if($this->orientation==SL_HORIZONTAL) {
				$html = '<table><tr>';
				foreach($this->items as &$item) {
					$html .= '<td>' . $item->html() . '</td>';
				}
				return $html . '</tr></table>';
			}

			// vertical
			$html = '<table>';
			foreach($this->items as &$item) {
				$html .= '<tr><td>' . $item->html() . '</td></tr>';
			}
			return $html . '</table>';
		}

		protected $orientation = 0;
		protected $items = array();
	}

	class Layout_HBox extends Layout_Box {
		public function __construct()
		{
			parent::__construct(SL_HORIZONTAL);
		}
	}

	class Layout_VBox extends Layout_Box {
		public function __construct()
		{
			parent::__construct(SL_VERTICAL);
		}
	}

	// this is a simple wrapper around an SF_Item class which
	// handles the adversities of a Grid Layout_. Its implementation
	// is of no interest to the developer.
	class Layout_GridItem implements Layout_Item {
		public function __construct($x,$y,$item,$w,$h)
		{
			$this->x = $x;
			$this->y = $y;
			$this->item = $item;
			$this->w = $w;
			$this->h = $h;

			$this->called = false;
		}

		// will get called multiple times if either
		// width or height is different from 1
		public function html($columnclass='')
		{
			if($this->called===true) {
				return '';
			}
			$this->called = true;

			$html = '<td';
			if($columnclass)
				$html .= ' class="' . $columnclass . '"';
			if($this->w!=1)
				$html .= ' colspan="' . $this->w . '"';
			if($this->h!=1)
				$html .= ' rowspan="' . $this->h . '"';
			$html .= '>';
			if(is_string($this->item)||!$this->item)
				$html .= $this->item;
			else
				$html .= $this->item->html();
			$html .= "</td>\n";
			return $html;
		}

		protected $x,$y,$item,$w,$h;
		protected $called;
	}

	// Sets the correct class name on a empty table cell
	class Layout_EmptyGridItem extends Layout_GridItem {
		public function __construct()
		{}

		public function html($columnclass='')
		{
			if($columnclass) {
				return '<td class="' . $columnclass . '">&nbsp;</td>';
			}
			return '<td>&nbsp;</td>';
		}
	}

	class Layout_Grid implements Layout_Item {
		protected $row_class = array();

		public function __construct()
		{
			$this->h = 0;
			$this->w = 0;
		}

		public function add_item($x, $y, $item, $w=1, $h=1)
		{
			$this->w = max($this->w, $x+$w);
			$this->h = max($this->h, $y+$h);

			$gi = new Layout_GridItem($x,$y,$item,$w,$h);
			for($j=$y; $j<$y+$h; ++$j) {
				for($i=$x; $i<$x+$w; ++$i) {
					$this->items[$j][$i] =& $gi;
				}
			}
		}

		public function row_class($y)
		{
			return isset($this->row_class[$y])?$this->row_class[$y]:null;
		}

		public function set_row_class($y, $class=null)
		{
			$this->row_class[$y] = $class;
		}

		public function html()
		{
			if(!$this->w && !$this->h)
				return null;
			$empty = new Layout_EmptyGridItem();
			$html = "<table>\n";
			for($j=0; $j<$this->h; ++$j) {
				$html .= '<tr'.(isset($this->row_class[$j])?
					' class="'.$this->row_class[$j].'"':'').">\n";
				for($i=0; $i<$this->w; ++$i) {
					$item =& $empty;
					if(isset($this->items[$j][$i]))
						$item =& $this->items[$j][$i];
					if(isset($this->columnclass[$i])) {
						$html .= $item->html($this->columnclass[$i]);
					} else {
						$html .= $item->html();
					}
				}
				$html .= "</tr>\n";
			}
			return $html . "</table>\n";
		}

		public function set_column_class($column, $class)
		{
			$this->columnclass[$column] = $class;
			$this->w = max($this->w, $column);
		}

		public function width()
		{
			return $this->w;
		}

		public function height()
		{
			return $this->h;
		}

		protected $items = array();
		protected $w, $h;
		protected $columnclass = array();
	}

?>
