# Reviewer back office, step 1 of 3: the review queue

**When:** 2026-10-11 21:49 (UTC+3)

## What was asked

> "A, step by step" (option A: the reviewer queue, split into 1. queue, 2. request detail, 3. approve/reject)

## What we did

- **`/staff` is now the review queue** for the signed-in reviewer's ministry. It replaces the placeholder "signed in as" page.
  - Tabs: *Awaiting decision* / *Approved* / *Rejected*, each with a count.
  - Waiting requests are listed oldest first, decided ones newest first; 20 per page.
  - Each row shows reference, applicant (name and National ID), purpose and addressee, submitted date, and the SLA clock.
  - Phones get one card per request.
- **SLA clock:** `Application::workingDaysWaiting()` / `isOverdue()` count working days only (the Al-Wahah weekend is Friday–Saturday). Overdue requests get a red badge and a notice at the top.
- **API:** `ApplicationResource` gained an `sla` object (`working_days`, `working_days_waiting`, `is_overdue`) when the service is loaded. `docs/api/openapi.json` was regenerated.
- **Demo data:** `DemoApplicationSeeder` (also called from `DatabaseSeeder`) creates 8 MOI requests through the real actions: 6 waiting (2 overdue), 1 approved (with certificate), 1 rejected. It's safe to run again.
- Renamed the seeded citizen "Noora Al-Thani" to **"Noora Al-Rashed"**, because Al Thani is a real ruling family and the project must stay fictional.
- Added 6 tests (`StaffApplicationQueueTest`): ordering, counts, filter, other ministries hidden, SLA maths, decided never overdue. 78 tests pass.

## Decisions

- The website reuses the API's `ApplicationResource`, round-tripped to JSON as the landing page does, so the web and API shapes can't drift.
- SLA is counted in working days from the day after submission. A request sent on Thursday has waited 1 working day by Sunday.

## Files

- `app/Http/Controllers/Web/Staff/ApplicationController.php` (replaces `HomeController`)
- `app/Models/Application.php`, `app/Http/Resources/Api/V1/ApplicationResource.php`, `docs/api/openapi.json`
- `database/seeders/DemoApplicationSeeder.php`, `DatabaseSeeder.php`, `GovEntitySeeder.php`
- `resources/js/pages/staff/applications/index.tsx` (replaces `staff/home.tsx`), `resources/js/types/models.ts`
- `routes/web.php`, `app/Http/Controllers/Web/Auth/StaffSignInController.php`
- `tests/Feature/Web/StaffApplicationQueueTest.php`, `tests/Feature/Web/StaffSignInTest.php`
