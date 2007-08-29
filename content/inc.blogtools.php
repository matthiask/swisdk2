<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class BlogTools {
		public static function do_trackback($trackback_url, $post_url, $blog_name, $post_title, $post_excerpt)
		{
			$postdata = http_build_query(array(
				'url'		=> $post_url,
				'blog_name'	=> $blog_name,
				'title' 	=> $post_title,
				'excerpt'	=> $post_excerpt
			));

			$ret = BlogTools::do_post($trackback_url, $postdata);

			if($sxml = @simplexml_load_string($ret)) {
				if(intval($sxml->error)==0)
					return true;

				return $sxml->message;
			}

			return false;
		}

		public static function do_pingback($pingback_url, $blog_url, $blog_name)
		{
			$xml=<<<EOD
<?xml version="1.0"?>
<methodCall>
  <methodName>weblogUpdates.ping</methodName>
  <params>
    <param>
      <value>$blog_name</value>
    </param>
    <param>
      <value>$blog_url</value>
    </param>
  </params>
</methodCall>
EOD;
			$ret = BlogTools::do_post($pingback_url, $xml, 'text/xml');
			if($sxml = @simplexml_load_string($ret)) {
				if(intval($sxml->params->param[0]->value->struct->member[0]->value->boolean)==0)
					return true;
			}

			echo $ret;

			return false;
		}

		public static function do_post($url, $data, $content_type = 'application/x-www-form-urlencoded')
		{
			$handle = curl_init();
			curl_setopt($handle, CURLOPT_URL, $url);
			curl_setopt($handle, CURLOPT_POST, 1);
			curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
			curl_setopt($handle, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($handle, CURLOPT_HEADER, 1);
			curl_setopt($handle, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($handle, CURLOPT_AUTOREFERER, 1);
			curl_setopt($handle, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($handle, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($handle, CURLOPT_PORT, 80);
			curl_setopt($handle, CURLOPT_HTTPHEADER, array(
				'Content-Type: '.$content_type));
			$ret = curl_exec($handle);

			if(curl_errno($handle)) {
				print htmlspecialchars(curl_error($handle));
				curl_close($handle);
				return false;
			}

			curl_close($handle);
			return $ret;
		}
	}

?>
