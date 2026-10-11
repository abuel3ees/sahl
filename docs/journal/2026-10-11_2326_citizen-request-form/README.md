# Citizen request form and tracking

**When:** 2026-10-11 23:26 (UTC+3)

## What was asked

> "add that yes" (a signed-in citizen requests a Good Conduct Certificate and follows it)

## What we did

- **`/services/{slug}/apply`:** two steps, *Your request* then *Review and submit*.
  - Purpose as cards with hints.
  - Optional addressee (empty = "To whom it may concern").
  - Certificate language.
  - The review step has a declaration checkbox before **Submit**.
  - Side panel: processing time, fee, validity, and "no payment taken (demo)".
  - If the citizen already has an open request, the page links to it instead of showing the form.
- **`POST /services/{slug}/applications`:** `Web\SubmitApplicationRequest` extends the API's `StoreApplicationRequest` (active service, purpose, language, addressee ≤ 150, one open request per service) and adds `declaration: accepted`. It runs the same `SubmitApplication` action, then redirects to the request with a one-time "Request submitted" banner.
- **`/account/applications/{id}`:** the citizen follows their request.
  - **Waiting:** "a reviewer will decide by …", using the new `Application::decisionDueBy()` (SLA working days, Friday and Saturday skipped).
  - **Rejected:** the reason, plus a link to request again.
  - **Approved:** the verification code, valid-until date, and a link to the public verification page.
  - A progress timeline.
  - Another citizen's request is a 404.
- **Shared timeline:** `components/applications/timeline.tsx` replaces the copy in the staff page. It's worded for the viewer ("You submitted your request" or "Submitted by the applicant").
- **Links:** `/account` has **Request a Good Conduct Certificate** and links each request; the landing page's **Start request** goes to the form. Guests sign in first and come back (`url.intended`).
- The header shows the Arabic name in Arabic (`auth.user.name_ar` shared).
- Added 15 tests (`CitizenApplicationTest`). 128 pass.
- Checked end to end in headless Chrome as demo citizen Fatima: form → review → declaration → submit → confirmation (`SHL-2026-VVRBZQ`, due 14 Oct).

## Decisions

- **One set of rules for the web and the API.** The web form request extends the API's; only the declaration is web-only, because the API's clients make their own declarations.
- **Two-step form** (fill in, then review), the usual pattern for government e-services: the citizen sees exactly what will be printed before committing.

## Files

- `app/Http/Controllers/Web/Citizen/ApplicationController.php`, `app/Http/Requests/Web/SubmitApplicationRequest.php`, `routes/web.php`
- `app/Models/Application.php` (`decisionDueBy`), `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/pages/citizen/apply.tsx`, `resources/js/pages/citizen/application.tsx`, `resources/js/pages/citizen/account.tsx`, `resources/js/pages/landing.tsx`
- `resources/js/components/applications/timeline.tsx`, `resources/js/pages/staff/applications/show.tsx`, `resources/js/components/site/site-header.tsx`, `resources/js/types/global.d.ts`
- `tests/Feature/Web/CitizenApplicationTest.php`
- `docs/v1/ministry-of-interior.md`, `CLAUDE.md`
