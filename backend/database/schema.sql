-- EventOra Database Schema
-- Generated to match PR1 Data Dictionary exactly (Section 7.1)
-- Phase 1: Must-Have core tables only (9 tables)
-- Remaining tables (feedback, certificates, notifications, favorites)
-- correspond to Should-Have features and will be added in a later migration

CREATE DATABASE IF NOT EXISTS eventora
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE eventora;

-- ============================================
-- 1. users
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('attendee', 'organiser', 'faculty_admin') NOT NULL,
    matric_no VARCHAR(20) NULL,
    phone VARCHAR(20) NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- 2. societies
-- ============================================
CREATE TABLE societies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    logo_url VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_societies_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- 3. society_members
-- ============================================
CREATE TABLE society_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('organiser', 'co_organiser') NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_society_members_society
        FOREIGN KEY (society_id) REFERENCES societies(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_society_members_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    -- Same user shouldn't be added to the same society twice
    UNIQUE KEY uq_society_user (society_id, user_id)
) ENGINE=InnoDB;

-- ============================================
-- 4. events
-- ============================================
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    created_by INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    venue VARCHAR(200) NOT NULL,
    category ENUM('academic', 'sports', 'cultural', 'religious') NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    reg_deadline DATETIME NOT NULL,
    capacity INT NOT NULL,
    fee_type ENUM('free', 'paid') NOT NULL,
    fee_amount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    waitlist_enabled TINYINT(1) NOT NULL DEFAULT 1,
    poster_url VARCHAR(255) NULL,
    contact_person VARCHAR(100) NULL,
    contact_email VARCHAR(150) NULL,
    special_instructions TEXT NULL,
    status ENUM('draft', 'pending_approval', 'published', 'completed', 'rejected', 'cancelled') NOT NULL DEFAULT 'draft',
    cancellation_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_events_society
        FOREIGN KEY (society_id) REFERENCES societies(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_events_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT,

    -- These fields get filtered/searched constantly on the public listing page,
    -- so indexing them keeps that query fast as event count grows
    INDEX idx_events_status (status),
    INDEX idx_events_category (category),
    INDEX idx_events_society (society_id)
) ENGINE=InnoDB;

-- ============================================
-- 5. event_approvals
-- ============================================
CREATE TABLE event_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    reviewed_by INT NOT NULL,
    decision ENUM('approved', 'rejected') NOT NULL,
    reason TEXT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_approvals_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_approvals_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- 6. registrations
-- ============================================
CREATE TABLE registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending_payment', 'confirmed', 'waitlisted', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    waitlist_position INT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,

    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_registrations_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    -- Enforces "duplicate registration prevention" (PR1 5.1 Ticketing Module)
    -- at the database level, not just in application logic
    UNIQUE KEY uq_event_user (event_id, user_id),

    INDEX idx_registrations_status (status)
) ENGINE=InnoDB;

-- ============================================
-- 7. payments
-- ============================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL UNIQUE,
    amount DECIMAL(8,2) NOT NULL,
    status ENUM('pending', 'paid', 'failed', 'refunded_mock') NOT NULL DEFAULT 'pending',
    mock_ref VARCHAR(50) NOT NULL UNIQUE,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- registration_id is UNIQUE above, which is what makes this a true 1:1
    -- relationship as specified in PR1 ("References registrations.id; one-to-one")
    CONSTRAINT fk_payments_registration
        FOREIGN KEY (registration_id) REFERENCES registrations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 8. tickets
-- ============================================
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL UNIQUE,
    qr_token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('active', 'used', 'cancelled') NOT NULL DEFAULT 'active',
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- 1:1 with registrations, same pattern as payments above
    CONSTRAINT fk_tickets_registration
        FOREIGN KEY (registration_id) REFERENCES registrations(id)
        ON DELETE CASCADE,

    -- qr_token is looked up on every QR scan during check-in, must be fast
    INDEX idx_tickets_qr_token (qr_token)
) ENGINE=InnoDB;

-- ============================================
-- 9. check_ins
-- ============================================
CREATE TABLE check_ins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL UNIQUE,
    checked_by INT NOT NULL,
    method ENUM('qr_scan', 'manual_entry') NOT NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- ticket_id is UNIQUE, which enforces "prevents double check-in"
    -- exactly as stated in PR1's description of this entity
    CONSTRAINT fk_checkins_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_checkins_checker
        FOREIGN KEY (checked_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;