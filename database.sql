-- PDF Library Module Database Schema
-- Run this once to set up the required tables

CREATE DATABASE IF NOT EXISTS pdf_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pdf_library;

CREATE TABLE IF NOT EXISTS pdf_documents (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255)  NOT NULL,
    filename    VARCHAR(255)  NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    page_count  INT UNSIGNED DEFAULT NULL,
    description TEXT          DEFAULT NULL,
    category    VARCHAR(100)  DEFAULT NULL,
    uploaded_by VARCHAR(100)  DEFAULT 'admin',
    uploaded_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    view_count  INT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    INDEX idx_category (category),
    INDEX idx_uploaded_at (uploaded_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pdf_categories (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7)   NOT NULL DEFAULT '#6c757d'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default categories
INSERT IGNORE INTO pdf_categories (name, color) VALUES
    ('Academic',    '#0d6efd'),
    ('Research',    '#6610f2'),
    ('Manuals',     '#0dcaf0'),
    ('Reports',     '#198754'),
    ('General',     '#6c757d');
