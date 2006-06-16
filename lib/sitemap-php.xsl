<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="text"/>
<xsl:param name="language"/>

<xsl:template match="sitemap">&lt;?php
global $_swisdk2_sitemap;
$_swisdk2_sitemap = array();
<xsl:apply-templates/>
?></xsl:template>

<xsl:template match="site">
$_swisdk2_sitemap['<xsl:value-of select="@id"/>']['<xsl:value-of select="@lang"/>'] = array(
	<xsl:if test="@title">
	'title' => '<xsl:value-of select="@title"/>',
	</xsl:if>
	<xsl:if test="@url">
	'url' => '<xsl:value-of select="@url"/>',
	</xsl:if>
	<xsl:if test="count(*) &gt; 0">
	'pages' => array(
		<xsl:apply-templates/>
	),
	</xsl:if>
);
</xsl:template>

<xsl:template match="page">
'<xsl:value-of select="@id"/>' => array(
	<xsl:if test="@title">
	'title' => '<xsl:value-of select="@title"/>',
	</xsl:if>
	<xsl:if test="@path">
	'path' => '<xsl:value-of select="@path"/>',
	</xsl:if>
	<xsl:if test="@url">
	'url' => '<xsl:value-of select="@url"/>',
	</xsl:if>
	<xsl:if test="@rewrite">
	'rewrite' => '<xsl:value-of select="@rewrite"/>',
	</xsl:if>
	<xsl:if test="count(*) &gt; 0">
	'pages' => array(
		<xsl:apply-templates/>
	),
	</xsl:if>
),
</xsl:template>
</xsl:stylesheet>

