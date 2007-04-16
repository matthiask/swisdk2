<?php
	/*
	*	Copyright (c) 2006-2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class NoTableFormRenderer extends FormRenderer {
		protected $html;
		protected $odd = true;

		public function html()
		{
			return $this->html_start
				.$this->html
				.$this->html_end;
		}

		protected function visit_Form_start($obj)
		{
			$this->_collect_javascript($obj);
			$this->form_submitted = $obj->submitted();
			$this->html .= '<fieldset id="'.$obj->id().'">';
			if($title = $obj->title())
				$this->html .= '<legend>'.$title.'</legend>';
			$this->html .= "\n";
		}

		protected function visit_Form_end($obj)
		{
			$this->html .= '</fieldset>';
			$this->_collect_javascript($obj);

			$upload = '';
			$valid = '';
			if($this->file_upload)
				$upload = 'enctype="multipart/form-data">'."\n"
					.'<input type="hidden" name="MAX_FILE_SIZE" '
					.'value="'
					.str_replace(array('k', 'm'), array('000', '000000'),
						strtolower(ini_get('upload_max_filesize')))
					.'"';
			list($html, $js) = $this->_validation_html($obj);
			$this->add_html_start(
				'<form method="post" action="'.htmlspecialchars($_SERVER['REQUEST_URI'])
				.'" id="'.$obj->id()."\" $html class=\"sf-form\" "
				."accept-charset=\"utf-8\" $upload>\n<div>\n".$js);
			$this->add_html_end($this->_message_html($obj));
			$this->add_html_end($this->_info_html($obj));
			$this->add_html_end("</div></form>\n");
		}

		protected function visit_FormBox_start($obj)
		{
			$this->_collect_javascript($obj);
			if($obj->is_empty() || !$obj->widget())
				return;

			$this->odd = true;
			$this->html .= '<fieldset id="'.$obj->id().'">';

			$title = $obj->title();
			$e = $obj->expander();

			if($e===null) {
				if($title)
					$this->html .= '<legend>'.$title."</legend>\n";
			} else if($title) {
				$id = $obj->id().'__expander';

				$this->html .= '<legend>'
					.'<a href="#" onclick="$(\'#'.$id.'_s\').val(1-$(\'#'
						.$id.'_s\').val());$(\'#'.$id.'\').toggle();'
						.'return false">'
					.$title." (click to show/hide)</a></legend>\n"
					.'<input type="hidden" id="'.$id.'_s" name="'.$id.'_s"'
						.' value="'.intval($e==FORM_EXPANDER_SHOW).'" />';
				$this->html .= "\n";
				$this->html .= '<div id="'.$id.'">';
				$this->html .= "\n";
				$this->html .= <<<EOD
<script type="text/javascript">
//<![CDATA[
$(function(){
	$('#$id').each(function(){
		this.style.display = '$e';
	});
});
//]]>
</script>

EOD;
			}
		}

		protected function visit_FormBox_end($obj)
		{
			if($obj->is_empty() || !$obj->widget())
				return;
			$this->html .= $this->_message_html($obj);
			$this->html .= '</div></fieldset>';
			$this->html .= "\n";
		}

		protected function _render($obj, $field_html, $row_class=null)
		{
			if(!$this->odd)
				$row_class .= ' even';
			$this->odd = !$this->odd;

			$this->html .= '<div class="sf-element '.$row_class.'">';
			$this->html .= "\n";
			$this->html .= $this->_title_html($obj)
				."\n"
				.$field_html
				."\n"
				.'<span class="sf-info">'
				.$this->_info_html($obj)
				.$this->_message_html($obj)
				.'</span></div>';
			$this->html .= "\n";
		}

		protected function _render_bar($obj, $html, $row_class=null)
		{
			$this->_render($obj, $html, $row_class);
		}
	}

?>
