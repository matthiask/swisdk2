DROP TABLE IF EXISTS `tbl_article`;
CREATE TABLE `tbl_article` (
  `article_id` int(11) NOT NULL auto_increment,
  `article_creation_dttm` int(11) unsigned NOT NULL,
  `article_start_dttm` int(11) unsigned NOT NULL,
  `article_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `article_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `article_teaser` text collate utf8_unicode_ci NOT NULL,
  `article_text` text collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
