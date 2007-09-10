DROP TABLE IF EXISTS `tbl_event`;
CREATE TABLE `tbl_event` (
  `event_id` int(11) NOT NULL auto_increment,
  `event_active` tinyint(1) NOT NULL,
  `event_headline` tinyint(1) NOT NULL,
  `event_creation_dttm` int(11) unsigned NOT NULL,
  `event_start_dttm` int(11) unsigned NOT NULL,
  `event_end_dttm` int(11) unsigned NOT NULL,
  `event_all_day` tinyint(1) NOT NULL,
  `event_openend` tinyint(1) NOT NULL,
  PRIMARY KEY  (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_event_content`;
CREATE TABLE `tbl_event_content` (
  `event_content_id` int(11) NOT NULL auto_increment,
  `event_content_event_id` int(11) NOT NULL,
  `event_content_language_id` int(11) NOT NULL,
  `event_content_active` tinyint(1) NOT NULL,
  `event_content_author_id` int(11) NOT NULL,
  `event_content_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `event_content_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `event_content_teaser` text collate utf8_unicode_ci NOT NULL,
  `event_content_text` text collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`event_content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
