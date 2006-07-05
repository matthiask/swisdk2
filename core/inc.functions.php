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
		if(strpos($url, "\n")===false) {
			session_write_close();
			header('Location: '.$url);
		} else
			SwisdkError::handle(new FatalError(
				'Invalid location specification: '.$url));
	}

	/**
	* Returns the REQUEST value given by $var
	*/
	function getInputRaw($var, $default = null)
	{
		return isset($_REQUEST[$var])?$_REQUEST[$var]:null;
	}

	/**
	 * Returns the REQUEST value given by $var, cleaning it to disable
		 * XSS attacks if value is a string or an array of strings
	 */
	function getInput($var, $default = null)
	{
		$value = getInputRaw($var, $default);
		if($value) {
			if(is_array($value))
				array_walk_recursive($value, 'cleanInputRef');
			else
				cleanInputRef($value);
		}
		return $value;
	}

	/**
	 * Clean HTML, hopefully disabling XSS attack vectors
	 */
	function cleanInput($value)
	{
		if(!$value || is_numeric($value))
			return $value;
		require_once SWISDK_ROOT.'lib/contrib/externalinput.php';
		return popoon_classes_externalinput::basicClean($value);
	}

	function cleanInputRef(&$var)
	{
		$var = cleanInput($var);
	}

	/**
	* Generates a string of random numbers and characters. 
	*/
	function randomKeys($length)
  	{
		$pattern = '1234567890abcdefghijklmnopqrstuvwxyz';
		for($i=0; $i<$length; $i++)
		     $key .= $pattern{rand(0,35)};
		return $key;
	}

	/**
	 * generates a unique ID which may be used to guard against CSRF attacks
	 *
	 * http://en.wikipedia.org/wiki/Cross-site_request_forgery
	 */
	function guardToken($token = null)
	{
		return sha1(session_id().Swisdk::config_value('core.token').$token);
	}

?>
