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
			SwisdkError::handle(new FatalError(sprintf(
				dgettext('swisdk', 'Invalid location specification: %s'), $url)));
	}

	/**
	* Returns the REQUEST value given by $var
	*/
	function getInputRaw($var, $default = null)
	{
		return isset($_REQUEST[$var])?$_REQUEST[$var]:$default;
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
		if(!utf8_is_valid($value)) {
			require_once UTF8.'/utils/bad.php';
			$value = utf8_bad_replace($value);
		}
		require_once SWISDK_ROOT.'lib/contrib/externalinput.php';
		return popoon_classes_externalinput::basicClean($value);
	}

	function cleanInputRef(&$var)
	{
		$var = cleanInput($var);
	}

	/**
	 * Generates a ASCII-clean representation from any string passed in
	 *
	 * Good for generating slugs from article titles
	 *
	 * Don't rely on the implementation staying the same
	 */
	function slugify($string)
	{
		$fr = array('ä', 'ö', 'ü', 'ß', 'à','á','è','é','î','ô');
		$to = array('ae','oe','ue','ss','a','a','e','e','i','o');

		return preg_replace('/[-\s]+/', '-',
			trim(
				preg_replace('/[^a-z0-9\s-]/', '',
					str_replace($fr, $to,
						utf8_strtolower($string)))));
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
		if(!session_id())
			session_start();
		return sha1(session_id().Swisdk::config_value('core.token').$token);
	}

	/**
	 * return the backtrace as a string
	 */
	function backtrace()
	{
		$bt = debug_backtrace();
		$str = "<pre><b>Backtrace:</b>\n";
		foreach($bt as $frame) {
			$str .= sprintf("%s() called at [%s]\n",
				(isset($frame['class'])?
				$frame['class'].$frame['type']:'').
				$frame['function'],
				isset($frame['file'])?$frame['file'].':'
				.$frame['line']:'');
		}
		$str .= '</pre>';
		return $str;
	}

	/**
	 * pipe a file to the browser
	 */
	function sendFile($path, $name, $mime='application/binary')
	{
		if(!file_exists($path))
			die('Invalid path given');

		if( preg_match( '/^(text|image)/i', $mime )  ) {
			$disposition = "inline";
		} else {
			$disposition = "attachment";
		}

		session_write_close();
		if (isset($_SERVER["HTTPS"])) {
			/**
			* We need to set the following headers to make downloads work using IE in HTTPS mode.
			*/
			header("Pragma: ");
			header("Cache-Control: ");
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
			header("Cache-Control: post-check=0, pre-check=0", false);
		} else if ($disposition == "attachment") {
			header("Cache-control: private");
		} else {
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");
		}
		header("Content-Type: $mime");
		header("Content-Disposition:$disposition; filename=\"".trim(htmlentities($name))."\"");
		header("Content-Description: ".trim(htmlentities($name)));
		header("Content-Length: ".(string)(filesize($path)));
		header("Connection: close");
	
		$fp = fopen( $path, 'rb' );
		fpassthru( $fp );
		fclose( $fp );
		exit();
	}


?>
