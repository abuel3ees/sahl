# Sign-in screen (frontend only)

**When:** 2026-10-11 16:49 (UTC+3)

## What was asked

> "before we update, let's just set up the log in screen (its okay, just frontend)"

## What we did

- Added `/login` (named `login`), an Inertia page in the 1b Poster design: a red poster panel with the rotating star and the Kufic wordmark, plus the form on the other side. On mobile, the panel is hidden.
- Added an audience switch: **Citizens & residents** sign in with an 11-digit National ID, and **Government staff** with a work email. Both have a password field with show/hide, and a "keep me signed in" option.
- Submitting shows a "not connected yet" notice. Nothing is posted.
- The demo accounts are listed under the form.
- The "Sign in" links in the header and on the landing page now go to `/login`.
- Checked in Arabic (RTL) and English, on desktop and mobile.

## Decisions

- The route is named `login` because Laravel's `auth` middleware redirects guests to that name, so wiring it up later needs no change here.
- Citizens and staff share one screen with a switch, rather than having two separate pages.

## Files

- `resources/js/pages/auth/login.tsx`
- `routes/web.php`
- `resources/js/components/site/site-header.tsx`, `resources/js/pages/landing.tsx` (links)
- `tests/Feature/Web/LoginPageTest.php`
