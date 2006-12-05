<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="text"/>
<xsl:param name="language"/>

<xsl:template match="sitemap">&lt;?php
global $_swisdk2_sitemap;
$_swisdk2_sitemap = array(
	'pages' => array(
	<xsl:apply-templates/>
	)
);
?></xsl:template>

<xsl:template match="page">
'<xsl:value-of select="@id"/>' => array(
	<xsl:for-each select="@*">
		'<xsl:value-of select="name()" />' => '<xsl:value-of select="." />',
	</xsl:for-each>
	<xsl:if test="count(*) &gt; 0">
	'pages' => array(
		<xsl:apply-templates/>
	),
	</xsl:if>
),
</xsl:template>
</xsl:stylesheet>

