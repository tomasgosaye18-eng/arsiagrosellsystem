-- =========================================================
-- Migration: Drop Location Hierarchy Tables
-- File: 2026_08_07_000025_drop_location_tables.sql
-- Purpose: Remove unused location hierarchy tables:
--          regions, zones, cities, campuses, buildings.
--          Also remove FK columns from farms, warehouses,
--          and shops that reference those tables.
-- Run in: aam_db
-- =========================================================

USE `aam_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ── Remove FK columns from farms ─────────────────────────
ALTER TABLE `farms`
    DROP COLUMN IF EXISTS `campus_id`,
    DROP COLUMN IF EXISTS `city_id`;

-- ── Remove FK columns from warehouses ────────────────────
ALTER TABLE `warehouses`
    DROP COLUMN IF EXISTS `campus_id`,
    DROP COLUMN IF EXISTS `building_id`,
    DROP COLUMN IF EXISTS `city_id`;

-- ── Remove FK columns from shops ─────────────────────────
ALTER TABLE `shops`
    DROP COLUMN IF EXISTS `campus_id`,
    DROP COLUMN IF EXISTS `building_id`,
    DROP COLUMN IF EXISTS `city_id`;

-- ── Drop location tables (order: child → parent) ─────────
DROP TABLE IF EXISTS `buildings`;
DROP TABLE IF EXISTS `campuses`;
DROP TABLE IF EXISTS `cities`;
DROP TABLE IF EXISTS `zones`;
DROP TABLE IF EXISTS `regions`;

SET FOREIGN_KEY_CHECKS = 1;
