DROP TABLE IF EXISTS `tbl_template`;
CREATE TABLE `tbl_template` (
  `template_id` int(11) NOT NULL auto_increment,
  `template_key` varchar(64) collate utf8_unicode_ci NOT NULL,
  `template_content` text collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
