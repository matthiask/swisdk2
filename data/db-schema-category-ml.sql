DROP TABLE IF EXISTS `tbl_category`;
CREATE TABLE `tbl_category` (
  `category_id` int(11) NOT NULL auto_increment,
  `category_parent_id` int(11) NOT NULL,
  `category_realm_id` int(11) NOT NULL,
  PRIMARY KEY  (`category_id`),
  KEY `category_parent_id` (`category_parent_id`),
  KEY `category_realm_id` (`category_realm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `tbl_category_content`;
CREATE TABLE `tbl_category_content` (
  `category_content_id` int(11) NOT NULL auto_increment,
  `category_content_category_id` int(11) NOT NULL,
  `category_content_language_id` int(11) NOT NULL,
  `category_content_title` varchar(64) collate utf8_unicode_ci NOT NULL,
  `category_content_name` varchar(64) collate utf8_unicode_ci NOT NULL,
  `category_content_description` text collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`category_content_id`),
  KEY `category_content_category_id` (`category_content_category_id`),
  KEY `category_content_language_id` (`category_content_language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
