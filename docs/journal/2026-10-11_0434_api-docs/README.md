# API docs: Swagger + ministry document

**When:** 2026-10-11 04:34 (UTC+3)

## What was asked

> “After you're done, write me some docs: Swagger and a .md for the single ministry.”

## What we did

- Exported the OpenAPI 3.1 (Swagger) file. It's generated from the code, so it can't drift, and it passes Redocly's validator.
- Added groups, response codes (201/404/409/429) and enum types to the spec.
- Wrote the ministry document: the service, the lifecycle diagram, who can do what, the data model, the form fields, a curl walkthrough, the errors, the privacy choices, and what's not in V1.

## Files

- [`docs/api/openapi.json`](../../api/openapi.json) (regenerate with `php artisan scramble:export`)
- [`docs/v1/ministry-of-interior.md`](../../v1/ministry-of-interior.md)
