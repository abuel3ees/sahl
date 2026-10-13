# Certificate PDF with QR code

**When:** 2026-10-11 23:39 (UTC+3)

## What was asked

> "let's do certificates" … "go" (mPDF + mpdf/qrcode, generated on download)

## What we did

- **`App\Certificates\CertificatePdf`:** renders an A4 PDF from the certificate's **issue-time snapshot** with **mPDF**, which joins Arabic letters and lays out right-to-left in pure PHP.
  - Font: **IBM Plex Sans Arabic** (Arabic and Latin, SIL Open Font License), in `resources/fonts/ibm-plex-sans-arabic/` with `OFL.txt`.
  - mPDF's temporary files go in `storage/framework/cache/mpdf`.
- **Layout** (`resources/views/certificates/good-conduct.blade.php`, tables and inline CSS for mPDF):
  - a red header with the star mark and *State of Al-Wahah / Ministry of Interior* in both languages
  - the title, serial number, issue date and valid-until date
  - the statement, in the language the applicant chose
  - the holder (both names), National ID, purpose, addressee and issuer
  - a box with the **QR code → `/verify/{code}`** and the printed code
  - a "demonstration" watermark and footer: it must never pass for a real document
- **`GET /applications/{id}/certificate.pdf`:** shown inline, `Cache-Control: private, no-store` (it carries the full National ID). `ApplicationPolicy::view` lets in the applicant and the issuing ministry's reviewers; everyone else gets 404, and so does a request with no certificate yet.
- **Buttons:**
  - the citizen's request page and `/account`: "Download certificate"
  - the reviewer's certificate card: "Open the certificate (PDF)"
- `CertificatePurpose::label()` gives the same purpose wording as the website.
- **New dependencies** (approved): `mpdf/mpdf` ^8.3 and `mpdf/qrcode` ^1.2.
- Added 6 tests (`CertificateDownloadTest`). 134 pass. Rendered Arabic and English samples were checked visually.

## Decisions

- **mPDF over headless Chrome or DomPDF.** DomPDF breaks Arabic letter joining. Chrome renders perfectly but needs Node and Chrome on the server, which makes the Azure deployment heavier.
- **Generated on download** from the snapshot for now (identical every time, no storage).
  - **Later:** generate once at issue, **sign with a key in Azure Key Vault**, and keep it in **immutable Blob Storage**.
- **The QR code is the proof in V1:** the online record decides, as on many GCC certificates.

## Files

- `app/Certificates/CertificatePdf.php`, `resources/views/certificates/good-conduct.blade.php`, `resources/fonts/ibm-plex-sans-arabic/*`
- `app/Http/Controllers/Web/CertificateDownloadController.php`, `routes/web.php`, `app/Enums/CertificatePurpose.php`
- `resources/js/pages/citizen/application.tsx`, `resources/js/pages/citizen/account.tsx`, `resources/js/pages/staff/applications/show.tsx`
- `tests/Feature/Web/CertificateDownloadTest.php`
- `composer.json`, `composer.lock`, `docs/v1/ministry-of-interior.md`, `CLAUDE.md`
