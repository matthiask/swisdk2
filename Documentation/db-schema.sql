CREATE TABLE `tbl_language` (
  `language_id` int(11) NOT NULL auto_increment,
  `language_key` varchar(4) NOT NULL,
  `language_title` varchar(64) NOT NULL,
  `language_locale` varchar(64) NOT NULL,
  PRIMARY KEY  (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tbl_realm` (
  `realm_id` int(11) NOT NULL auto_increment,
  `realm_title` varchar(255) NOT NULL,
  `realm_url` varchar(255) NOT NULL,
  `realm_role_id` int(11) NOT NULL,
  PRIMARY KEY  (`realm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tbl_role` (
  `role_id` int(11) NOT NULL auto_increment,
  `role_title` varchar(32) NOT NULL,
  PRIMARY KEY  (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tbl_user` (
  `user_id` int(10) unsigned NOT NULL auto_increment,
  `user_name` varchar(32) character set latin1 NOT NULL,
  `user_forename` varchar(32) character set latin1 NOT NULL,
  `user_title` varchar(32) character set latin1 NOT NULL,
  `user_email` varchar(64) NOT NULL,
  `user_login` varchar(16) character set latin1 NOT NULL,
  `user_password` varchar(64) character set latin1 NOT NULL,
  PRIMARY KEY  (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tbl_user_group` (
  `user_group_id` int(11) NOT NULL auto_increment,
  `user_group_parent_id` int(11) NOT NULL,
  `user_group_title` varchar(255) NOT NULL,
  PRIMARY KEY  (`user_group_id`),
  KEY `user_group_parent_id` (`user_group_parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tbl_user_group_to_realm` (
  `user_group_id` int(11) NOT NULL,
  `realm_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY  (`user_group_id`,`realm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tbl_user_to_realm` (
  `user_id` int(11) NOT NULL,
  `realm_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY  (`user_id`,`realm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tbl_user_to_user_group` (
  `user_id` int(11) NOT NULL,
  `user_group_id` int(11) NOT NULL,
  KEY `user_id` (`user_id`),
  KEY `user_group_id` (`user_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
