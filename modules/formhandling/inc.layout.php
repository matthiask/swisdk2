<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	// layout classes: vertical and horizontal boxes and a grid implementation
	
	interface Swisdk_Layout_Item {
		public function get_html();
	}
	
	// bah... enums
	define('SL_HORIZONTAL', 1);
	define('SL_VERTICAL', 2);
	
	class Swisdk_Layout_Box implements Swisdk_Layout_Item {
		public function __construct($orientation)
		{
			$this->orientation = $orientation;
		}
		public function pack_start(Swisdk_Layout_Item &$item)
		{
			array_unshift($this->items, $item);
		}
		
		public function pack_end(Swisdk_Layout_Item &$item)
		{
			array_push($this->items, $item);
		}
		
		public function get_html()
		{
			if($this->orientation==SL_HORIZONTAL) {
				$html = '<table><tr>';
				foreach($this->items as &$item) {
					$html .= '<td>' . $item->get_html() . '</td>';
				}
				return $html . '</tr></table>';
			}
			
			// vertical
			$html = '<table>';
			foreach($this->items as &$item) {
				$html .= '<tr><td>' . $item->get_html() . '</td></tr>';
			}
			return $html . '</table>';
		}
		
		protected $orientation = 0;
		protected $items = array();
	}
	
	class Swisdk_Layout_HBox extends Swisdk_Layout_Box {
		public function __construct()
		{
			parent::__construct(SL_HORIZONTAL);
		}
	}
	
	class Swisdk_Layout_VBox extends Swisdk_Layout_Box {
		public function __construct()
		{
			parent::__construct(SL_VERTICAL);
		}
	}
	
	// this is a simple wrapper around an SF_Item class which
	// handles the adversities of a Grid Layout. Its implementation
	// is of no interest to the developer.
	class Swisdk_Layout_GridItem implements Swisdk_Layout_Item {
		public function __construct($x,$y,&$item,$w,$h)
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
		public function get_html($columnclass='')
		{
			if($this->called===true) {
				return '';
			}
			$this->called = true;

			$html = '<td';
			if($columnclass) {
				$html .= ' class="' . $columnclass . '"';
			}
			if($this->w!=1) {
				$html .= ' colspan="' . $this->w . '"';
			}
			if($this->h!=1) {
				$html .= ' rowspan="' . $this->h . '"';
			}
			return $html . '>' . $this->item->get_html() . '</td>';
		}
		
		protected $x,$y,$item,$w,$h;
		protected $called;
	}
	
	// Sets the correct class name on a empty table cell
	class Swisdk_Layout_EmptyGridItem extends Swisdk_Layout_GridItem {
		public function __construct()
		{}
		
		public function get_html($columnclass='')
		{
			if($columnclass) {
				return '<td class="' . $columnclass . '">&nbsp;</td>';
			}
			return '<td>&nbsp;</td>';
		}
	}
	
	class Swisdk_Layout_Grid implements Swisdk_Layout_Item {
		public function __construct()
		{
			$this->h = 0;
			$this->w = 0;
		}
		
		public function add_item($x, $y, Swisdk_Layout_Item &$item, $w=1, $h=1)
		{
			$this->w = max($this->w, $x+$w);
			$this->h = max($this->h, $y+$h);
			
			$gi = new Swisdk_Layout_GridItem($x,$y,$item,$w,$h);
			for($j=$y; $j<$y+$h; ++$j) {
				for($i=$x; $i<$x+$w; ++$i) {
					$this->items[$j][$i] =& $gi;
				}
			}
		}
		
		public function get_html()
		{
			$empty = new Swisdk_Layout_EmptyGridItem();
			$html = '<table>';
			for($j=0; $j<$this->h; ++$j) {
				$html .= '<tr>';
				for($i=0; $i<$this->w; ++$i) {
					$item =& $this->items[$j][$i];
					if(!$item) {
						$item =& $empty;
					}
					if(isset($this->columnclass[$i])) {
						$html .= $item->get_html($this->columnclass[$i]);
					} else {
						$html .= $item->get_html();
					}
				}
				$html .= "</tr>\n";
			}
			return $html . '</table>';
		}
		
		public function set_column_class($column, $class)
		{
			$this->columnclass[$column] = $class;
			$this->w = max($this->w, $column);
		}
		
		public function get_width()
		{
			return $this->w;
		}
		
		public function get_height()
		{
			return $this->h;
		}
		
		protected $items = array();
		protected $w, $h;
		protected $columnclass = array();
	}

?>
