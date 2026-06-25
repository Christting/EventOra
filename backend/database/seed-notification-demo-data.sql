-- EventOra - Notification Demo Seed Data
--
-- WHY THIS FILE EXISTS:
-- The notification backend needs predictable local data so these flows can
-- be tested without waiting for every other module to be finished:
--   1. organiser submits / admin approves or rejects event
--   2. organiser cancels published event, attendee receives notification
--   3. organiser/admin sends upcoming event reminders
--
-- Run this AFTER:
--   1. backend/database/schema.sql
--   2. backend/database/create_notifications.sql
--
-- All demo accounts below use password: password123

USE eventora;

SET @demo_password_hash = '$2y$10$Pfu1L9vKJak54nFpXg6mkuLwV3nlYY4MP/Jg20MJm0shCTgXsBpKa';

-- ============================================
-- 1. Demo users
-- ============================================
INSERT INTO users (name, email, password_hash, role)
VALUES ('Notification Demo Admin', 'notify.admin@example.com', @demo_password_hash, 'faculty_admin')
ON DUPLICATE KEY UPDATE
    name = 'Notification Demo Admin',
    password_hash = @demo_password_hash,
    role = 'faculty_admin';

INSERT INTO users (name, email, password_hash, role)
VALUES ('Notification Demo Organiser', 'notify.organiser@example.com', @demo_password_hash, 'organiser')
ON DUPLICATE KEY UPDATE
    name = 'Notification Demo Organiser',
    password_hash = @demo_password_hash,
    role = 'organiser';

INSERT INTO users (name, email, password_hash, role, matric_no)
VALUES ('Notification Demo Attendee', 'notify.attendee@example.com', @demo_password_hash, 'attendee', 'A24CS9999')
ON DUPLICATE KEY UPDATE
    name = 'Notification Demo Attendee',
    password_hash = @demo_password_hash,
    role = 'attendee',
    matric_no = 'A24CS9999';

SET @demo_admin_id = (SELECT id FROM users WHERE email = 'notify.admin@example.com' LIMIT 1);
SET @demo_organiser_id = (SELECT id FROM users WHERE email = 'notify.organiser@example.com' LIMIT 1);
SET @demo_attendee_id = (SELECT id FROM users WHERE email = 'notify.attendee@example.com' LIMIT 1);

-- ============================================
-- 2. Demo society and organiser membership
-- ============================================
INSERT INTO societies (name, description, logo_url, created_by)
VALUES (
    'Notification Demo Society',
    'Local seed society used for notification backend demos.',
    NULL,
    @demo_admin_id
)
ON DUPLICATE KEY UPDATE
    description = 'Local seed society used for notification backend demos.',
    created_by = @demo_admin_id;

SET @demo_society_id = (SELECT id FROM societies WHERE name = 'Notification Demo Society' LIMIT 1);

INSERT INTO society_members (society_id, user_id, role)
VALUES (@demo_society_id, @demo_organiser_id, 'organiser')
ON DUPLICATE KEY UPDATE
    role = 'organiser';

-- ============================================
-- 3. Pending event for admin approve/reject notification tests
-- ============================================
INSERT INTO events (
    society_id, created_by, title, description, venue, category,
    start_datetime, end_datetime, reg_deadline, capacity,
    fee_type, fee_amount, waitlist_enabled, status
)
SELECT
    @demo_society_id, @demo_organiser_id,
    'Notification Demo Pending Event',
    'Use this event to test admin approval or rejection notifications.',
    'N28 Demo Room', 'academic',
    DATE_ADD(NOW(), INTERVAL 7 DAY),
    DATE_ADD(DATE_ADD(NOW(), INTERVAL 7 DAY), INTERVAL 2 HOUR),
    DATE_ADD(NOW(), INTERVAL 6 DAY),
    50, 'free', 0.00, 1, 'pending_approval'
WHERE NOT EXISTS (
    SELECT 1 FROM events
    WHERE title = 'Notification Demo Pending Event'
      AND society_id = @demo_society_id
);

SET @demo_pending_event_id = (
    SELECT id FROM events
    WHERE title = 'Notification Demo Pending Event'
      AND society_id = @demo_society_id
    ORDER BY id ASC
    LIMIT 1
);

UPDATE events
SET
    created_by = @demo_organiser_id,
    description = 'Use this event to test admin approval or rejection notifications.',
    venue = 'N28 Demo Room',
    category = 'academic',
    start_datetime = DATE_ADD(NOW(), INTERVAL 7 DAY),
    end_datetime = DATE_ADD(DATE_ADD(NOW(), INTERVAL 7 DAY), INTERVAL 2 HOUR),
    reg_deadline = DATE_ADD(NOW(), INTERVAL 6 DAY),
    capacity = 50,
    fee_type = 'free',
    fee_amount = 0.00,
    waitlist_enabled = 1,
    status = 'pending_approval',
    cancellation_reason = NULL
WHERE id = @demo_pending_event_id;

DELETE FROM event_approvals WHERE event_id = @demo_pending_event_id;

-- ============================================
-- 4. Published event for reminder and cancellation notification tests
-- ============================================
INSERT INTO events (
    society_id, created_by, title, description, venue, category,
    start_datetime, end_datetime, reg_deadline, capacity,
    fee_type, fee_amount, waitlist_enabled, status
)
SELECT
    @demo_society_id, @demo_organiser_id,
    'Notification Demo Published Event',
    'Use this event to test attendee cancellation and reminder notifications.',
    'Dewan Demo', 'cultural',
    DATE_ADD(NOW(), INTERVAL 2 HOUR),
    DATE_ADD(NOW(), INTERVAL 4 HOUR),
    DATE_ADD(NOW(), INTERVAL 1 HOUR),
    80, 'free', 0.00, 1, 'published'
WHERE NOT EXISTS (
    SELECT 1 FROM events
    WHERE title = 'Notification Demo Published Event'
      AND society_id = @demo_society_id
);

SET @demo_published_event_id = (
    SELECT id FROM events
    WHERE title = 'Notification Demo Published Event'
      AND society_id = @demo_society_id
    ORDER BY id ASC
    LIMIT 1
);

UPDATE events
SET
    created_by = @demo_organiser_id,
    description = 'Use this event to test attendee cancellation and reminder notifications.',
    venue = 'Dewan Demo',
    category = 'cultural',
    start_datetime = DATE_ADD(NOW(), INTERVAL 2 HOUR),
    end_datetime = DATE_ADD(NOW(), INTERVAL 4 HOUR),
    reg_deadline = DATE_ADD(NOW(), INTERVAL 1 HOUR),
    capacity = 80,
    fee_type = 'free',
    fee_amount = 0.00,
    waitlist_enabled = 1,
    status = 'published',
    cancellation_reason = NULL
WHERE id = @demo_published_event_id;

INSERT INTO registrations (event_id, user_id, status, waitlist_position, cancelled_at)
VALUES (@demo_published_event_id, @demo_attendee_id, 'confirmed', NULL, NULL)
ON DUPLICATE KEY UPDATE
    status = 'confirmed',
    waitlist_position = NULL,
    cancelled_at = NULL;

-- Reset demo notifications so the same demo can be repeated after rerunning this file.
DELETE FROM notifications
WHERE user_id IN (@demo_admin_id, @demo_organiser_id, @demo_attendee_id)
  AND (
      related_event_id IN (@demo_pending_event_id, @demo_published_event_id)
      OR type IN ('event_reminder', 'event_cancelled', 'event_approved', 'event_rejected', 'event_pending_approval')
  );

-- ============================================
-- 5. Quick reference output
-- ============================================
SELECT
    @demo_admin_id AS admin_user_id,
    @demo_organiser_id AS organiser_user_id,
    @demo_attendee_id AS attendee_user_id,
    @demo_pending_event_id AS pending_event_id,
    @demo_published_event_id AS published_event_id;

SELECT 'Notification demo data is ready. Password for all demo accounts is password123.' AS message;