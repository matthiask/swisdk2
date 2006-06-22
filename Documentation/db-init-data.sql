-- insert default set of values into database

INSERT INTO `tbl_language` VALUES (1,'de','deutsch'),(2,'fr','fran&ccedil;ais'),(3,'en','english');
INSERT INTO `tbl_realm` VALUES (1,'Root','',1),(2,'Administration','admin',4);
INSERT INTO `tbl_role` VALUES (1,'Visitor'),(2,'Authenticated'),(3,'Member'),(4,'Manager'),(5,'Administrator'),(6,'SiteAdministrator');
INSERT INTO `tbl_user` VALUES (1,'Visitor','','','visitor@spinlock.ch','',''),(2,'Admin','','Admin','admin@example.com','admin',MD5('password'));
INSERT INTO `tbl_user_group` VALUES (1,0,'Root'),(2,1,'Administrators');
INSERT INTO `tbl_user_group_to_realm` VALUES (2,1,5),(2,2,5);
INSERT INTO `tbl_user_to_realm` VALUES (1,1,1);
INSERT INTO `tbl_user_to_user_group` VALUES (2,2);
