DROP TABLE IF EXISTS `tbl_forum_topic`;
CREATE TABLE `tbl_forum_topic` (
  `forum_topic_id` int(11) NOT NULL auto_increment,
  `forum_topic_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `forum_topic_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `forum_topic_creation_dttm` int(10) unsigned NOT NULL,
  `forum_topic_author_id` int(11) NOT NULL,
  `forum_topic_comment_realm` varchar(32) character set utf8 NOT NULL,
  `forum_topic_last_comment_id` int(11) NOT NULL,
  PRIMARY KEY  (`forum_topic_id`),
  KEY `forum_author_id` (`forum_topic_author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
