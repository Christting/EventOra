# EventOra - Student Society Event Management & Ticketing Platform

EventOra is a campus event platform for student societies. It helps students discover events, register or pay for tickets, store QR tickets, and check in through a mobile-friendly organiser scanner.

## Frontend Prototype

This repository currently contains the launch-ready frontend prototype for the registration, ticketing, and QR check-in experience.

Included screens and states:

- Event discovery landing page.
- Event listing cards with categories, price, and availability.
- Event detail and registration checkout.
- Mock payment confirmation states.
- Waitlist, cancellation, and duplicate registration states.
- My Tickets wallet with upcoming and past ticket cards.
- QR check-in mobile scanner experience.
- Check-in outcomes for success, invalid ticket, wrong society, and already checked in.

## Current Scope

The current version is a frontend prototype. Backend API, database, authentication, real payment, and real camera scanning will be integrated by the team in later milestones.

## Demo Flow

1. Discover upcoming events from the landing page.
2. Review event details and reserve a seat.
3. Complete the mock payment confirmation for a paid event.
4. Review waitlist, cancellation, and duplicate registration states.
5. Open My Tickets to view upcoming and past QR tickets.
6. Use the QR check-in screen to review scanner, manual fallback, and result panels.

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

## Future Integration

The frontend prototype can later be connected to the team backend for:

- Event data from API.
- Registration creation.
- Mock payment confirmation.
- Ticket generation.
- QR token validation.
- Check-in result handling.
