-- EventOra — Test Seed Data for Admin Approval API
--
-- WHY THIS FILE EXISTS:
-- The Admin Approval API (GET /api/admin/events/pending,
-- POST /api/admin/events/{id}/approve, POST /api/admin/events/{id}/reject)
-- needs rows in the `events` table to operate on. At the time this file
-- was written, the Event Controller (owned by Christ — Backend & API Lead)
-- had not been built yet, so the `events` and `societies` tables were empty.
--
-- This script manually inserts placeholder data ONLY so the Admin Approval
-- API can be developed and tested in isolation. It does NOT replace the
-- real Event Controller. Once Christ's EventController is merged, real
-- events will be created through the API instead of this script, and this
-- seed data can simply be deleted from the database (the schema and the
-- Admin Approval endpoints do not depend on this file in any way).
--
-- Assumes the following already exist (per project notes):
--   users.id = 1  -> testuser@example.com, role = organiser
--   users.id = 2  -> admin@example.com,    role = faculty_admin
--
-- Run this AFTER schema.sql has already created the tables.

USE eventora;

-- ============================================
-- 1. Seed one society
-- ============================================
-- events.society_id is a NOT NULL foreign key referencing societies.id,
-- so at least one society row must exist before any event can be inserted.
-- created_by points to user id=1 (organiser), since societies are created
-- under an organiser/admin context per the data dictionary.
INSERT INTO societies (name, description, logo_url, created_by)
VALUES (
    'Computing Society',
    'Official student society for the Faculty of Computing',
    NULL,
    1
);

-- ============================================
-- 2. Seed test events
-- ============================================

-- Event #1: status = pending_approval
-- This is the primary record your GET /api/admin/events/pending
-- endpoint should return.
INSERT INTO events (
    society_id, created_by, title, description, venue, category,
    start_datetime, end_datetime, reg_deadline, capacity,
    fee_type, fee_amount, waitlist_enabled, status
) VALUES (
    1, 1,
    'AI Workshop: Intro to Machine Learning',
    'A hands-on workshop covering ML fundamentals for beginners.',
    'N28 Lecture Hall', 'academic',
    '2026-07-10 14:00:00', '2026-07-10 17:00:00', '2026-07-08 23:59:59',
    50, 'free', 0.00, 1, 'pending_approval'
);

-- Event #2: status = pending_approval
-- A second pending event so the pending list returns more than one row,
-- confirming the query isn't accidentally hardcoded to a single result.
INSERT INTO events (
    society_id, created_by, title, description, venue, category,
    start_datetime, end_datetime, reg_deadline, capacity,
    fee_type, fee_amount, waitlist_enabled, status
) VALUES (
    1, 1,
    'Charity Fun Run 2026',
    'A 5km charity run open to all students, proceeds go to local shelters.',
    'UTM Sports Complex', 'sports',
    '2026-08-01 07:00:00', '2026-08-01 10:00:00', '2026-07-28 23:59:59',
    200, 'paid', 15.00, 1, 'pending_approval'
);

-- Event #3: status = published
-- This one should NOT appear in the pending list. Use it to verify the
-- WHERE status = 'pending_approval' filter is actually working, instead
-- of accidentally returning every event regardless of status.
INSERT INTO events (
    society_id, created_by, title, description, venue, category,
    start_datetime, end_datetime, reg_deadline, capacity,
    fee_type, fee_amount, waitlist_enabled, status
) VALUES (
    1, 1,
    'Cultural Night: Malaysia Heritage Showcase',
    'An evening celebrating Malaysia''s diverse cultural heritage through performances.',
    'Dewan Sultan Iskandar', 'cultural',
    '2026-07-20 19:00:00', '2026-07-20 22:00:00', '2026-07-18 23:59:59',
    300, 'free', 0.00, 1, 'published'
);