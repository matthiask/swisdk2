DROP TABLE IF EXISTS `tbl_event`;
CREATE TABLE `tbl_event` (
  `event_id` int(11) NOT NULL auto_increment,
  `event_creation_dttm` int(11) unsigned NOT NULL,
  `event_start_dttm` int(11) unsigned NOT NULL,
  `event_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `event_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `event_location` text collate utf8_unicode_ci NOT NULL,
  `event_text` text collate utf8_unicode_ci NOT NULL,
  `event_report` text collate utf8_unicode_ci NOT NULL,
  `event_gallery_album_id` int(11) NOT NULL,
  PRIMARY KEY  (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
