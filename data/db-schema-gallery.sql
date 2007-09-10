DROP TABLE IF EXISTS `tbl_gallery_album`;
CREATE TABLE `tbl_gallery_album` (
  `gallery_album_id` int(11) NOT NULL auto_increment,
  `gallery_album_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `gallery_album_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `gallery_album_dttm` int(11) NOT NULL,
  `gallery_album_creation_dttm` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`gallery_album_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `tbl_gallery_image`;
CREATE TABLE `tbl_gallery_image` (
  `gallery_image_id` int(11) NOT NULL auto_increment,
  `gallery_image_gallery_album_id` int(11) NOT NULL,
  `gallery_image_file` varchar(255) collate utf8_unicode_ci NOT NULL,
  `gallery_image_original_file` varchar(255) collate utf8_unicode_ci NOT NULL,
  `gallery_image_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `gallery_image_sortkey` int(11) NOT NULL,
  PRIMARY KEY  (`gallery_image_id`),
  KEY `gallery_image_gallery_album_id` (`gallery_image_gallery_album_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
