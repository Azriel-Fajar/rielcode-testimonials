-- Migration: testimonials table
-- DB: rielcode (local) / rier5192_rielcode (production)
-- Run once. Safe to re-run with IF NOT EXISTS check below.

CREATE TABLE IF NOT EXISTS testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_name VARCHAR(80) NOT NULL,
  business_name VARCHAR(100) NOT NULL,
  role_title VARCHAR(80) NOT NULL,
  rating TINYINT NOT NULL,
  project_url VARCHAR(255) NOT NULL,
  problem_before TEXT NOT NULL,
  solution_after TEXT NOT NULL,
  recommendation TEXT NOT NULL,
  headline VARCHAR(120) DEFAULT NULL,
  client_email VARCHAR(120) DEFAULT NULL,
  consent_given TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration v2: one-time access tokens
-- Run this block on existing installs (safe to run multiple times).
ALTER TABLE testimonials
  ADD COLUMN IF NOT EXISTS invite_token VARCHAR(64) DEFAULT NULL UNIQUE,
  ADD COLUMN IF NOT EXISTS token_used TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS testimonial_invites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  label VARCHAR(200) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME DEFAULT NULL,
  testimonial_id INT DEFAULT NULL,
  CONSTRAINT fk_invite_testi FOREIGN KEY (testimonial_id) REFERENCES testimonials(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
