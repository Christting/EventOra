# EventOra Notification Flow Test Notes

This branch connects the notification feature end to end:

- Slim backend creates and stores notifications in MySQL.
- Vue frontend reads notifications from `/api/notifications`.
- JWT protects notification APIs so users only see their own notifications.

## Local Setup

Start Laragon MySQL, then run these SQL scripts if the database is fresh:

```powershell
Get-Content -Raw backend\database\schema.sql | & "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root
Get-Content -Raw backend\database\create_notifications.sql | & "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root
```

Install PHP dependencies from `backend`:

```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" "C:\laragon\bin\composer\composer.phar" install
```

Create `backend/.env` locally:

```env
DB_HOST=127.0.0.1
DB_NAME=eventora
DB_USER=root
DB_PASS=

JWT_SECRET=eventora_local_dev_secret_please_change_before_real_deployment_2026
JWT_EXPIRY=86400
```

Start backend from the project root:

```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -S localhost:8000 -t backend/public
```

Start frontend from `frontend`:

```powershell
npm run dev
```

## Test Accounts

Use these local test accounts if they exist in your MySQL database:

```text
Organiser:
testuser@example.com
password123

Faculty Admin:
admin@example.com
password123
```

If they do not exist, create them through the register/login flow or insert local seed data.

For notification-specific demos, you can run:

```powershell
Get-Content -Raw backend\database\seed-notification-demo-data.sql | & "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root
```

This creates or resets these login-capable demo accounts. All use password `password123`:

```text
Faculty Admin:
notify.admin@example.com

Organiser:
notify.organiser@example.com

Attendee:
notify.attendee@example.com
```

It also creates one pending approval event and one published event with a confirmed attendee registration.

## Demo Flows

### 1. Organiser submits event, admin receives notification

1. Login as organiser.
2. Create an event.
3. Backend creates an event with `pending_approval`.
4. Login as faculty admin.
5. Open `/notifications`.
6. Expected result: admin sees `New event pending approval`.

Backend notification type:

```text
event_pending_approval
```

### 2. Admin rejects event, organiser receives notification

1. Login as faculty admin.
2. Open approval queue.
3. Reject a pending event with a reason.
4. Login as organiser.
5. Open `/notifications`.
6. Expected result: organiser sees `Event rejected` with the rejection reason.

Backend notification type:

```text
event_rejected
```

### 3. Admin approves event, organiser receives notification

1. Login as faculty admin.
2. Open approval queue.
3. Approve a pending event.
4. Login as organiser.
5. Open `/notifications`.
6. Expected result: organiser sees `Event approved`.

Backend notification type:

```text
event_approved
```

### 4. Mark notifications as read

1. Login as any user with unread notifications.
2. Open `/notifications`.
3. Click `Mark as read` or `Mark all as read`.
4. Refresh the page.
5. Expected result: read state persists because MySQL was updated.

APIs used:

```text
POST /api/notifications/{id}/read
POST /api/notifications/read-all
POST /api/notifications/send-event-reminders
```

### 5. Organiser cancels event, attendees receive notification

1. Make sure an event is `published`.
2. Make sure at least one attendee has a registration for that event with status `confirmed`, `waitlisted`, or `pending_payment`.
3. Login as the organiser who owns the event.
4. Cancel the event. Optionally include a cancellation reason.
5. Login as the attendee.
6. Open `/notifications`.
7. Expected result: attendee sees `Event cancelled`.

Backend notification type:

```text
event_cancelled
```

Cancelled registrations are skipped, so users who already cancelled their own registration will not receive this event cancellation notification.

### 6. Send upcoming event reminders

1. Make sure an event is `published` and starts within the reminder window.
2. Make sure at least one attendee has a `confirmed` registration for that event.
3. Login as an organiser or faculty admin.
4. Send event reminders with `hours_before`, for example `24`.
5. Login as the attendee.
6. Open `/notifications`.
7. Expected result: attendee sees `Event reminder`.

Backend notification type:

```text
event_reminder
```

The reminder sender skips duplicate reminders for the same user and event, so running it twice should not create the same reminder twice.

## Implemented APIs

```text
GET  /api/notifications
GET  /api/notifications/unread-count
POST /api/notifications/{id}/read
POST /api/notifications/read-all
POST /api/notifications/send-event-reminders
```

## Important Note

The old mock notification data is no longer the source for the Vue notification page. The Vue notification view now reads from the Slim backend and MySQL.
