DROP TABLE IF EXISTS `tbl_comment`;
CREATE TABLE `tbl_comment` (
  `comment_id` int(11) NOT NULL auto_increment,
  `comment_realm` varchar(32) character set utf8 collate utf8_unicode_ci NOT NULL,
  `comment_parent_id` int(11) NOT NULL,
  `comment_creation_dttm` int(11) unsigned NOT NULL,
  `comment_author` varchar(255) NOT NULL,
  `comment_author_url` varchar(255) NOT NULL,
  `comment_author_email` varchar(255) NOT NULL,
  `comment_author_ip` varchar(32) NOT NULL,
  `comment_author_agent` varchar(255) NOT NULL,
  `comment_text` text NOT NULL,
  `comment_state` varchar(16) NOT NULL default 'new',
  `comment_type` varchar(16) NOT NULL default 'comment',
  `comment_notify` tinyint(1) NOT NULL,
  PRIMARY KEY  (`comment_id`),
  KEY `comment_state` (`comment_state`),
  KEY `comment_type` (`comment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
