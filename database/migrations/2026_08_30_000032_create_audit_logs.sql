-- =========================================================
-- AAM — Create audit_logs table
-- Date: 2026-08-30
-- =========================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED     NULL     DEFAULT NULL,
  `role_name`      VARCHAR(80)      NULL     DEFAULT NULL,
  `action_type`    VARCHAR(50)      NOT NULL,
  `module`         VARCHAR(60)      NOT NULL DEFAULT '',
  `entity_type`    VARCHAR(80)      NULL     DEFAULT NULL,
  `entity_label`   VARCHAR(255)     NULL     DEFAULT NULL,
  `description`    VARCHAR(500)     NULL     DEFAULT NULL,
  `table_affected` VARCHAR(80)      NULL     DEFAULT NULL,
  `record_id`      INT UNSIGNED     NULL     DEFAULT NULL,
  `old_values`     LONGTEXT         NULL     DEFAULT NULL,
  `new_values`     LONGTEXT         NULL     DEFAULT NULL,
  `location_type`  ENUM('farm','warehouse','shop','system','') NULL DEFAULT NULL,
  `farm_id`        INT UNSIGNED     NULL     DEFAULT NULL,
  `warehouse_id`   INT UNSIGNED     NULL     DEFAULT NULL,
  `shop_id`        INT UNSIGNED     NULL     DEFAULT NULL,
  `ip_address`     VARCHAR(45)      NULL     DEFAULT NULL,
  `user_agent`     VARCHAR(500)     NULL     DEFAULT NULL,
  `action_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_id` (`user_id`),
  KEY `idx_audit_module`  (`module`),
  KEY `idx_audit_at`      (`action_at`),
  CONSTRAINT `fk_auditl_user`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
