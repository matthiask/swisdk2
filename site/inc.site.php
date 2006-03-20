<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse (http://pflanzschule.irregular.ch/)
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
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
