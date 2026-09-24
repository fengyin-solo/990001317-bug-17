-- 浏览记录表迁移脚本
-- 作用：记录每个访客对每条留言的有效浏览，用于浏览量去重
-- 执行此 SQL 来添加浏览量去重所需的表结构

USE `community_board`;

-- 创建浏览记录表（同一访客对同一条留言只记录一次）
CREATE TABLE IF NOT EXISTS `message_views` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` VARCHAR(64) NOT NULL COMMENT '访客唯一标识',
    `message_id` INT UNSIGNED NOT NULL COMMENT '留言ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '首次浏览时间',
    UNIQUE KEY `uk_visitor_message` (`visitor_id`, `message_id`),
    INDEX `idx_visitor_id` (`visitor_id`),
    INDEX `idx_message_id` (`message_id`),
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='留言浏览记录表（按访客去重）';

-- 浏览量字段索引（热门排序使用；若已存在会报错，可忽略或先检查）
ALTER TABLE `messages` ADD INDEX `idx_views` (`views`);

-- 执行完成后，可以通过以下命令验证：
-- SHOW TABLES LIKE 'message_views';
-- DESCRIBE message_views;
