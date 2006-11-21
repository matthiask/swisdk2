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
			exit();
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
	 * generates a css class from a string (request uri, controller uri
	 * or whatever)
	 */
	function cssClassify($string)
	{
		$res = str_replace(' ', '-', trim(preg_replace(
			'/[^A-Za-z]+/', ' ', $string)));
		if($res)
			return $res;
		return 'root';
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

	/**
	 * convert a timespan in seconds to a textual representation
	 */
	function stringifyTimespan($seconds)
	{
		static $timespans = null;

		$template = dgettext('swisdk', '%s ago');
		if($seconds>0)
			$template = dgettext('swisdk', 'in %s');
		$seconds = abs($seconds);

		if(!$timespans)
			$timespans = array_combine(
				explode(',',
					dgettext('swisdk', 'years,months,weeks,days,hours,min,sec')),
				array(86400*365, 86400*365/12, 86400*7, 86400, 3600, 60, 1));

		foreach($timespans as $type => $span)
			if($seconds>2*$span)
				return sprintf($template,
					intval($seconds/$span).' '.$type);

		return 'right now';
	}

	/**
	 * return a unique filenames for related files
	 *
	 * f.e.
	 * img.jpg => img_32kjh321kjh.jpg
	 * img.jpg (thumb) => img_thumb_3k3213kj1j2h.jpg
	 *
	 * If the filename has no extension, the uniquification token is simply appended
	 */
	function uniquifyFilename($fname, $token=null)
	{
		if($token)
			$token = '_'.$token.'_'.uniqid();
		else
			$token = '_'.uniqid();

		$pos = strrpos($fname, '.');
		if($pos===false)
			return $fname.$token;
		else
			return substr($fname, 0, $pos).$token.substr($fname, $pos);
	}

	/**
	 * sanitize a filename
	 */
	function sanitizeFilename($fname)
	{
		$fname = trim(preg_replace('/[^A-Za-z0-9\.\-_+]/', '_', $fname), '._');
		if(!$fname)
			$fname = 'none';
		return $fname;
	}

?>
