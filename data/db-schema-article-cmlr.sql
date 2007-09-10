DROP TABLE IF EXISTS `tbl_article`;
CREATE TABLE `tbl_article` (
  `article_id` int(11) NOT NULL auto_increment,
  `article_author_id` int(11) NOT NULL,
  `article_active` tinyint(1) NOT NULL,
  `article_creation_dttm` int(11) unsigned NOT NULL,
  `article_start_dttm` int(11) unsigned NOT NULL,
  `article_end_dttm` int(11) unsigned NOT NULL,
  `article_endless` tinyint(1) NOT NULL,
  `article_status` varchar(16) NOT NULL default 'publish',
  `article_comment_realm` varchar(32) NOT NULL,
  `article_title` varchar(255) NOT NULL,
  `article_name` varchar(255) NOT NULL,
  `article_teaser` text NOT NULL,
  `article_text` text NOT NULL,
  `article_realm_id` int(11) NOT NULL,
  `article_role_id` int(11) NOT NULL,
  PRIMARY KEY  (`article_id`),
  KEY `article_author_id` (`article_author_id`),
  KEY `article_realm_id` (`article_realm_id`),
  KEY `article_role_id` (`article_role_id`),
  KEY `article_status` (`article_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_article_to_category`;
CREATE TABLE `tbl_article_to_category` (
  `ac_article_id` int(11) NOT NULL,
  `ac_category_id` int(11) NOT NULL,
  PRIMARY KEY  (`ac_article_id`,`ac_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `tbl_article_to_language`;
CREATE TABLE `tbl_article_to_language` (
  `al_article_id` int(11) NOT NULL,
  `al_language_id` int(11) NOT NULL,
  PRIMARY KEY  (`al_article_id`,`al_language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_article_to_realm`;
CREATE TABLE `tbl_article_to_realm` (
  `arr_article_id` int(11) NOT NULL,
  `arr_realm_id` int(11) NOT NULL,
  `arr_role_id` int(11) NOT NULL,
  PRIMARY KEY  (`arr_article_id`,`arr_realm_id`),
  KEY `arr_role_id` (`arr_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
