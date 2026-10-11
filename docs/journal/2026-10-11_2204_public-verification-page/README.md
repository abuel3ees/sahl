# Public certificate verification page

**When:** 2026-10-11 22:04 (UTC+3)

## What was asked

> "open the public verification record?" (the back-office button opened raw JSON)
>
> "build it yes" (including pointing `verification_url` at the web page)

## What we did

- **`/verify`:** a public form, no sign-in. It takes a verification code; typing or pasting is cleaned up (spaces and dashes removed, upper-cased). Below it, three steps: scan or type, compare, trust the record.
- **`/verify/{code}`:** the result, in Arabic and English:
  - **Valid:** ink block with a check mark.
  - **Expired:** red block, "Genuine, but expired".
  - **Not found:** dashed block, HTTP **404**, with a tip that codes never contain 0/O/1/I/L.
  - **Details:** holder name in both languages, masked National ID (`*******4821`), issuer, serial, issued and valid-until dates.
  - "Check another certificate" form, and `noindex` so search engines never list people's names.
- **Same data and protection as the API:** reuses `CertificateVerificationResource` (masking) and the `verification` rate limit (30 per minute per IP; web and API share the bucket).
- **`verification_url` (API and QR) now points to `/verify/{code}`.** Apps keep using `GET /api/v1/certificates/verify/{code}`. Scramble description updated and `docs/api/openapi.json` regenerated.
- The back-office "Open the public verification record" button now opens this page.
- **"Verify a certificate" added to the main navigation.** The desktop nav now starts at `xl` (1280px), with the menu button below that. With five links the header wrapped at 1024px.
- Added 7 tests (`CertificateVerificationPageTest`). 96 tests pass.

## Decisions

- **The QR code opens the web page, not JSON.** Whoever scans it is a person with a phone (user's choice). Machine clients call the API endpoint.
- **Unknown codes return 404 on a full page**, not a redirect, so the URL stays shareable and monitoring sees real 404s.

## Files

- `app/Http/Controllers/Web/CertificateVerificationController.php`, `routes/web.php`
- `app/Http/Resources/Api/V1/CertificateResource.php`, `docs/api/openapi.json`
- `resources/js/pages/verify/index.tsx`, `resources/js/pages/verify/show.tsx`, `resources/js/components/verify/code-form.tsx`
- `resources/js/components/site/nav-links.ts`, `resources/js/components/site/site-header.tsx`, `resources/js/types/models.ts`
- `tests/Feature/Web/CertificateVerificationPageTest.php`, `tests/Feature/Api/V1/ApplicationCertificateControllerTest.php`
- `docs/v1/ministry-of-interior.md`, `CLAUDE.md`
