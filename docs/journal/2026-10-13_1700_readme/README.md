# README in the Sahl design

## What was asked

> "before, make a read me for the repo, a super nice readme with the exact same design as the webapp, something very very nice"
>
> "Before, can you write a super nice read me for the repo, with the same theme and like screenshots and stuff"

## What we did

1. **Real screenshots** of the running app, taken with headless Chrome at 2x and resized for the README:
   - the landing page in English and Arabic, sign-in, the staff review queue and decision page, the citizen's request, the public verify page, the API reference
   - the certificate PDF, rendered to an image
   - The signed-in pages used short-lived local demo sessions for the seeded reviewer and citizen. Nothing was changed in the app.
2. **Two diagrams drawn as SVG in the app's own design system:**
   - same paper colour, ink, red, 2 px rules and no radius
   - the real fonts (Archivo, IBM Plex Sans Arabic, Reem Kufi), embedded as subsets, so they render the same on GitHub
   - `flow.svg`: submit → review → issue → verify
   - `architecture.svg`: Entra, App Service, Key Vault, Postgres, monitoring and the GitHub deploy identity
3. **`README.md`** rewritten, replacing the Laravel skeleton README:
   - the hero screenshot as a banner, a bilingual title and brand-coloured badges
   - a fictional-entity note, the flow, a screens gallery, the architecture with its security decisions, the stack, local setup, links to the setup guides and journal, and the roadmap

## Decisions

- **Screenshots of the real app over mock-ups,** so the README shows exactly what's built.
- **No licence section.** That choice is the owner's.

## Files

- `README.md` (rewritten)
- `docs/readme/*.png` (9 screenshots), `docs/readme/flow.svg`, `docs/readme/architecture.svg` (new)
