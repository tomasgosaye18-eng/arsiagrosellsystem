-- =========================================================
-- Migration: Phase 8B - Product Management Module
-- File: 2026_07_28_000004_phase8b_product_management.sql
-- Purpose: Create complete product master data management schema
-- Dependencies: 2026_07_28_000003_phase8a_admin_dashboard.sql
-- =========================================================
-- Tables Created:
--   1. product_categories
--   2. units_of_measure
--   3. products
--   4. product_images
--   5. product_price_history
-- =========================================================

USE `arsiagro_db`;

-- =========================================================
-- TABLE: product_categories
-- Purpose: Master data for product categorization
-- =========================================================
CREATE TABLE IF NOT EXISTS `product_categories` (
    `category_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Unique category identifier (e.g., CAT001)',
    `category_name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Category display name',
    `description` TEXT NULL COMMENT 'Category description',
    `display_order` INT UNSIGNED DEFAULT 0 COMMENT 'Sort order for display',
    `icon` VARCHAR(50) NULL COMMENT 'Font Awesome icon class (e.g., fa-leaf)',
    `parent_category_id` INT UNSIGNED NULL COMMENT 'For hierarchical categories (future)',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID who created',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL COMMENT 'Soft delete timestamp',
    
    CONSTRAINT `fk_category_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_category_id`) REFERENCES `product_categories`(`category_id`) ON DELETE SET NULL,
    
    INDEX `idx_category_active` (`is_active`, `deleted_at`),
    INDEX `idx_category_display_order` (`display_order`),
    INDEX `idx_category_code` (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product categories master data';

-- =========================================================
-- TABLE: units_of_measure
-- Purpose: Master data for product measurement units
-- =========================================================
CREATE TABLE IF NOT EXISTS `units_of_measure` (
    `unit_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unit_code` VARCHAR(10) NOT NULL UNIQUE COMMENT 'Unique unit identifier (e.g., KG)',
    `unit_name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Full unit name (e.g., Kilogram)',
    `abbreviation` VARCHAR(10) NOT NULL COMMENT 'Short form (e.g., kg)',
    `description` VARCHAR(255) NULL COMMENT 'Unit description or use case',
    `unit_type` ENUM('WEIGHT', 'VOLUME', 'LENGTH', 'PIECE', 'AREA', 'OTHER') DEFAULT 'OTHER' COMMENT 'Unit category',
    `is_base_unit` TINYINT(1) DEFAULT 0 COMMENT '1=Base unit for conversions',
    `conversion_factor` DECIMAL(10,4) NULL COMMENT 'Conversion to base unit (future)',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID who created',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL COMMENT 'Soft delete timestamp',
    
    CONSTRAINT `fk_unit_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    
    INDEX `idx_unit_active` (`is_active`, `deleted_at`),
    INDEX `idx_unit_code` (`unit_code`),
    INDEX `idx_unit_type` (`unit_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Units of measurement master data';

-- =========================================================
-- TABLE: products
-- Purpose: Master product catalog
-- =========================================================
CREATE TABLE IF NOT EXISTS `products` (
    `product_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique product SKU',
    `barcode` VARCHAR(50) UNIQUE NULL COMMENT 'Product barcode (EAN13/Code128)',
    `product_name` VARCHAR(200) NOT NULL COMMENT 'Product display name',
    `scientific_name` VARCHAR(200) NULL COMMENT 'Scientific/botanical name',
    `category_id` INT UNSIGNED NOT NULL COMMENT 'Product category',
    `unit_id` INT UNSIGNED NOT NULL COMMENT 'Base unit of measure',
    `brand` VARCHAR(100) NULL COMMENT 'Product brand/manufacturer',
    `description` TEXT NULL COMMENT 'Detailed product description',
    `specifications` TEXT NULL COMMENT 'Product specs (JSON format)',
    
    -- Pricing
    `cost_price` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Purchase/cost price',
    `selling_price` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Current selling price',
    `markup_percentage` DECIMAL(5,2) NULL COMMENT 'Profit margin %',
    
    -- Stock Control
    `minimum_stock` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Minimum stock level',
    `maximum_stock` DECIMAL(10,2) NULL COMMENT 'Maximum stock capacity',
    `reorder_level` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Reorder trigger level',
    `reorder_quantity` DECIMAL(10,2) NULL COMMENT 'Standard reorder amount',
    
    -- Expiry Management
    `requires_expiry` TINYINT(1) DEFAULT 0 COMMENT '1=Track expiry dates',
    `shelf_life_days` INT NULL COMMENT 'Product shelf life in days',
    `storage_conditions` VARCHAR(255) NULL COMMENT 'Storage requirements',
    
    -- Product Attributes
    `weight` DECIMAL(10,3) NULL COMMENT 'Product weight',
    `weight_unit` VARCHAR(10) NULL COMMENT 'Weight unit (kg/g)',
    `dimensions` VARCHAR(100) NULL COMMENT 'L x W x H',
    `color` VARCHAR(50) NULL COMMENT 'Product color',
    `size` VARCHAR(50) NULL COMMENT 'Product size',
    
    -- Images
    `primary_image` VARCHAR(255) NULL COMMENT 'Primary product image path',
    `has_multiple_images` TINYINT(1) DEFAULT 0 COMMENT '1=Has image gallery',
    
    -- Status & Tracking
    `product_status` ENUM('ACTIVE', 'INACTIVE', 'DISCONTINUED', 'OUT_OF_STOCK', 'ARCHIVED') DEFAULT 'ACTIVE',
    `is_featured` TINYINT(1) DEFAULT 0 COMMENT '1=Featured product',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    
    -- Audit
    `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID who created',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL COMMENT 'User ID who last updated',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL COMMENT 'Soft delete timestamp',
    
    CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`category_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_product_unit` FOREIGN KEY (`unit_id`) REFERENCES `units_of_measure`(`unit_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_product_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_product_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    
    INDEX `idx_product_code` (`product_code`),
    INDEX `idx_product_barcode` (`barcode`),
    INDEX `idx_product_name` (`product_name`),
    INDEX `idx_product_category` (`category_id`, `is_active`),
    INDEX `idx_product_status` (`product_status`, `is_active`),
    INDEX `idx_product_active` (`is_active`, `deleted_at`),
    INDEX `idx_product_reorder` (`reorder_level`, `minimum_stock`),
    FULLTEXT INDEX `ft_product_search` (`product_name`, `product_code`, `barcode`, `brand`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product master catalog';

-- =========================================================
-- TABLE: product_images
-- Purpose: Product image gallery (multiple images per product)
-- =========================================================
CREATE TABLE IF NOT EXISTS `product_images` (
    `image_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL COMMENT 'Related product',
    `image_path` VARCHAR(255) NOT NULL COMMENT 'Image file path',
    `thumbnail_path` VARCHAR(255) NULL COMMENT 'Thumbnail path',
    `image_type` ENUM('PRIMARY', 'GALLERY', 'THUMBNAIL') DEFAULT 'GALLERY',
    `display_order` INT UNSIGNED DEFAULT 0 COMMENT 'Image sort order',
    `file_size` INT UNSIGNED NULL COMMENT 'File size in bytes',
    `mime_type` VARCHAR(50) NULL COMMENT 'Image MIME type',
    `width` INT UNSIGNED NULL COMMENT 'Image width in pixels',
    `height` INT UNSIGNED NULL COMMENT 'Image height in pixels',
    `alt_text` VARCHAR(255) NULL COMMENT 'Image alt text for accessibility',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=Active, 0=Hidden',
    `uploaded_by` INT UNSIGNED NOT NULL COMMENT 'User ID who uploaded',
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_product_image_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_image_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    
    INDEX `idx_product_images_product` (`product_id`, `is_active`),
    INDEX `idx_product_images_type` (`image_type`),
    INDEX `idx_product_images_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product image gallery';

-- =========================================================
-- TABLE: product_price_history
-- Purpose: Track product price changes over time
-- =========================================================
CREATE TABLE IF NOT EXISTS `product_price_history` (
    `price_history_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL COMMENT 'Related product',
    `price_type` ENUM('COST', 'SELLING') NOT NULL COMMENT 'Type of price change',
    `old_price` DECIMAL(15,2) NOT NULL COMMENT 'Previous price',
    `new_price` DECIMAL(15,2) NOT NULL COMMENT 'New price',
    `price_difference` DECIMAL(15,2) GENERATED ALWAYS AS (`new_price` - `old_price`) STORED COMMENT 'Price change amount',
    `percentage_change` DECIMAL(5,2) NULL COMMENT 'Percentage change',
    `effective_date` DATE NOT NULL COMMENT 'Date price becomes effective',
    `reason` VARCHAR(255) NULL COMMENT 'Reason for price change',
    `approved_by` INT UNSIGNED NULL COMMENT 'User who approved (optional workflow)',
    `changed_by` INT UNSIGNED NOT NULL COMMENT 'User who initiated change',
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_price_history_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_price_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_price_history_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    
    INDEX `idx_price_history_product` (`product_id`, `effective_date`),
    INDEX `idx_price_history_type` (`price_type`),
    INDEX `idx_price_history_date` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product price change history';

-- =========================================================
-- SEED DATA: Default Product Categories
-- =========================================================
INSERT INTO `product_categories` (`category_code`, `category_name`, `description`, `display_order`, `icon`, `created_by`, `is_active`) VALUES
('CAT001', 'Grains & Cereals', 'Wheat, Barley, Teff, Maize, Sorghum', 1, 'fa-wheat-awn', 1, 1),
('CAT002', 'Pulses & Legumes', 'Beans, Lentils, Chickpeas, Peas', 2, 'fa-seedling', 1, 1),
('CAT003', 'Vegetables', 'Tomatoes, Onions, Potatoes, Cabbage', 3, 'fa-carrot', 1, 1),
('CAT004', 'Fruits', 'Apples, Bananas, Oranges, Avocados', 4, 'fa-apple-whole', 1, 1),
('CAT005', 'Oilseeds', 'Sesame, Sunflower, Niger Seed, Flax', 5, 'fa-droplet', 1, 1),
('CAT006', 'Spices & Herbs', 'Turmeric, Ginger, Coriander, Fenugreek', 6, 'fa-pepper-hot', 1, 1),
('CAT007', 'Root Crops', 'Potatoes, Sweet Potatoes, Carrots, Beets', 7, 'fa-tree', 1, 1),
('CAT008', 'Coffee & Tea', 'Coffee Beans, Tea Leaves', 8, 'fa-mug-hot', 1, 1),
('CAT009', 'Animal Products', 'Milk, Eggs, Honey, Butter', 9, 'fa-egg', 1, 1),
('CAT010', 'Other Products', 'Miscellaneous agricultural products', 10, 'fa-box', 1, 1);

-- =========================================================
-- SEED DATA: Default Units of Measurement
-- =========================================================
INSERT INTO `units_of_measure` (`unit_code`, `unit_name`, `abbreviation`, `unit_type`, `description`, `is_base_unit`, `created_by`, `is_active`) VALUES
-- Weight Units
('KG', 'Kilogram', 'kg', 'WEIGHT', 'Standard weight unit', 1, 1, 1),
('G', 'Gram', 'g', 'WEIGHT', '1/1000 of a kilogram', 0, 1, 1),
('TON', 'Metric Ton', 'ton', 'WEIGHT', '1000 kilograms', 0, 1, 1),
('QNTL', 'Quintal', 'qtl', 'WEIGHT', '100 kilograms', 0, 1, 1),

-- Volume Units
('L', 'Liter', 'l', 'VOLUME', 'Standard volume unit', 1, 1, 1),
('ML', 'Milliliter', 'ml', 'VOLUME', '1/1000 of a liter', 0, 1, 1),

-- Piece Units
('PC', 'Piece', 'pc', 'PIECE', 'Individual item count', 1, 1, 1),
('DZN', 'Dozen', 'dzn', 'PIECE', '12 pieces', 0, 1, 1),
('CTN', 'Carton', 'ctn', 'PIECE', 'Standard carton', 0, 1, 1),
('PKT', 'Packet', 'pkt', 'PIECE', 'Standard packet', 0, 1, 1),
('BOX', 'Box', 'box', 'PIECE', 'Standard box', 0, 1, 1),
('BTL', 'Bottle', 'btl', 'PIECE', 'Standard bottle', 0, 1, 1),
('BDL', 'Bundle', 'bdl', 'PIECE', 'Standard bundle', 0, 1, 1),
('BAG', 'Bag', 'bag', 'PIECE', 'Standard bag', 0, 1, 1),
('SCK', 'Sack', 'sck', 'PIECE', 'Large sack', 0, 1, 1);

-- =========================================================
-- SAMPLE DATA: Demo Products
-- =========================================================
INSERT INTO `products` (
    `product_code`, `barcode`, `product_name`, `scientific_name`, `category_id`, `unit_id`, 
    `brand`, `description`, `cost_price`, `selling_price`, `minimum_stock`, `reorder_level`,
    `requires_expiry`, `shelf_life_days`, `storage_conditions`, `product_status`, `created_by`
) VALUES
('PRD001', '1234567890123', 'Teff - White', 'Eragrostis tef', 1, 1, 'Arsi Premium', 'Premium quality white teff grain', 45.00, 65.00, 100, 150, 0, NULL, 'Cool, dry place', 'ACTIVE', 1),
('PRD002', '1234567890124', 'Wheat - Soft', 'Triticum aestivum', 1, 1, 'Arsi Premium', 'High quality soft wheat', 30.00, 45.00, 200, 250, 0, NULL, 'Cool, dry place', 'ACTIVE', 1),
('PRD003', '1234567890125', 'Red Kidney Beans', 'Phaseolus vulgaris', 2, 1, 'Arsi Premium', 'Premium red kidney beans', 55.00, 75.00, 150, 200, 0, NULL, 'Cool, dry place', 'ACTIVE', 1),
('PRD004', '1234567890126', 'Chickpeas - Desi', 'Cicer arietinum', 2, 1, 'Arsi Premium', 'Desi variety chickpeas', 60.00, 85.00, 100, 150, 0, NULL, 'Cool, dry place', 'ACTIVE', 1),
('PRD005', '1234567890127', 'Tomatoes - Fresh', 'Solanum lycopersicum', 3, 1, 'Arsi Fresh', 'Fresh red tomatoes', 15.00, 25.00, 50, 75, 1, 7, 'Refrigerate', 'ACTIVE', 1),
('PRD006', '1234567890128', 'Red Onions', 'Allium cepa', 3, 1, 'Arsi Fresh', 'Fresh red onions', 12.00, 20.00, 100, 150, 0, 30, 'Cool, dry place', 'ACTIVE', 1),
('PRD007', '1234567890129', 'Coffee Beans - Arabica', 'Coffea arabica', 8, 1, 'Ethiopian Gold', 'Premium Arabica coffee beans', 250.00, 350.00, 50, 75, 0, NULL, 'Cool, dry, airtight', 'ACTIVE', 1),
('PRD008', '1234567890130', 'Sesame Seeds - White', 'Sesamum indicum', 5, 1, 'Arsi Premium', 'Premium white sesame seeds', 80.00, 120.00, 100, 150, 0, NULL, 'Cool, dry place', 'ACTIVE', 1);

-- =========================================================
-- END OF MIGRATION
-- =========================================================
