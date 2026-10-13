# 09 · Monitoring: Log Analytics, Application Insights, availability alert

**Done:** 2026-10-13, resource group `rg-sahl-dev-sdc`. Builds on [05](05-azure-app-service.md).

**Goal:** answer "is it up, what's slow, what failed" without touching the PHP code, and get an email when the site is down.

**Cost:**
- **Log Analytics:** about $2–3 per GB ingested, and the first 5 GB a month are free. Sahl produces a few MB a day, and the daily cap (0.1 GB) limits a runaway logging bug.
- **Availability test:** about $1 a month.
- **Alert rule:** about $0.10 a month.
- **Email notifications:** free.

---

## Words used here

| Term | Meaning |
|---|---|
| **Log Analytics workspace** (`log-sahl-dev-sdc`) | The store for logs and metrics. Queried with **KQL** |
| **Application Insights** (`appi-sahl-dev-sdc`) | The monitoring app on top of the workspace: availability, failures, dashboards. *Workspace-based*: its data lives in the workspace |
| **Diagnostic setting** | "Send this resource's logs to that workspace." No code |
| **Availability test** (standard web test) | Azure calls a URL on a schedule from several places and records whether it passed |
| **Action group** | Who gets told and how (here: an email) |
| **Metric alert** | A rule that fires an action group when a metric crosses a line |

## Decisions

- **No PHP instrumentation (yet).** Application Insights has no automatic agent for PHP. All the data comes from App Service's own logs. OpenTelemetry in PHP (a per-request breakdown) and the browser SDK are later options.
- **The alert email is not in the repo.** It's read from `SAHL_ALERT_EMAIL`.

## Step 1: Register the providers (once per subscription)

```bash
az provider register -n Microsoft.OperationalInsights
az provider register -n Microsoft.Insights
az provider register -n Microsoft.AlertsManagement
for n in Microsoft.OperationalInsights Microsoft.Insights Microsoft.AlertsManagement; do az provider show -n $n --query registrationState -o tsv; done   # wait for 3 x Registered
```

## Step 2: Infrastructure (Bicep)

`infra/main.bicep` gained a monitoring section:

| Resource | Settings |
|---|---|
| `workspace` (`log-sahl-dev-sdc`) | `PerGB2018`, 30 days retention, daily cap 0.1 GB |
| `appInsights` (`appi-sahl-dev-sdc`) | `kind: 'web'`, `WorkspaceResourceId` = the workspace |
| `webappDiagnostics` (scope: the web app) | `AppServiceHTTPLogs` (every request: status, duration, URL), `AppServiceConsoleLogs` (Laravel's stderr), `AppServicePlatformLogs` (starts, restarts, crashes), `AllMetrics` |
| `upTest` (`avail-sahl-up`) | GET `/up` every 300 s from West Europe, North Europe and France Central. Expects 200 and a TLS certificate valid for more than 7 days. Tagged `hidden-link:<appInsights id>` so it shows under Application Insights |
| `opsEmail` (`ag-sahl-ops`) | Email to `alertEmail` |
| `siteDownAlert` (`alert-sahl-down`) | Severity 1 when **2 of 3** locations fail within 5 minutes |

```bash
export MYIP=$(curl -s https://api.ipify.org)
export SAHL_ADMIN_UPN=$(az ad signed-in-user show --query userPrincipalName -o tsv)
export SAHL_ALERT_EMAIL=<your email>
az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam -o none
```

Azure emails you that you were added to the action group `ag-sahl-ops`.

## Step 3: Look at the data

Logs take a few minutes to arrive after the first deploy.

- **Availability:** Portal → `appi-sahl-dev-sdc` → **Availability**.
- **Queries:** Portal → `log-sahl-dev-sdc` → **Logs**. Switch the editor to **KQL mode**.

```kusto
// Slowest pages in the last 24 h (TimeTaken is in milliseconds)
AppServiceHTTPLogs
| where TimeGenerated > ago(24h)
| summarize requests = count(), p50 = percentile(TimeTaken, 50), p95 = percentile(TimeTaken, 95) by CsUriStem
| top 10 by p95
```

```kusto
// Failed requests (5xx)
AppServiceHTTPLogs
| where ScStatus >= 500
| project TimeGenerated, CsMethod, CsUriStem, ScStatus, TimeTaken
| order by TimeGenerated desc
```

```kusto
// Laravel errors (Laravel logs to stderr; LOG_LEVEL=info)
AppServiceConsoleLogs
| where ResultDescription has "ERROR"
| project TimeGenerated, ResultDescription
| order by TimeGenerated desc
```

```kusto
// Starts, restarts and crashes
AppServicePlatformLogs
| project TimeGenerated, Level, Message
| order by TimeGenerated desc
```

**Result (2026-10-13, first ~10 minutes after the deploy at 12:14 UTC):**
- All four tables receive data: `AppServicePlatformLogs`, `AppServiceConsoleLogs`, `AppServiceHTTPLogs` and `AppAvailabilityResults`.
- Availability: France Central 2/2, West Europe 2/2, North Europe 2/3.
- The one failure, at 12:13, was a `500` while the deploy restarted the app. One location failing doesn't fire the alert (it needs 2 of 3), which is why the rule is set up that way.

**Query from the terminal** (no portal; the workspace ID is its `customerId`):

```bash
W=$(az monitor log-analytics workspace show -g rg-sahl-dev-sdc -n log-sahl-dev-sdc --query customerId -o tsv)
az rest --method post --url "https://api.loganalytics.io/v1/workspaces/$W/query" --resource https://api.loganalytics.io \
  --body '{"query":"AppAvailabilityResults | where TimeGenerated > ago(1h) | summarize passed=countif(Success), failed=countif(not(Success)) by Location"}' \
  --query "tables[0].rows"
```

---

## Checklist for a rebuild

- [ ] The three providers registered
- [ ] `SAHL_ALERT_EMAIL` exported, then the Bicep deployed
- [ ] Confirmation email received for `ag-sahl-ops`
- [ ] After about 15 minutes: Availability shows green from 3 locations; `AppServiceHTTPLogs` returns rows
