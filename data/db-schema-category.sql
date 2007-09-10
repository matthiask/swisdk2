DROP TABLE IF EXISTS `tbl_category`;
CREATE TABLE `tbl_category` (
  `category_id` int(11) NOT NULL auto_increment,
  `category_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `category_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
