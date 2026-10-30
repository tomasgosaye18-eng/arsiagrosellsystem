-- =========================================================
-- MIGRATION: 2026_07_30_000008_business_audit_trail.sql
-- Purpose  : Extend audit_logs for Business Operational
--            Audit Trail — adds location, role, entity,
--            and description columns for full business
--            traceability across Farm / Warehouse / Shop /
--            Transfers / Products / Users.
-- Safe     : All ALTER ... ADD COLUMN IF NOT EXISTS
-- =========================================================

ALTER TABLE `audit_logs`
    ADD COLUMN IF NOT EXISTS `role_name`        VARCHAR(80)   NULL DEFAULT NULL
        COMMENT 'Role name of acting user at time of action',
    ADD COLUMN IF NOT EXISTS `entity_type`      VARCHAR(80)   NULL DEFAULT NULL
        COMMENT 'Business entity type e.g. harvest, sale, transfer',
    ADD COLUMN IF NOT EXISTS `entity_label`     VARCHAR(255)  NULL DEFAULT NULL
        COMMENT 'Human-readable entity label e.g. product name',
    ADD COLUMN IF NOT EXISTS `description`      VARCHAR(500)  NULL DEFAULT NULL
        COMMENT 'Human-readable description of the action',
    ADD COLUMN IF NOT EXISTS `location_type`    ENUM('farm','warehouse','shop','system','') NULL DEFAULT NULL
        COMMENT 'Which location context the action occurred in',
    ADD COLUMN IF NOT EXISTS `farm_id`          INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'Farm context if applicable',
    ADD COLUMN IF NOT EXISTS `warehouse_id`     INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'Warehouse context if applicable',
    ADD COLUMN IF NOT EXISTS `shop_id`          INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'Shop context if applicable';

-- Indexes for business filters
CREATE INDEX IF NOT EXISTS `idx_audit_role`         ON `audit_logs`(`role_name`);
CREATE INDEX IF NOT EXISTS `idx_audit_entity_type`  ON `audit_logs`(`entity_type`);
CREATE INDEX IF NOT EXISTS `idx_audit_farm`         ON `audit_logs`(`farm_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_warehouse`    ON `audit_logs`(`warehouse_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_shop`         ON `audit_logs`(`shop_id`);
CREATE INDEX IF NOT EXISTS `idx_audit_module_action`ON `audit_logs`(`module`, `action_type`, `action_at`);
