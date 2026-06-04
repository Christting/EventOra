# EventOra Frontend UI Progress

This branch documents my assigned frontend work for EventOra. The current progress is a static HTML/CSS UI skeleton focused on the overall clean white style, event registration, ticketing, and QR check-in screens.

## My Contribution

- Frontend skeleton and page layout.
- Minimal white UI style with reusable design tokens.
- Event listing and event detail UI.
- Registration panel for free and paid events.
- Mock payment modal UI.
- Waitlist, cancellation, and duplicate registration states.
- My Tickets page with upcoming and past ticket cards.
- QR check-in scanner UI with manual ticket code fallback.
- QR check-in result states for success, invalid ticket, wrong society, and already checked in.
- Responsive layout polish for mobile, tablet, and desktop.

## Current Scope

This is frontend-only progress for HTML/CSS review. It does not include backend integration yet.

Not included yet:

- Backend API.
- Database.
- Real JWT authentication.
- Real payment gateway.
- Real QR scanner camera plugin.
- Capacitor Android implementation.

## Demo Flow

1. Open the EventOra frontend page.
2. Review the event listing cards.
3. Open the event registration section.
4. Review the paid event mock payment modal.
5. Review free registration, duplicate registration, waitlist, and cancellation states.
6. Review My Tickets with QR-style ticket placeholders.
7. Review QR check-in scanner UI and manual ticket code fallback.
8. Review QR check-in result state cards.

## Run Locally

```powershell
cd frontend
npm install
npm run dev
```

Then open:

```text
http://localhost:5173
```

## Future Backend Integration

The static UI can later be connected to the team backend for:

- Event data from API.
- Registration creation.
- Mock payment confirmation.
- Ticket generation.
- QR token validation.
- Check-in result handling.
