-- 浏览记录表迁移脚本
-- 执行此 SQL 来添加浏览量去重所需的表结构

USE `community_board`;

-- 创建浏览记录表（同一访客对同一条留言只记录一次）
CREATE TABLE IF NOT EXISTS `message_views` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT UNSIGNED NOT NULL COMMENT '留言ID',
    `visitor_id` VARCHAR(64) NOT NULL COMMENT '访客唯一标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '首次浏览时间',
    UNIQUE KEY `uk_visitor_message` (`visitor_id`, `message_id`),
    INDEX `idx_message_id` (`message_id`),
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='留言浏览记录表';

-- 执行完成后，可以通过以下命令验证：
-- SHOW TABLES LIKE 'message_views';
-- DESCRIBE message_views;
