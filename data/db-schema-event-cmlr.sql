DROP TABLE IF EXISTS `tbl_event`;
CREATE TABLE `tbl_event` (
  `event_id` int(11) NOT NULL auto_increment,
  `event_author_id` int(11) NOT NULL,
  `event_contact_id` int(11) NOT NULL,
  `event_active` tinyint(1) NOT NULL,
  `event_creation_dttm` int(11) unsigned NOT NULL,
  `event_start_dttm` int(11) unsigned NOT NULL,
  `event_end_dttm` int(11) unsigned NOT NULL,
  `event_openend` tinyint(1) NOT NULL,
  `event_all_day` tinyint(1) NOT NULL,
  `event_comment_realm` varchar(32) NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_location` text NOT NULL,
  `event_text` text NOT NULL,
  `event_realm_id` int(11) NOT NULL,
  `event_role_id` int(11) NOT NULL,
  PRIMARY KEY  (`event_id`),
  KEY `event_author_id` (`event_author_id`),
  KEY `event_realm_id` (`event_realm_id`),
  KEY `event_role_id` (`event_role_id`),
  KEY `event_contact_id` (`event_contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_event_to_category`;
CREATE TABLE `tbl_event_to_category` (
  `ec_event_id` int(11) NOT NULL,
  `ec_category_id` int(11) NOT NULL,
  PRIMARY KEY  (`ec_event_id`,`ec_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `tbl_event_to_language`;
CREATE TABLE `tbl_event_to_language` (
  `el_event_id` int(11) NOT NULL,
  `el_language_id` int(11) NOT NULL,
  PRIMARY KEY  (`el_event_id`,`el_language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_event_to_realm`;
CREATE TABLE `tbl_event_to_realm` (
  `err_event_id` int(11) NOT NULL,
  `err_realm_id` int(11) NOT NULL,
  `err_role_id` int(11) NOT NULL,
  PRIMARY KEY  (`err_event_id`,`err_realm_id`),
  KEY `err_role_id` (`err_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
