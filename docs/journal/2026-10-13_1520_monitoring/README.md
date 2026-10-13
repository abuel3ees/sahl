# Monitoring: Log Analytics, Application Insights, availability alert

## What was asked

> "A"  (Application Insights now, before turning off password sign-in)
>
> "A"  (built-in logs + an availability test, no PHP instrumentation)
>
> "anything left?"

## What we did

1. **Explained the limit:** Application Insights has no automatic agent for PHP. Gave three options: built-in logs; OpenTelemetry in PHP; the browser SDK.
2. **Bicep** (written by me), deployed by the user:
   - workspace `log-sahl-dev-sdc` (30 days retention, 0.1 GB daily cap)
   - Application Insights `appi-sahl-dev-sdc`
   - diagnostic setting `to-log-analytics` on the web app (HTTP, console and platform logs, plus all metrics)
   - standard availability test `avail-sahl-up` on `/up` from 3 European locations
   - action group `ag-sahl-ops` (email from `SAHL_ALERT_EMAIL`)
   - metric alert `alert-sahl-down` (2 of 3 locations failing)
3. **The user registered** `Microsoft.OperationalInsights`, `Microsoft.Insights` and `Microsoft.AlertsManagement`.
4. **Verified with KQL from the terminal:**
   - all four tables receive data
   - availability passes from all three locations
   - one `500` from North Europe at 12:13, during the deploy's restart. One location alone doesn't fire the alert.

Guide: [setup 09](../../setup/09-monitoring.md).

## Decisions

- **Built-in logs only for now.** No code, no new dependencies.
- **The alert needs 2 of 3 locations to fail**, so a single blip (like a restart) doesn't page anyone.
- **The alert address stays out of the repo** (`SAHL_ALERT_EMAIL`).

## Files

- `infra/main.bicep` (monitoring section), `infra/main.bicepparam` (`alertEmail`)
- `docs/setup/09-monitoring.md` (new)
