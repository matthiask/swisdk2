<?php
	/*
		Project: swisdk 1.5
		Creator: Moritz Zumb?hl (mail@momoetomo.ch)
		Copyright (c) 2004, ProjectPflanzschulstrasse (http://pflanzschule.irregular.ch/)
		Distributed under the GNU Lesser General Public License.
		Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/
	
	/**
	*	Die with the message... and log the error in the deadscripts.log
	*/
	function SwisdkDie( $message = null ) {
		@error_log ( formatLogEntry($message) , 3 , CONTENT_ROOT . 'logs/deadscripts.log' );
		die( $message );
	}
	
	/**
	*	Appends the date and time to the error message (and a newline char)
	*/
	function formatLogEntry( $message ) {
		return "[" . strftime( '%d.%m.%Y - %H:%M:%S' ) . "] " . $message . "\n"; 
	}
	
	/**
	*	redirects the client browser to the new location
	*/
	function redirect( $url ) {
		header( 'Location: ' . $url );
	}

	/**
	*	Returns the GET or POST value given by $parameter (if both exists the post values is returned)
	*	the function does a basic security check on the values..
	*/
	function getInput( $parameter , $default = "" , $allowtags = null ) {
  		
  		if( existsInput( $parameter ) ) {
			$var = isset( $_POST[$parameter] ) ? $_POST[$parameter] : $_GET[$parameter];
			if( is_array( $var ) ) {
				$newArray = array();
				if( $allowtags == null ) {
					foreach( $var as $key => $value ) {
						$newArray[ $key ] = strip_tags( stripslashes( $value ) );
					}
				} else {
					foreach( $var as $key => $value ) {
						$newArray[ $key ] = strip_tags( stripslashes( $var ), $allowtags );
					}
				}
				return $newArray;
			}
			// else: parameter is scalar
			
  			if( $allowtags == null ) {
  				return strip_tags(stripslashes( $var ));
  			}
  			
  			return strip_tags(stripslashes( $var ) , $allowtags );
  		
  		}
		
		return $default;
	}	

	/**
	*	Returns the GET or POST values of the array keys preserving default values if no appropriate
	*	REQUEST variable was found
	*/
	function getInputs( $parameters , $allowtags = null ) {
		
		$values = array();
		foreach( $parameters as $key => $value ) {
			$values[$key] = getInput( $key , $value , $allowtags );
		}
		
		return $values;
	}

	/**
	*	Checks if the post or get variable exists.
	*/
	function existsInput( $parameter ) {
		return isset( $_POST[$parameter] ) || isset( $_GET[$parameter] );
	}

	/*
	function getConfigValue( $value ) {
	
		switch( $value ) {
		
			case "def_lng":
				return 1;
				break;
			default:
				return null;
				break;
		}
	}
	*/
?>