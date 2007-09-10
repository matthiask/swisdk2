DROP TABLE IF EXISTS `tbl_tag`;
CREATE TABLE `tbl_tag` (
  `tag_id` int(11) NOT NULL auto_increment,
  `tag_title` varchar(64) collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
