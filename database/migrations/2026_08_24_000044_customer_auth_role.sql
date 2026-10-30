-- =========================================================
-- Migration: Customer Authentication Role
-- =========================================================
-- Adds the 'customer' role to the roles table.
-- Uses INSERT IGNORE so this migration is safe to run
-- multiple times without duplicating data.
-- Role ID 7 is the next available value after:
--   1=administrator, 2=farm_manager, 3=warehouse_manager,
--   5=salesperson, 6=general_manager
-- =========================================================

INSERT IGNORE INTO `roles` (`id`, `name`, `display_name`, `description`, `is_active`, `created_at`)
VALUES (7, 'customer', 'Customer', 'E-commerce storefront customer — access to browsing, cart, checkout, orders only.', 1, NOW());

-- Verify the role was inserted or already exists
SELECT id, name, display_name, is_active FROM `roles` WHERE name = 'customer';
