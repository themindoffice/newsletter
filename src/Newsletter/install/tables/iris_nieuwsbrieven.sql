CREATE TABLE IF NOT EXISTS `iris_nieuwsbrieven`
(
    `id`                int(9) unsigned NOT NULL AUTO_INCREMENT,
    `naam`              varchar(255) COLLATE utf8_unicode_ci        DEFAULT NULL,
    `omschrijving`      text COLLATE utf8_unicode_ci                DEFAULT NULL,
    `foto`              text COLLATE utf8_unicode_ci                DEFAULT NULL,
    `onderwerpregel`    varchar(255) COLLATE utf8_unicode_ci        DEFAULT NULL,
    `lijsten_id`        varchar(255) COLLATE utf8_unicode_ci        DEFAULT NULL,
    `btn_link`          varchar(255) COLLATE utf8_unicode_ci        DEFAULT NULL,
    `btn_text`          varchar(255) COLLATE utf8_unicode_ci        DEFAULT NULL,
    `active`            enum('ja','nee') COLLATE utf8_unicode_ci    NOT NULL DEFAULT 'ja',
    `options`           text COLLATE utf8_unicode_ci                DEFAULT NULL,
    `html`              longtext COLLATE utf8_unicode_ci            DEFAULT NULL,
    `sent_at`           int(11) DEFAULT NULL,
    `created`           int(11) DEFAULT NULL,
    `modified`          int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;