<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>{$smarty.get.class} Picker</title>
<link rel="stylesheet" type="text/css" href="/media/admin/admin.css" />
{literal}
<style type="text/css">
#body .s-table tbody tr:hover {
	background: #657ba8;
	cursor: pointer;
}

#body label.sf-label {
	text-align: left;
}

#body form dt {
	width: 80px;
}

#body form dd {
	margin-left: 90px;
}

#body form input,
#body form button,
#body form select,
#body form textarea {
	width: 230px;
}

.s-table tbody tr:hover {
	background: #cfc7b7;
	cursor: pointer;
}

#typechooser {
	width: 300px;
	float: left;
}

#typechooser h2 {
	margin: 0;
	padding: 5px;
	background: #dfd8c7;
}

#typechooser ul {
	height: 670px;
	overflow: scroll;
	margin: 0;
	padding: 0;
}

#typechooser li {
	list-style: none;
	margin: 0;
	padding: 10px 0;
	text-align: center;
}

#typechooser li span {
	display: block;
}

#typechooser li a {
	display: block;
	margin: 0 auto;
	width: 240px;
	padding: 10px;
	background: #dfd8c7;
	color: #000;
	text-decoration: none;
	overflow: hidden;
}

#typechooser li a:hover {
	background: #efe8d7;
}

#editarea {
}

#editarea #tools {
	background: #dfd8c7;
	height: 32px;
}

#editarea #tools a {
	display: block;
	float: left;
	padding: 5px 7px;
	border: 1px solid #dfd8c7;
	background: #efe8d7;
}

#editarea #tools a:hover {
	background: #dfd8c7;
}

#imagearea {
	position: relative;
	margin-left: 300px;
}

</style>
<script type="text/javascript">
//<![CDATA[
function do_select(elem, val, str)
{
	opener.select_{/literal}{$element}{literal}(val, str);
	this.close();
}
//]]>
</script>
{/literal}
</head>

<body id="body">

<div id="typechooser">
	<h2>Act on:</h2>
	<ul>
		<li>
			<a href="{$this_url}">
			All types
			</a>
		</li>
		{foreach from=$types item=t}
		<li>
			<a href="{$this_url}?type={$t}">
				<img src="{webroot key=data}/{$htdocs_data_dir}/{$images.$t}?{''|uniqid}" />
				<span>{$t}</span>
			</a>
		</li>
		{/foreach}
	</ul>
</div>


<div id="editarea">
	<div id="tools">
		<a href="{$this_url_type}cmd=restart"
				title="Start over">
			<img src="{webroot key=img}/icons/star.png" />
		</a>
		<a href="{$this_url_type}cmd=rotate_clockwise"
				title="Rotate clockwise">
			<img src="{webroot key=img}/icons/shape_rotate_clockwise.png" />
		</a>
		<a href="{$this_url_type}cmd=rotate_anticlockwise"
				title="Rotate anti-clockwise">
			<img src="{webroot key=img}/icons/shape_rotate_anticlockwise.png" />
		</a>
		<a href="{$this_url_type}cmd=grayscale"
				title="Convert to grayscale">
			<img src="{webroot key=img}/icons/contrast.png" />
		</a>
		<a href="{$this_url_type}cmd=colorize-red"
				title="Colorize image with red color">
			colorize red
		</a>
		<a href="{$this_url_type}cmd=colorize-green"
				title="Colorize image with green color">
			colorize green
		</a>
		<a href="{$this_url_type}cmd=colorize-blue"
				title="Colorize image with blue color">
			colorize blue
		</a>
		<a href="{$this_url_type}cmd=darken"
				title="Darken image">
			<img src="{webroot key=img}/icons/contrast_decrease.png" />
		</a>
		<a href="{$this_url_type}cmd=lighten"
				title="Lighten image">
			<img src="{webroot key=img}/icons/contrast_increase.png" />
		</a>
		<a href="{$this_url_type}cmd=crop"
				title="Crop image">
			<img src="{webroot key=img}/icons/shape_handles.png" />
		</a>
	</div>

	<div id="imagearea">

		{if $smarty.get.cmd=='crop'}
<script type="text/javascript" src="{webroot key=js}/scriptaculous/prototype.js"></script>
<script type="text/javascript" src="{webroot key=js}/scriptaculous/scriptaculous.js"></script>
<script type="text/javascript" src="{webroot key=js}/cropper/cropper.js"></script>


<form action="{$smarty.server.REQUEST_URI}" method="post">
	<input type="hidden" name="w" id="w" />
	<input type="hidden" name="h" id="h" />
	<input type="hidden" name="x" id="x" />
	<input type="hidden" name="y" id="y" />
	<input type="submit" value="crop" />
</form>

<img src="{webroot key=data}/{$htdocs_data_dir}/{$images.crop}?{''|uniqid}" id="image" />

<script type="text/javascript">
//<![CDATA[
{literal}
	Event.observe(window, 'load', function(){
		new Cropper.Img('image', {onEndCrop: onEndCrop});
	});

	function onEndCrop(coords, dimensions) {
		$('w').value = dimensions.width;
		$('h').value = dimensions.height;
		$('x').value = coords.x1;
		$('y').value = coords.y1;
	}
{/literal}
//]]>
</script>


		{else}
		<table style="width:620px;height:620px;border:1px solid #aba;">
			<tr><td style="text-align:center;vertical-align:middle">
			<img src="{webroot key=data}/{$htdocs_data_dir}/{$images.$type}?{''|uniqid}" />
			</td></tr>
		</table>
		{/if}
	</div>
</div>

<br style="clear:both" />

</body>
</html>
