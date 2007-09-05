<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>{swisdk_runtime_value key="website.title"}</title>
{swisdk_needs_library name=jquery}
{swisdk_libraries_html}
<link rel="stylesheet" type="text/css" href="/media/admin/admin.css" />
<script type="text/javascript">{literal}

$(function(){
	$('#fast-switch').change(function(){
		if(this.value)
			window.location.href=this.value;
	});
});

{/literal}</script>
{block name="head"}
{/block}
</head>

<body>

<div id="wrap">

	<div id="head">

		<h1><a href="{swisdk_runtime_value key=navigation.prepend}/admin/">{swisdk_runtime_value key="website.title"}</a></h1>

		<a id="logout" href="?logout=1"><img src="/media/admin/gnome-logout.png" alt="log out" /></a>

		<div id="head-actions">
			<span>Fast switch</span>
			<select id="fast-switch">
				{generate_module_switch}
			</select>
		</div>

	</div>

	{if !$adminindex}
	<div id="subnavigation">
		<ul>
			<li><a href="{swisdk_runtime_value key=navigation.prepend}{$currentmodule.url}"><strong>{$currentmodule.title}</strong></a></li>
		{foreach from=$currentmodule.pages item=module}
			<li><a href="{swisdk_runtime_value key=navigation.prepend}{$module.url}">{$module.title}</a></li>
		{/foreach}
		</ul>
	</div>
	{/if}

	<div id="body">

		{block name="content"}
		{$content}
		{/block}
	</div>

</div>

</body>
</html>
