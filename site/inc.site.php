<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class Site {
		protected $arguments;

		public function __construct()
		{
		}
		
		public function setArguments( $arguments )
		{
			$this->arguments = $arguments;
		}
	}
?>
