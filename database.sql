SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `session_code` varchar(10) NOT NULL,
  `session_name` varchar(255) NOT NULL,
  `max_players` int(11) NOT NULL DEFAULT 10,
  `vote_rule` enum('strict','moyenne','mediane','majorite_absolue','majorite_relative') DEFAULT 'strict',
  `current_story_id` int(11) DEFAULT NULL,
  `status` enum('waiting','voting','revealed','finished') DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `pseudo` varchar(100) NOT NULL,
  `is_scrum_master` tinyint(1) DEFAULT 0,
  `is_connected` tinyint(1) DEFAULT 1,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session_saves` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `save_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`save_data`)),
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_stories` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `story_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('haute','moyenne','basse') DEFAULT 'moyenne',
  `estimation` int(11) DEFAULT NULL,
  `status` enum('pending','voting','estimated') DEFAULT 'pending',
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `vote_value` varchar(10) NOT NULL,
  `vote_round` int(11) NOT NULL DEFAULT 1,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_code` (`session_code`),
  ADD KEY `idx_session_code` (`session_code`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_player_session` (`session_id`,`pseudo`),
  ADD KEY `idx_session_player` (`session_id`,`pseudo`);

ALTER TABLE `session_saves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_save` (`session_id`,`saved_at`);

ALTER TABLE `user_stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_story` (`session_id`,`order_index`);

ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vote_round` (`session_id`,`story_id`,`player_id`,`vote_round`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `idx_story_round` (`story_id`,`vote_round`);

ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `session_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `players`
  ADD CONSTRAINT `players_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE;

ALTER TABLE `session_saves`
  ADD CONSTRAINT `session_saves_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_stories`
  ADD CONSTRAINT `user_stories_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE;

ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`story_id`) REFERENCES `user_stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE;

COMMIT;
