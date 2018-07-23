SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `Queue`;
CREATE TABLE `Queue` (
  `task_id` tinytext NOT NULL,
  `queue_name` tinytext NOT NULL,
  `payload` text NOT NULL,
  `created_timestamp` int(11) NOT NULL,
  `is_pop` tinyint(1) NOT NULL DEFAULT '0',
-- This line must removed for internal-builtin queue
  UNIQUE KEY `task_id` (`task_id`(24))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
