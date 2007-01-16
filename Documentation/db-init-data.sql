INSERT INTO `tbl_language` (`language_id`, `language_key`, `language_title`, `language_locale`) VALUES 
(1, 'd', 'deutsch', 'de_CH.UTF-8;de_CH;de');

INSERT INTO `tbl_realm` (`realm_id`, `realm_title`, `realm_url`, `realm_role_id`) VALUES 
(1, 'Root', '', 1),
(2, 'Administration', 'admin', 4);

INSERT INTO `tbl_role` (`role_id`, `role_title`) VALUES 
(1, 'Visitor'),
(2, 'Authenticated'),
(3, 'Member'),
(4, 'Manager'),
(5, 'Administrator'),
(6, 'SiteAdministrator');

INSERT INTO `tbl_user` (`user_id`, `user_name`, `user_forename`, `user_title`, `user_email`, `user_login`, `user_password`) VALUES 
(1, 'Visitor', '', '', 'visitor@spinlock.ch', '', ''),
(2, 'Admin', '', 'Admin', 'admin@example.com', 'admin', MD5('password'));

INSERT INTO `tbl_user_group` (`user_group_id`, `user_group_parent_id`, `user_group_title`) VALUES 
(1, 0, 'Root'),
(2, 1, 'Administrators');

INSERT INTO `tbl_user_group_to_realm` (`ugrr_user_group_id`, `ugrr_realm_id`, `ugrr_role_id`) VALUES 
(2, 1, 5),
(2, 2, 5);

INSERT INTO `tbl_user_to_realm` (`urr_user_id`, `urr_realm_id`, `urr_role_id`) VALUES 
(1, 1, 1);

INSERT INTO `tbl_user_to_user_group` (`uug_user_id`, `uug_user_group_id`) VALUES 
(2, 2);
