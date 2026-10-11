# Reviewer back office, step 3 of 3: approve or reject

**When:** 2026-10-11 21:58 (UTC+3)

## What was asked

> "go" (step 3: decide a request from the back office)

## What we did

- **Decision panel** on waiting requests, for reviewers allowed to decide (`canDecide` = waiting + `ApplicationPolicy::decide`):
  - **Approve:** an optional note for the applicant.
  - **Reject:** a required reason of at least 10 characters, with a character counter. The button stays disabled until the reason is long enough.
  - **Two steps:** write, then confirm ("This can't be undone"). Approving issues the certificate immediately.
- **Routes:** `POST /staff/applications/{id}/approval` and `/rejection`, handled by `ApplicationDecisionController`.
  - It reuses the API's `ApproveApplicationRequest` / `RejectApplicationRequest` (policy + validation) and the `ApproveApplication` / `RejectApplication` actions.
- **After deciding,** the request page shows a one-time banner (Inertia flash):
  - approved: certificate serial issued
  - rejected: the applicant will see the reason
  - **already decided by a colleague**: the action's 409 `ConflictHttpException` is caught, and nothing changes.
- Added 8 tests (`StaffApplicationDecisionTest`): approve issues certificate, reject with reason, reason validation, colleague-first, another ministry → 404, citizen → 403, panel only while waiting. 89 tests pass.
- Ministry document section 8 now describes the back office; CLAUDE.md map updated.

## Decisions

- **One set of rules for web and API.** The web controller type-hints the API's form requests instead of copying them, so validation and authorization can't drift.
- **"Already decided" is a message, not an error page.** Two reviewers on one request is a normal race; the database row lock in the action makes sure only the first decision counts.

## Files

- `app/Http/Controllers/Web/Staff/ApplicationDecisionController.php`, `app/Http/Controllers/Web/Staff/ApplicationController.php` (`canDecide`), `routes/web.php`
- `resources/js/components/staff/decision-panel.tsx`, `resources/js/pages/staff/applications/show.tsx`, `resources/js/types/global.d.ts`
- `tests/Feature/Web/StaffApplicationDecisionTest.php`
- `docs/v1/ministry-of-interior.md`, `CLAUDE.md`
