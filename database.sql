CREATE DATABASE IF NOT EXISTS br_gaming_cafe
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE br_gaming_cafe;


-- ========================================
-- USERS / CUSTOMER ACCOUNTS
-- ========================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    phone VARCHAR(40) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ========================================
-- BOOKINGS
-- ========================================

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NULL,

    name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(160),

    station VARCHAR(100) NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    hours TINYINT UNSIGNED NOT NULL DEFAULT 1,

    notes VARCHAR(500),

    status ENUM(
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);


-- ========================================
-- CONTACT MESSAGES
-- ========================================

CREATE TABLE IF NOT EXISTS contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(120) NOT NULL,
    email VARCHAR(160),
    phone VARCHAR(40),
    message TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);