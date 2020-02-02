CREATE TABLE `characters` (
  `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_seen` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `last_seen` TIMESTAMP NULL DEFAULT NULL,
  `first_seen_level` INTEGER NOT NULL
);

CREATE TABLE `exp_records` (
  `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `character_id` INTEGER NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `online` TINYINT(1),
  `level` INTEGER NOT NULL,
  `calculated_exp` BIGINT NOT NULL
);
