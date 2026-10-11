# V1 API: Good Conduct Certificate

**When:** 2026-10-11 04:25 (UTC+3)

## What was asked

> “Let's start with V1, step by step, let's start with the APIs.”

## What we did

- `git init`, plus Laravel Boost installed (as the project instructions require).
- Installed Sanctum (API tokens) and Scramble (OpenAPI generator).
- **Data:** `gov_entities`, `services`, `applications` (UUIDv7), `application_status_changes` (append-only timeline), `certificates` (snapshot content plus a verification code). Users gained `type`, `national_id` and `gov_entity_id`.
- **Business rules** live in `app/Actions/Applications/` (`SubmitApplication`, `ApproveApplication`, `RejectApplication`):
  - A decision is final. Deciding twice returns 409, and the row is locked while deciding.
  - A citizen can have only one open application per service.
  - Approving issues the certificate in the same transaction.
- **Security:**
  - Other people's records return **404, not 403**.
  - Reviewers only see their own ministry's applications and can never decide their own.
  - Verification shows only a masked national ID.
  - Tokens expire after 8 hours, and sign-in and verification are rate-limited.
- **14 endpoints** under `/api/v1`, plus 50 tests. The full flow was also checked over real HTTP.

## Files

- `app/Actions/Applications/`, `app/Http/Controllers/Api/V1/`, `app/Policies/ApplicationPolicy.php`
- `routes/api.php`, `database/migrations/2026_10_11_*`
- `tests/Feature/Api/V1/`, `tests/Feature/Policies/`
