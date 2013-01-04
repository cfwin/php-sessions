DROP TABLE IF EXISTS `session`;
CREATE TABLE `session` (
  `session_id` varchar(60) NOT NULL,
  `data` longtext NOT NULL,
  `ip` varchar(32) NOT NULL,
  `session_time` INT(11) NOT NULL DEFAULT '0',
  `creation_time` datetime NOT NULL DEFAULT '0001-01-01 01:00:00',
  `last_access` datetime NOT NULL DEFAULT '0001-01-01 01:00:00',
  `modification_time` datetime NOT NULL DEFAULT '0001-01-01 01:00:00',
  `modifier_id` int(11) NOT NULL DEFAULT '0',
  `creator_id` int(11) NOT NULL DEFAULT '0',
  `record_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`session_id`)
);