CREATE TABLE IF NOT EXISTS `iris_nieuwsbrieven_lijsten`
(
    `id`           int(9) unsigned NOT NULL AUTO_INCREMENT,
    `naam`         varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
    `active`       enum('ja','nee') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ja',
    `options`      text COLLATE utf8_unicode_ci         DEFAULT NULL,
    `created`      int(11) DEFAULT NULL,
    `modified`     int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;