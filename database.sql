CREATE DATABASE IF NOT EXISTS bais_rouilo_gaming_cafe
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE bais_rouilo_gaming_cafe;

-- ========================================
-- USERS / CUSTOMER ACCOUNTS
-- ========================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(120) NOT NULL,

    first_name VARCHAR(80) NOT NULL,

    middle_name VARCHAR(80) DEFAULT NULL,

    last_name VARCHAR(80) NOT NULL,

    email VARCHAR(160) NOT NULL UNIQUE,

    birthdate DATE NOT NULL,

    phone VARCHAR(40) NOT NULL DEFAULT '',

    password VARCHAR(255) NOT NULL,

    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- GAMING SETUPS
-- ========================================

CREATE TABLE IF NOT EXISTS gaming_setups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(120) NOT NULL,

    price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- BOOKINGS
-- ========================================

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    customer_name VARCHAR(120) NOT NULL,

    email VARCHAR(160) DEFAULT NULL,

    phone VARCHAR(40) DEFAULT NULL,

    setup_id INT UNSIGNED NOT NULL,

    booking_date DATE NOT NULL,

    start_time TIME NOT NULL,

    hours TINYINT UNSIGNED NOT NULL DEFAULT 1,

    message VARCHAR(1000) DEFAULT NULL,

    status ENUM(
        'pending',
        'accepted',
        'rejected',
        'completed'
    ) NOT NULL DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bookings_setup
        FOREIGN KEY (setup_id)
        REFERENCES gaming_setups(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- ========================================
-- CUSTOMER REVIEWS
-- ========================================

CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    rating INT NOT NULL,

    review TEXT NOT NULL,

    status ENUM(
        'pending',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_reviews_rating
        CHECK (rating BETWEEN 1 AND 5)
);

-- ========================================
-- CONTACT MESSAGES
-- ========================================

CREATE TABLE IF NOT EXISTS contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(120) NOT NULL,

    email VARCHAR(160) DEFAULT NULL,

    phone VARCHAR(40) DEFAULT NULL,

    message TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- GAMING SETUP DATA
-- ========================================

INSERT INTO gaming_setups
    (id, name, price_per_hour)
VALUES
    (1, 'PC 01', 45.00),
    (2, 'PC 02', 55.00),
    (3, 'PC 03', 60.00),
    (4, 'PC 04', 120.00)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    price_per_hour = VALUES(price_per_hour);