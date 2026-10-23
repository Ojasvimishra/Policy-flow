# ADR-003: Frontend Strategy — Decoupled React / Vite / Tailwind SPA

## Status
Accepted

## Context
The assessment's core mandatory requirements focus on API correctness, fault tolerance, idempotency, and automated testing. However, an operational interface is desired to provide visibility into policy requests, state transitions, attempt history, and manual reconciliation triggers.

## Decision
We define a decoupled frontend application using **React 18**, **TypeScript**, **Vite**, and **Tailwind CSS** located in the `frontend/` directory.
- The frontend acts strictly as a consumer of the backend REST API (`/api/policy-requests`).
- The backend remains completely functional and testable without the frontend.
- Frontend implementation is scheduled for **Phase 4** to ensure all core business logic, database migrations, pricing formulas, and mock insurer resilience mechanisms are fully tested and validated first.

## Alternatives Considered
1. **Server-Side Rendered Blade / Livewire**:
   - Viable, but modern team frontend workflows benefit from strict JSON API contracts and decoupled React state modeling.
2. **Filament Admin Panel**:
   - Mentioned as an optional stretch feature in the assignment; however, a custom React interface provides precise control over the issuance timeline and visual mock scenario triggers.

## Consequences
- **Positive**: Clean separation between operational UI presentation and backend transaction engine. Backend REST API is independently testable via cURL, Postman, and automated tests.
- **Negative**: Adds Node.js / Vite build tooling to local development.
