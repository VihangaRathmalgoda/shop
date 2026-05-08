-- ============================================================
-- ONLINE CLOTHING STORE - DATABASE SCHEMA
-- Created for Sri Lankan Online Clothing Business
-- ============================================================

CREATE DATABASE IF NOT EXISTS clothing_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clothing_store;

-- ============================================================
-- SITE SETTINGS (Logo, Name, Contact, Payment Gateways, Theme)
-- ============================================================
CREATE TABLE site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text','image','boolean','json','color') DEFAULT 'text',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'My Fashion Store', 'text'),
('site_logo', '', 'image'),
('site_favicon', '', 'image'),
('site_tagline', 'Style for Everyone', 'text'),
('contact_whatsapp', '+94771234567', 'text'),
('contact_email', 'info@myfashionstore.lk', 'text'),
('contact_phone', '+94771234567', 'text'),
('contact_address', 'Colombo, Sri Lanka', 'text'),
('facebook_url', '', 'text'),
('instagram_url', '', 'text'),
('tiktok_url', '', 'text'),
('active_theme', 'default', 'text'),
('payhere_enabled', '0', 'boolean'),
('payhere_merchant_id', '', 'text'),
('payhere_merchant_secret', '', 'text'),
('payhere_sandbox', '1', 'boolean'),
('koko_enabled', '0', 'boolean'),
('koko_api_key', '', 'text'),
('koko_api_secret', '', 'text'),
('koko_sandbox', '1', 'boolean'),
('cod_enabled', '1', 'boolean'),
('whatsapp_orders_enabled', '1', 'boolean'),
('portal_orders_enabled', '1', 'boolean'),
('currency_symbol', 'Rs.', 'text'),
('shipping_fee', '350', 'text'),
('free_shipping_above', '5000', 'text'),
('meta_description', 'Online clothing store in Sri Lanka', 'text'),
('meta_keywords', 'clothing, fashion, sri lanka, online shopping', 'text');

-- ============================================================
-- COLOR THEMES (Aurudu, Christmas, Vesak, Default, etc.)
-- ============================================================
CREATE TABLE color_themes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    theme_key VARCHAR(50) UNIQUE NOT NULL,
    theme_name VARCHAR(100) NOT NULL,
    primary_color VARCHAR(20) DEFAULT '#2c3e50',
    secondary_color VARCHAR(20) DEFAULT '#e74c3c',
    accent_color VARCHAR(20) DEFAULT '#f39c12',
    bg_color VARCHAR(20) DEFAULT '#ffffff',
    text_color VARCHAR(20) DEFAULT '#333333',
    navbar_color VARCHAR(20) DEFAULT '#2c3e50',
    footer_color VARCHAR(20) DEFAULT '#1a252f',
    button_color VARCHAR(20) DEFAULT '#e74c3c',
    badge_color VARCHAR(20) DEFAULT '#f39c12',
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO color_themes (theme_key, theme_name, primary_color, secondary_color, accent_color, bg_color, text_color, navbar_color, footer_color, button_color, badge_color, is_active) VALUES
('default', 'Default', '#2c3e50', '#e74c3c', '#f39c12', '#f8f9fa', '#333333', '#2c3e50', '#1a252f', '#e74c3c', '#f39c12', TRUE),
('aurudu', 'Sinhala & Tamil New Year (Aurudu)', '#8B0000', '#FF8C00', '#FFD700', '#FFF8E1', '#333333', '#8B0000', '#5D0000', '#FF8C00', '#FFD700', FALSE),
('christmas', 'Christmas', '#1a5c1a', '#cc0000', '#FFD700', '#f0fff0', '#222222', '#1a5c1a', '#0d3d0d', '#cc0000', '#FFD700', FALSE),
('vesak', 'Vesak', '#6A0DAD', '#FFD700', '#FF8C00', '#FAF0FF', '#333333', '#4B0082', '#2E0057', '#6A0DAD', '#FFD700', FALSE),
('valentine', "Valentine's Day", '#8B0032', '#FF1493', '#FF69B4', '#FFF0F5', '#333333', '#8B0032', '#5D001E', '#FF1493', '#FF69B4', FALSE),
('eid', 'Eid / Ramadan', '#006400', '#DAA520', '#8B6914', '#FAFFF0', '#333333', '#004d00', '#003300', '#DAA520', '#006400', FALSE),
('blackfriday', 'Black Friday / Sale', '#000000', '#FFD700', '#FF4500', '#111111', '#EEEEEE', '#000000', '#000000', '#FFD700', '#FF4500', FALSE);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

INSERT INTO categories (name, slug, description, sort_order) VALUES
('Men', 'men', 'Men\'s Clothing', 1),
('Women', 'women', 'Women\'s Clothing', 2),
('Kids', 'kids', 'Kids Clothing', 3),
('Accessories', 'accessories', 'Fashion Accessories', 4),
('T-Shirts', 't-shirts', 'All T-Shirts', 5),
('Dresses', 'dresses', 'All Dresses', 6),
('Trousers', 'trousers', 'Trousers & Pants', 7),
('Sarees', 'sarees', 'Traditional Sarees', 8);

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    category_id INT,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    sale_price DECIMAL(10,2) DEFAULT NULL,
    is_on_sale BOOLEAN DEFAULT FALSE,
    discount_percent INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    is_new_arrival BOOLEAN DEFAULT TRUE,
    status ENUM('active','inactive','out_of_stock') DEFAULT 'active',
    meta_title VARCHAR(200),
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ============================================================
-- PRODUCT COLORS
-- ============================================================
CREATE TABLE product_colors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    color_name VARCHAR(50) NOT NULL,
    color_hex VARCHAR(10) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================================
-- PRODUCT SIZES
-- ============================================================
CREATE TABLE product_sizes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    size_label VARCHAR(20) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================================
-- PRODUCT STOCK (per color + size combination)
-- ============================================================
CREATE TABLE product_stock (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    color_id INT NOT NULL,
    size_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_stock (product_id, color_id, size_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES product_colors(id) ON DELETE CASCADE,
    FOREIGN KEY (size_id) REFERENCES product_sizes(id) ON DELETE CASCADE
);

-- ============================================================
-- PRODUCT IMAGES (per color)
-- ============================================================
CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    color_id INT DEFAULT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES product_colors(id) ON DELETE SET NULL
);

-- ============================================================
-- OFFERS / PROMOTIONS
-- ============================================================
CREATE TABLE offers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    offer_name VARCHAR(100) NOT NULL,
    offer_code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percent','fixed') DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_discount_amount DECIMAL(10,2) DEFAULT NULL,
    applies_to ENUM('all','category','product') DEFAULT 'all',
    applies_to_id INT DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- BANNERS (Carousel)
-- ============================================================
CREATE TABLE banners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200),
    subtitle TEXT,
    button_text VARCHAR(100),
    button_link VARCHAR(255),
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- CUSTOMERS
-- ============================================================
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    whatsapp VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- ============================================================
-- ORDERS
-- ============================================================
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(200) NOT NULL,
    customer_email VARCHAR(150),
    customer_phone VARCHAR(20),
    customer_whatsapp VARCHAR(20),
    delivery_address TEXT NOT NULL,
    delivery_city VARCHAR(100),
    delivery_postal VARCHAR(20),
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    shipping_fee DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    offer_id INT DEFAULT NULL,
    offer_code VARCHAR(50),
    payment_method ENUM('whatsapp_cod','cod','payhere','koko','bank_transfer') DEFAULT 'whatsapp_cod',
    payment_status ENUM('pending','slip_uploaded','verified','failed','refunded') DEFAULT 'pending',
    payment_id VARCHAR(200),
    payment_slip VARCHAR(255),
    order_status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned') DEFAULT 'pending',
    order_source ENUM('whatsapp','portal','admin') DEFAULT 'whatsapp',
    notes TEXT,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE SET NULL
);

-- ============================================================
-- ORDER ITEMS
-- ============================================================
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_code VARCHAR(50),
    product_name VARCHAR(200),
    color_name VARCHAR(50),
    size_label VARCHAR(20),
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- ============================================================
-- ORDER STATUS HISTORY
-- ============================================================
CREATE TABLE order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    changed_by ENUM('admin','customer','system') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ============================================================
-- ADMIN USERS
-- ============================================================
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(200),
    role ENUM('superadmin','admin','staff') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin (password: Admin@1234)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@store.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Store Admin', 'superadmin');

-- ============================================================
-- WISHLIST
-- ============================================================
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (customer_id, product_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================================
-- CART (for portal users)
-- ============================================================
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    product_id INT NOT NULL,
    color_id INT NOT NULL,
    size_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================================
-- REVIEWS
-- ============================================================
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_products_featured ON products(is_featured);
CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(order_status);
CREATE INDEX idx_orders_payment ON orders(payment_status);
CREATE INDEX idx_orders_number ON orders(order_number);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_stock_product ON product_stock(product_id);

-- ============================================================
-- VIEWS
-- ============================================================
CREATE VIEW v_product_summary AS
SELECT 
    p.*,
    c.name AS category_name,
    c.slug AS category_slug,
    (SELECT SUM(ps.quantity) FROM product_stock ps WHERE ps.product_id = p.id) AS total_stock,
    (SELECT COUNT(*) FROM product_colors pc WHERE pc.product_id = p.id) AS color_count,
    (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) AS avg_rating,
    (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) AS review_count,
    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image
FROM products p
LEFT JOIN categories c ON p.category_id = c.id;

CREATE VIEW v_order_summary AS
SELECT 
    o.*,
    COUNT(oi.id) AS item_count
FROM orders o
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id;

-- ////////////////////////////new update

-- ============================================================
-- PASSWORD FIX — Run this if you already imported database.sql
-- and cannot login with admin / Admin@1234
-- ============================================================
USE clothing_store;

-- Fix admin password to: Admin@1234
UPDATE admin_users 
SET password_hash = '$2y$10$xX75K3RCbG/vNlfM7CbJnumt/IF6b/N6uUul/qd9.gdCYPHuLLP5y'
WHERE username = 'admin';

-- Verify the update
SELECT id, username, email, full_name, role, 
       CASE WHEN password_hash LIKE '$2y$%' THEN 'Hash looks correct' ELSE 'Hash issue' END as hash_status
FROM admin_users 
WHERE username = 'admin';

-- ============================================================
-- After running this, login with:
-- Username: admin
-- Password: Admin@1234
-- ============================================================

USE clothing_store;
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(150) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, attempted_at),
  INDEX idx_user_time (username, attempted_at)
);