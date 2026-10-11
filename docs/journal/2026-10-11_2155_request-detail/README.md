# Reviewer back office, step 2 of 3: request detail

**When:** 2026-10-11 21:55 (UTC+3)

## What was asked

> "go for it" (step 2: open a request from the queue)

## What we did

- **`/staff/applications/{id}`**: the reference number in the queue now links here.
  - **Main column:**
    - Applicant: name in Arabic and English (the certificate prints one of them), National ID, email.
    - Request: purpose, addressed to, certificate language, submitted.
    - Decision, once decided: outcome, when, by whom, and the note or rejection reason.
  - **Side column:**
    - Timeline of status changes, with notes.
    - The issued certificate: serial, verification code, issued and valid-until dates, and a link to the public verification record.
  - Waiting requests show the SLA clock and the overdue badge next to the reference.
- **Access:** the page reuses `ApplicationPolicy::view`. Another ministry's request is a **404**; citizens are stopped by `can:access-backoffice` (403).
- **"Decided by"** names the reviewer, but only in the back office. The public API still only says "ministry".
- Free-text fields (reason, notes, addressee) use `dir="auto"`, so English text displays correctly inside the Arabic page.
- Shared bits (`PURPOSES`, `STATUSES`, `StatusBadge`, `SlaBadge`, `DateText`) moved to `resources/js/components/staff/application-bits.tsx`. Pages only export their component, which keeps React Fast Refresh working.
- Added 3 tests: detail with timeline and certificate, other ministry → 404, citizen → 403. 81 tests pass.

## Notes

- The user's running `npm run dev` served an **old copy** of `app.css` to the page's `<link>`, without the new Tailwind classes, while Vite's module copy had them. The production build is correct. If a new page looks unstyled, restart `npm run dev`.

## Files

- `app/Http/Controllers/Web/Staff/ApplicationController.php` (`show`), `routes/web.php`
- `resources/js/pages/staff/applications/show.tsx`, `resources/js/pages/staff/applications/index.tsx`
- `resources/js/components/staff/application-bits.tsx`, `resources/js/types/models.ts`
- `tests/Feature/Web/StaffApplicationQueueTest.php`
