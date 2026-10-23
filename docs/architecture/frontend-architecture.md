# Frontend Architecture & Interface Specification

## 1. Role & Boundary
The PolicyFlow frontend is an **operational and agent-facing portal**.
- **Authority**: The backend API is the single authoritative source of truth for pricing calculations, state transitions, validation, and policy issuance.
- **Frontend Responsibilities**: Client-side form formatting, asynchronous API polling, timeline visualization of issuance attempts, and operational management of failed/pending policies.
- **Strict Boundary**: The frontend never calculates insurance premiums or generates policy numbers.

---

## 2. Technology Stack
- **Framework**: React 18 (SPA)
- **Language**: TypeScript 5 (Strict mode enabled)
- **Build Tool**: Vite 5
- **Styling**: Tailwind CSS 3
- **State & Data Fetching**: TanStack Query (React Query) or lightweight native fetch wrappers with typed API responses.
- **Testing**: Vitest & React Testing Library (planned for Phase 4).

---

## 3. Directory Structure Blueprint (Target for Phase 4)
```
frontend/
├── public/
├── src/
│   ├── app/                 # Root component, router & query providers
│   ├── components/          # Reusable UI widgets (Button, Modal, Badge, Timeline)
│   │   ├── ui/
│   │   └── layout/          # Header, Sidebar, Container
│   ├── features/            # Domain-specific modules
│   │   ├── policy-intake/   # Intake Form, validation helpers
│   │   ├── policy-list/     # Filterable policy table, pagination
│   │   ├── policy-details/  # Policy card, state badge, attempt timeline
│   │   └── reconciliation/  # Pending policy resolution actions
│   ├── lib/                 # HTTP client (Axios/fetch with interceptors)
│   ├── types/               # TypeScript domain contracts (matching API)
│   └── tests/               # Unit and component tests
├── index.html
├── package.json
├── tsconfig.json
├── vite.config.ts
└── tailwind.config.js
```

---

## 4. TypeScript Domain Contracts (API Alignments)

```typescript
export type PolicyStatus = 
  | 'RECEIVED'
  | 'VALIDATED'
  | 'SUBMITTED'
  | 'PENDING_CONFIRMATION'
  | 'ISSUED'
  | 'FAILED'
  | 'NEEDS_RECONCILIATION';

export type PlanCode = 'BASIC' | 'PLUS' | 'PREMIUM';
export type Destination = 'DOMESTIC' | 'INTERNATIONAL';

export interface PolicyRequestPayload {
  client_request_id: string;
  agent_id: string;
  customer_name: string;
  date_of_birth: string; // YYYY-MM-DD
  destination: Destination;
  trip_start: string;    // YYYY-MM-DD
  trip_end: string;      // YYYY-MM-DD
  plan_code: PlanCode;
  sum_insured: number;
}

export interface PremiumSnapshot {
  travel_days: number;
  base_daily_rate: number;
  age_multiplier: number;
  destination_multiplier: number;
  coverage_multiplier: number;
  final_premium: number;
}

export interface PolicyRequestRecord {
  id: number;
  client_request_id: string;
  agent_id: string;
  customer_name: string;
  status: PolicyStatus;
  policy_number: string | null;
  provider_reference: string | null;
  premium: PremiumSnapshot;
  attempt_count: number;
  created_at: string;
  updated_at: string;
}
```

---

## 5. Planned Screens & User Flows (Phase 4 Implementation)
1. **Policy Intake Wizard (`/intake`)**: Real-time form validating trip dates and instant preview of server-calculated premium quote before final submission.
2. **Operations Dashboard (`/policies`)**: Live table with status badges (`ISSUED` in green, `PENDING_CONFIRMATION` in amber, `FAILED` in red), search by `client_request_id`, and status filter.
3. **Policy Inspection & Audit Timeline (`/policies/:id`)**: Detailed card showing immutable premium snapshot, issuance attempts history, and real-time reconciliation triggers.
