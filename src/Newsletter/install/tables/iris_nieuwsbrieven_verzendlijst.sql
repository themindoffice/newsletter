CREATE TABLE IF NOT EXISTS `iris_nieuwsbrieven_verzendlijst`
(
    `id`                    int(9) unsigned NOT NULL AUTO_INCREMENT,
    `iris_nieuwsbrieven_id` int(9) NOT NULL,
    `email`                 varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
    `sent_at`               int(11) DEFAULT NULL,
    `created`               int(11) DEFAULT NULL,
    `modified`              int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;