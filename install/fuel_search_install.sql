CREATE TABLE `fuel_search` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `location` varchar(255) NOT NULL DEFAULT '',
  `scope` varchar(50) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `content` longtext NOT NULL,
  `excerpt` text NOT NULL,
  `date_added` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `location` (`location`),
  FULLTEXT KEY `title` (`title`,`content`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;