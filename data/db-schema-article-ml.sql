DROP TABLE IF EXISTS `tbl_article`;
CREATE TABLE `tbl_article` (
  `article_id` int(11) NOT NULL auto_increment,
  `article_active` tinyint(1) NOT NULL,
  `article_headline` tinyint(1) NOT NULL,
  `article_creation_dttm` int(11) unsigned NOT NULL,
  `article_start_dttm` int(11) unsigned NOT NULL,
  `article_end_dttm` int(11) unsigned NOT NULL,
  `article_endless` tinyint(1) NOT NULL,
  PRIMARY KEY  (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_article_content`;
CREATE TABLE `tbl_article_content` (
  `article_content_id` int(11) NOT NULL auto_increment,
  `article_content_article_id` int(11) NOT NULL,
  `article_content_language_id` int(11) NOT NULL,
  `article_content_active` tinyint(1) NOT NULL,
  `article_content_author_id` int(11) NOT NULL,
  `article_content_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `article_content_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `article_content_teaser` text collate utf8_unicode_ci NOT NULL,
  `article_content_text` text collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`article_content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
