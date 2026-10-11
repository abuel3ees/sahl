# Scope reset: build V1 first

**When:** 2026-10-11 03:41 (UTC+3)

## What was asked

> “The complexity of this whole project is borderline unmotivating.”

## What we did

- Agreed the pace was wrong: five long documents and nothing running yet.
- **Cut the scope to V1: one ministry, one service, built end to end.** A citizen requests a **Good Conduct Certificate**, a Ministry of Interior reviewer approves it, and the citizen gets a verifiable certificate.
- Everything else (APIM, Front Door, Functions, Service Bus, hub-spoke networking, payments, AI, more ministries) moved to a "later" list. The guides are now reference material, not prerequisites.
- Wrote the app description in one-liner, short and case-study lengths.

## Decisions

| Keep (V1) | Later |
|---|---|
| Laravel + Inertia React, Arabic/English | APIM, Front Door, WAF |
| One ministry, one service | Three ministries |
| Postgres, PDF with QR | Service Bus, Functions, payments, AI |
| App Service + Postgres + Key Vault | Hub-spoke networking, firewall, Bastion |

## Files

_None. A plan change only._
