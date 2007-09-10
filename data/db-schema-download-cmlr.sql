DROP TABLE IF EXISTS `tbl_download`;
CREATE TABLE `tbl_download` (
  `download_id` int(11) NOT NULL auto_increment,
  `download_author_id` int(11) NOT NULL,
  `download_active` tinyint(1) NOT NULL,
  `download_creation_dttm` int(11) unsigned NOT NULL,
  `download_start_dttm` int(11) unsigned NOT NULL,
  `download_end_dttm` int(11) unsigned NOT NULL,
  `download_endless` tinyint(1) NOT NULL,
  `download_title` varchar(255) NOT NULL,
  `download_teaser` text NOT NULL,
  `download_realm_id` int(11) NOT NULL,
  `download_role_id` int(11) NOT NULL,
  `download_file_file` varchar(255) NOT NULL,
  `download_file_name` varchar(255) NOT NULL,
  `download_file_mimetype` varchar(64) NOT NULL,
  `download_file_size` int(11) NOT NULL,
  PRIMARY KEY  (`download_id`),
  KEY `download_author_id` (`download_author_id`),
  KEY `download_realm_id` (`download_realm_id`),
  KEY `download_role_id` (`download_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_download_to_category`;
CREATE TABLE `tbl_download_to_category` (
  `dc_download_id` int(11) NOT NULL,
  `dc_category_id` int(11) NOT NULL,
  PRIMARY KEY  (`dc_download_id`,`dc_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `tbl_download_to_language`;
CREATE TABLE `tbl_download_to_language` (
  `dl_download_id` int(11) NOT NULL,
  `dl_language_id` int(11) NOT NULL,
  PRIMARY KEY  (`dl_download_id`,`dl_language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tbl_download_to_realm`;
CREATE TABLE `tbl_download_to_realm` (
  `drr_download_id` int(11) NOT NULL,
  `drr_realm_id` int(11) NOT NULL,
  `drr_role_id` int(11) NOT NULL,
  PRIMARY KEY  (`drr_download_id`,`drr_realm_id`),
  KEY `drr_role_id` (`drr_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
