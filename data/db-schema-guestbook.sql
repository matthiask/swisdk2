DROP TABLE IF EXISTS `tbl_guestbook`;
CREATE TABLE `tbl_guestbook` (
  `guestbook_id` int(11) NOT NULL auto_increment,
  `guestbook_active` tinyint(1) NOT NULL,
  `guestbook_creation_dttm` int(11) NOT NULL,
  `guestbook_author_gender` char(1) collate utf8_unicode_ci NOT NULL,
  `guestbook_author_name` varchar(64) collate utf8_unicode_ci NOT NULL,
  `guestbook_author_forename` varchar(64) collate utf8_unicode_ci NOT NULL,
  `guestbook_author_email` varchar(64) collate utf8_unicode_ci NOT NULL,
  `guestbook_author_ip` varchar(32) collate utf8_unicode_ci NOT NULL,
  `guestbook_author_agent` varchar(255) collate utf8_unicode_ci NOT NULL,
  `guestbook_text` text collate utf8_unicode_ci NOT NULL,
  `guestbook_location_id` int(11) NOT NULL,
  `guestbook_reply_dttm` int(11) NOT NULL,
  `guestbook_reply` text collate utf8_unicode_ci NOT NULL,
  `guestbook_rating` int(11) NOT NULL,
  PRIMARY KEY  (`guestbook_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
