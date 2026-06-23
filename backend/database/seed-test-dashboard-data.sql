-- EventOra — Test Seed Data for Organiser Dashboard API
--
-- WHY THIS FILE EXISTS:
-- GET /api/dashboard/organiser (PR1 Appendix A.4) needs to compute event
-- totals, registration counts, attendance rate, and capacity use. Those
-- numbers only make sense if there's data in `registrations`, `tickets`,
-- and `check_ins` - all of which were empty at the time this file was
-- written, since the real registration/ticketing flow (owned by Jia Hui -
-- Integration & Mobile Lead) had not been built yet.
--
-- This script is the SAME kind of placeholder as seed-test-events.sql:
-- it exists ONLY to unblock development and testing of the Organiser
-- Dashboard API in isolation. It does NOT replace the real registration
-- flow. Once Jia Hui's registration/ticketing endpoints are merged, real
-- data will flow through the API instead, and everything inserted here
-- can simply be deleted - the dashboard query itself does not depend on
-- this file in any way.
--
-- Assumes the following already exist:
--   users.id = 1        -> testuser@example.com,  role = organiser
--   users.id = 2         -> admin@example.com,      role = faculty_admin
--   events.id = 3         -> 'Cultural Night...', status = 'published',
--                           society_id = 1, capacity = 300
--   (events.id = 3 is used here because it's the one event that's
--   actually published - dashboard stats for a pending/draft event
--   wouldn't make sense yet)
--
-- This file ALSO backfills society_members (step 0 below), since that
-- table was empty even though the organiser had already "created"
-- events under society_id = 1 - see the comment on step 0 for why this
-- matters for the dashboard query.
--
-- Run this AFTER schema.sql AND seed-test-events.sql have already run.

USE eventora;

-- ============================================
-- 0. Backfill society_members for the organiser
-- ============================================
-- This row SHOULD already exist by this point in the real workflow
-- (an organiser needs to be a society member before they can create
-- events under that society - see PR1's security model: "An organiser
-- can only manage events belonging to societies they are a member of").
-- It was skipped earlier when events were seeded directly via SQL
-- instead of through a real "join society" flow.
--
-- Without this row, GET /api/dashboard/organiser would correctly find
-- ZERO societies for this organiser (per the security model), and so
-- would report zero events - even though events.created_by = 1 clearly
-- shows this organiser created 3 events. This single row closes that
-- gap so the dashboard query can follow the same society_members-based
-- authorization path that the rest of the system is supposed to use.
INSERT INTO society_members (society_id, user_id, role)
VALUES (1, 1, 'organiser');

-- ============================================
-- 1. Seed 4 test attendee users
-- ============================================
-- password_hash is a deliberately fake, unusable placeholder string -
-- NOT a real bcrypt hash. These 4 accounts exist only to be referenced
-- by registrations.user_id below; they are not meant to be logged into.
-- If a real login-capable test attendee is ever needed, generate a real
-- hash separately with: php -r "echo password_hash('...', PASSWORD_BCRYPT);"
INSERT INTO users (name, email, password_hash, role) VALUES
    ('Test Attendee A', 'attendee.a@example.com', 'NOT_A_REAL_HASH_PLACEHOLDER', 'attendee'),
    ('Test Attendee B', 'attendee.b@example.com', 'NOT_A_REAL_HASH_PLACEHOLDER', 'attendee'),
    ('Test Attendee C', 'attendee.c@example.com', 'NOT_A_REAL_HASH_PLACEHOLDER', 'attendee'),
    ('Test Attendee D', 'attendee.d@example.com', 'NOT_A_REAL_HASH_PLACEHOLDER', 'attendee');

-- ============================================
-- 2. Seed registrations for event id = 3 (the published Cultural Night event)
-- ============================================
-- Deliberately covers 4 different statuses so the dashboard's
-- "registrations" and "capacity use" numbers aren't trivially all-or-nothing:
--   - Attendee A: confirmed (will also get a ticket + check-in below)
--   - Attendee B: confirmed (will get a ticket, but will NOT check in -
--                 simulates someone who registered but didn't show up)
--   - Attendee C: waitlisted (event wasn't full, but this tests that
--                 waitlisted registrations don't count toward capacity use)
--   - Attendee D: cancelled (tests that cancelled registrations are
--                 excluded from active counts entirely)
--
-- NOTE: user_id values below assume the 4 attendees just inserted above
-- got ids 3, 4, 5, 6 (since users.id = 1 and 2 already exist). If your
-- database has different existing rows, run:
--   SELECT id, email FROM users WHERE email LIKE 'attendee.%';
-- and adjust the user_id values below to match before running this block.

INSERT INTO registrations (event_id, user_id, status, waitlist_position) VALUES
    (3, 3, 'confirmed',  NULL),
    (3, 4, 'confirmed',  NULL),
    (3, 5, 'waitlisted', 1),
    (3, 6, 'cancelled',  NULL);

-- ============================================
-- 3. Seed tickets for the 2 confirmed registrations only
-- ============================================
-- Per PR1's data dictionary, a ticket only exists once a registration is
-- confirmed (free event -> instant confirm + QR, per Section 5.1
-- Ticketing Module). Waitlisted and cancelled registrations never get a
-- ticket, which is why only 2 rows are inserted here, not 4.
--
-- qr_token values are obviously-fake placeholders (real tokens are
-- cryptographically random UUIDs generated by Jia Hui's ticketing code) -
-- they just need to be unique strings to satisfy the UNIQUE constraint.

INSERT INTO tickets (registration_id, qr_token, status) VALUES
    (1, 'TEST-QR-TOKEN-PLACEHOLDER-0001', 'used'),
    (2, 'TEST-QR-TOKEN-PLACEHOLDER-0002', 'active');

-- ============================================
-- 4. Seed ONE check-in (only for Attendee A's ticket)
-- ============================================
-- Attendee A showed up and got scanned in; Attendee B's ticket is left
-- untouched (status stays 'active', no check_ins row) to simulate a
-- no-show. This gives the dashboard a non-trivial attendance rate to
-- calculate: 1 checked in out of 2 confirmed = 50%, instead of either
-- 0% or 100% which wouldn't prove the calculation actually works.
--
-- checked_by = 1 (the organiser, testuser@example.com) - matches PR1's
-- check_ins.checked_by description: "References users.id — organiser
-- who scanned".

INSERT INTO check_ins (ticket_id, checked_by, method) VALUES
    (1, 1, 'qr_scan');