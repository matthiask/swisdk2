DROP TABLE IF EXISTS `tbl_language`;
CREATE TABLE IF NOT EXISTS `tbl_language` (
  `language_id` int(11) NOT NULL AUTO_INCREMENT,
  `language_key` varchar(4) NOT NULL,
  `language_title` varchar(64) NOT NULL,
  `language_locale` varchar(64) NOT NULL,
  PRIMARY KEY (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `tbl_realm`;
CREATE TABLE IF NOT EXISTS `tbl_realm` (
  `realm_id` int(11) NOT NULL AUTO_INCREMENT,
  `realm_title` varchar(255) NOT NULL,
  `realm_url` varchar(255) NOT NULL,
  `realm_role_id` int(11) NOT NULL,
  PRIMARY KEY (`realm_id`),
  KEY `realm_role_id` (`realm_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `tbl_role`;
CREATE TABLE IF NOT EXISTS `tbl_role` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_title` varchar(32) NOT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `tbl_user`;
CREATE TABLE IF NOT EXISTS `tbl_user` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_name` varchar(32) NOT NULL,
  `user_forename` varchar(32) NOT NULL,
  `user_title` varchar(32) NOT NULL,
  `user_email` varchar(64) NOT NULL,
  `user_login` varchar(16) NOT NULL,
  `user_password` varchar(64) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `tbl_user_group`;
CREATE TABLE IF NOT EXISTS `tbl_user_group` (
  `user_group_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_group_parent_id` int(11) NOT NULL,
  `user_group_title` varchar(255) NOT NULL,
  PRIMARY KEY (`user_group_id`),
  KEY `user_group_parent_id` (`user_group_parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `tbl_user_group_to_realm`;
CREATE TABLE IF NOT EXISTS `tbl_user_group_to_realm` (
  `ugrr_user_group_id` int(11) NOT NULL,
  `ugrr_realm_id` int(11) NOT NULL,
  `ugrr_role_id` int(11) NOT NULL,
  PRIMARY KEY (`ugrr_user_group_id`,`ugrr_realm_id`),
  KEY `ugrr_role_id` (`ugrr_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `tbl_user_to_realm`;
CREATE TABLE IF NOT EXISTS `tbl_user_to_realm` (
  `urr_user_id` int(11) NOT NULL,
  `urr_realm_id` int(11) NOT NULL,
  `urr_role_id` int(11) NOT NULL,
  PRIMARY KEY (`urr_user_id`,`urr_realm_id`),
  KEY `urr_role_id` (`urr_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `tbl_user_to_user_group`;
CREATE TABLE IF NOT EXISTS `tbl_user_to_user_group` (
  `uug_user_id` int(11) NOT NULL,
  `uug_user_group_id` int(11) NOT NULL,
  KEY `user_id` (`uug_user_id`),
  KEY `user_group_id` (`uug_user_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
