# Frontend decision: Inertia as a BFF

**When:** 2026-10-10 17:07 (UTC+3)

## What was asked

> “What is TanStack Router and what is Query, also what is MSAL React?” … “Is it a bad idea to just use Inertia?”

## What we did

- Explained TanStack Router, TanStack Query and MSAL React (the original plan was a React SPA holding tokens in the browser).
- **Reversed decision D06:** the site now uses **Laravel + Inertia React as a BFF** (backend-for-frontend). Sign-in happens on the server, the session lives in an HttpOnly cookie, and **tokens never reach the browser**.
- The same app image is deployed twice with `APP_SURFACE=citizen|backoffice`: the citizen site is public behind Front Door + WAF, and the back office is internal-only.
- APIM is now only for system-to-system APIs (registries, payment webhooks, a future mobile app).
- Updated chapters 5, 6, 7, 11, 15, 17, 18 and 20 of the guide, including a "decision history" note.

## Decisions

| Before | After |
|---|---|
| React SPA + TanStack + MSAL (tokens in the browser) | Inertia React, with server-side OIDC (tokens on the server) |

## Files

- [`docs/learning/sahl-architecture-guide.md`](../../learning/sahl-architecture-guide.md), chapters 5–7
