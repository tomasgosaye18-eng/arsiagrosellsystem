# AAM Database Migrations

## Execution Order

Run these SQL files in the **exact order** listed below using phpMyAdmin or MySQL CLI.

| Order | File | Phase | Description |
|-------|------|-------|-------------|
| 1 | `../../database/aam_core_schema.sql` | Core | Roles, users, farms, shops, warehouses, products, categories, units, prices, system_settings |
| 2 | `../../database/aam_inventory_schema.sql` | Core | farm_inventory, warehouse_inventory, shop_inventory, stock_movement_log |
| 3 | `../../database/aam_pos_schema.sql` | Core | sales, sale_items, receipts |
| 4 | `../../database/aam_final_schema.sql` | Core | harvests, dispatches, transfers, audit_logs, notifications |
| 5 | `2026_07_28_000001_auth_enhancements.sql` | 7 | account_status, profile_photo, reset tokens, remember_me_tokens |
| 6 | `2026_07_28_000002_login_history_enhancements.sql` | 7 | browser, OS, device, logout tracking on login_history |
| 7 | `2026_07_28_000003_phase8a_admin_dashboard.sql` | 8A | employee_id, middle_name, gender, email on users; activity_logs table |
| 8 | `2026_07_28_000004_phase8b_product_management.sql` | 8B | product_categories, units_of_measure, products, product_images, product_price_history |
| 9 | `2026_07_29_000005_phase8c_organization_management.sql` | 8C | regions, zones, cities, campuses, buildings, farms, warehouses, shops, organization_assignments, assignment_history |
| 10 | `2026_07_29_000006_phase8d_system_config.sql` | 8D | company_profile, system_settings, notification_settings, backup_history, import_export_history, error_logs, company_profile_history |
| 11 | `2026_07_29_000007_xampp_integration_checkpoint.sql` | 9 | XAMPP integration checkpoint |
| 12 | `2026_07_30_000008_business_audit_trail.sql` | 9 | audit_logs business trail columns (role_name, entity_type, location fields) |
| 13 | `2026_07_30_000009_phase9a_farm_manager_seed.sql` | 9A | Farm manager seed data |
| 14 | `2026_07_31_000010_phase9a_farm_module.sql` | 9A | harvest_batches, farm_dispatches, farm_dispatch_items |
| 15 | `2026_07_31_000011_manager_admin_integration.sql` | 9B | Manager/admin integration |
| 16 | `2026_07_31_000012_phase10_warehouse_operations.sql` | 10 | warehouse_inventory, warehouse_receiving, warehouse_transfers, shop_inventory, stock_movement_log |
| 17 | `2026_08_01_000013_phase11_inventory_transfer.sql` | 11 | inventory_batches, stock_adjustments, stock_counts, stock_count_items; extends shop_inventory |
| 18 | `2026_08_01_000014_phase12_shop_pos.sql` | 12 | seller_shifts, sales, sale_items; Phase 12 permissions for POS & shop roles |
| 19 | `2026_08_01_000015_phase12_shop_enhancements.sql` | 12 | Adds shops.shop_code, shops.phone, shops.email; backfills shop codes |
| 20 | `2026_08_01_000016_phase12_demo_users.sql` | 12 | Seeds all role demo users with verified bcrypt passwords |
| … | *(21–37 — see filenames)* | 13–16 | Transfer workflow, shop receiving, discrepancy workflow, mobile payments, payment methods |
| 38 | `2026_08_16_000038_roles_permissions_seed.sql` | RBAC | Seeds all core permission codes + correct role_permissions for all 5 roles. Fixes 0-permission display. |
| 39 | `2026_08_16_000039_fix_admin_gm_permissions.sql` | RBAC | Enforces strict System Admin vs General Manager separation. Admin = system only (34 perms). GM = business only (60 perms). |
| 40 | `2026_08_20_000040_phase5_customer_orders.sql` | 5 | customer_orders, customer_order_items; mobile_payments.customer_order_id; Phase 5 permissions |
| 41 | `2026_08_21_000041_phase6_shop_order_management.sql` | 6 | customer_order_status_changes; audit_logs index; search indexes; Phase 6 permissions |
| 42 | `2026_08_23_000042_phase7_mobile_banking_qr_payment.sql` | 7 | Expand payment_reference to VARCHAR(40) for AUS-PAY format; display_name on payment_methods; polling performance indexes |

## Quick Setup — Run All Migrations

```sql
-- Run in phpMyAdmin SQL tab, in order:
SOURCE C:/xampp/htdocs/aam/database/aam_core_schema.sql;
SOURCE C:/xampp/htdocs/aam/database/aam_inventory_schema.sql;
SOURCE C:/xampp/htdocs/aam/database/aam_pos_schema.sql;
SOURCE C:/xampp/htdocs/aam/database/aam_final_schema.sql;
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_28_000001_auth_enhancements.sql;
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_28_000002_login_history_enhancements.sql;
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_28_000003_phase8a_admin_dashboard.sql;
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_28_000004_phase8b_product_management.sql;
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_29_000005_phase8c_organization_management.sql;
SOURCE C:/xampp/htdocs/aam/app/database/migrations/2026_07_29_000006_phase8d_system_config.sql;
```

## Default Admin Account

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `Admin@123` |
| Must change password | No |

## All Demo Accounts

| Role | Username | Password | Assigned To |
|------|----------|----------|-------------|
| System Administrator | `admin` | `Admin@123` | — (system-wide) |
| General Manager | `gm_01` | `GenMgr@2026` | — (business-wide) |
| Farm Manager | `farm_manager_01` | `FarmMgr@2026` | Farm 1 (Main Farm) |
| Warehouse Manager | `wh_manager_01` | `WHMgr@2026` | Central Warehouse |
| Salesperson | `seller_01` | `Seller@2026` | Shop 1 (Main Campus) |
| Salesperson | `seller_02` | `Seller@2026` | Shop 2 (Health Campus) |

> Storekeeper role was permanently removed in migration 24 (`2026_08_05_000024_remove_storekeeper_role.sql`).  
> All passwords are bcrypt cost-12 hashes. Change all passwords after first login in production.

## Phase 8D Tables Created

| Table | Purpose |
|-------|---------|
| `company_profile` | University name, logo, contact info (single row) |
| `system_settings` | All application config as key-value pairs |
| `notification_settings` | Per-type notification enable/disable/threshold |
| `backup_history` | All backup operations, checksums, status |
| `import_export_history` | All import/export operations log |
| `error_logs` | Structured DB-level error and event log |
| `company_profile_history` | Audit trail of every company profile change |

## Notes

- All migrations use `IF NOT EXISTS` — safe to run multiple times.
- `FOREIGN_KEY_CHECKS` is disabled at start and re-enabled at end of each migration.
- The Phase 8D migration seeds: default company profile, all system settings defaults, all notification type settings.
- After running migration 10, visit `/settings` to verify the admin center loads.
