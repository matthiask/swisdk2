<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch> and
	*			    Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	* redirects the client browser to the new location
	*/
	function redirect($url)
	{
		if(strpos($url, "\n")===false)
			header('Location: '.$url);
		else
			SwisdkError::handle(new FatalError('Invalid location specification: '.$url));
	}

	/**
	* Returns the GET or POST value given by $parameter (if both exists the post
	* values is returned)
	*/
	function getInput($var, $default=null)
	{
		$value = $default;
		if(isset($_POST[$var]))
			$value = $_POST[$var];
		else if(isset($_GET[$var]))
			$value = $_GET[$var];
		return $value;
	}
	
	/**
	*	Generates a string of random numbers and characters. 
	*/
	function randomKeys($length)
  	{
		$pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
		for($i=0;$i<$length;$i++)
   		{
		     $key .= $pattern{rand(0,35)};
	   	}
		return $key;
	}
		
?>
