# Website: 'Poster' design + docs pages

**When:** 2026-10-11 04:51 (UTC+3)

## What was asked

> “Let's do the design first, with the API docs and the md docs.” Chose direction **1b Poster** and docs as pages in the same design.

## What we did

- Extracted the design bundle (three directions) and built **1b Poster** with Inertia v3, React 19, TypeScript and Tailwind v4.
- **Landing `/`:** red hero with the big سهل wordmark and turning star, photo strip, service ticker, the featured service (facts from the database) and an animated request-tracking demo.
- **`/developers`:** an API reference rendered from `openapi.json`, with a curl example per endpoint, the data models, and a download button.
- **`/docs/ministry-of-interior`:** the ministry `.md` rendered on the server, with diagrams drawn in Sahl's colours.
- **Arabic by default, right-to-left.** The toggle saves a `lang` cookie, and `?lang=en` also works. The layout mirrors itself using logical properties.
- **Fonts self-hosted** (Archivo, IBM Plex Sans Arabic, Reem Kufi). Motion respects reduced-motion settings.
- Checked with screenshots on desktop and at 390px wide, in both languages. 60 tests pass.

## Decisions

| Design vs build | Note |
|---|---|
| Design says "No fee" | The page shows QAR 50.00 from the database |
| Photo strip | Placeholders until real black-and-white photos are added |
| Sign in / Start request / Track | Buttons in place; the screens come later |

## Files

- `resources/js/` (pages, layouts, components, lib), `resources/css/app.css` (design tokens)
- `app/Http/Controllers/Web/`, `app/Http/Middleware/SetLocale.php`, `resources/views/app.blade.php`
- `tests/Feature/Web/`, `tests/Feature/Middleware/`
