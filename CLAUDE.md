# Sahl (سهل): project guide

Sahl is a **fictional** GCC-style government e-services portal for the fictional "State of Al-Wahah". It's a career/portfolio project for learning system architecture on Azure. Never use real government names, logos or systems.

## Current scope: V1

One ministry (**Ministry of Interior**, `MOI`) and one service (**Good Conduct Certificate**, `GOOD_CONDUCT`), built end to end:
citizen submits → MOI reviewer approves or rejects → certificate issued → anyone verifies it by code/QR.
Everything else (APIM, Front Door, Functions, Service Bus, payments, more ministries, AI) is deliberately **later**. Don't pull it in unless asked.

**Planned for V2:** a mock *Al-Wahah National Digital ID* provider federated into Entra External ID, so the National ID is verified. In V1 citizens self-declare it at sign-up.

## Work journal (always do this)

After every unit of work, add a folder `docs/journal/YYYY-MM-DD_HHMM_short-topic/` (local time, UTC+3) containing a `README.md` with the sections: **What was asked** (quote the user), **What we did**, **Decisions** (if any), **Files**. Then add a row to the table in `docs/journal/README.md`. Do this before the final reply for that piece of work.

## Setup guides for manual steps (always do this)

Whenever the user does something by hand (Azure portal, Entra, CLI, accounts, secrets), document every step they did in its own file `docs/setup/NN-topic.md` (numbered in order): what was clicked or typed, the exact values used (never secrets), the result, and a checklist for redoing it. The user must be able to rebuild everything from these files (for example after deleting the resource group). Link the guide from that piece of work's journal entry.

## Working agreements

- **Small steps, working software first.** The user found a large up-front design demotivating. Build something visible, then explain only what the next step needs.
- **The user makes the architecture decisions.** Explain the options, recommend one, and let them choose. Record significant decisions.
- **Don't write Bicep or infrastructure code for the user.** They are learning it (`docs/learning/bicep-guide.md`). Teach, let them write it, then review it like a PR.
- The user is an experienced Laravel developer and new to Azure. Explain Azure concepts from zero; don't over-explain Laravel.

## Map of the repository

| Path | What |
|---|---|
| `app/Actions/Applications/` | Business rules: submit, approve (issues the certificate), reject. Shared by the API and the website |
| `app/Http/Controllers/Api/V1/` | JSON API (`/api/v1`), Sanctum Bearer tokens |
| `app/Http/Controllers/Web/` | Inertia pages: landing, `/login`, public certificate check `/verify/{code}` (what the QR opens), `/developers`, `/docs/{page}`. Back office under `Staff/`: queue `/staff`, request `/staff/applications/{id}`, approve/reject (reuses the API's form requests and actions) |
| `app/Actions/Auth/SignInCitizenWithEntra.php`, `app/Auth/EntraExternalIdProvider.php` | Citizen sign-in: Entra External ID (`entra_citizen` driver, `{domain}.ciamlogin.com`). Reads the `national_id` claim. One National ID per account; an unclaimed seeded citizen is claimed by the first sign-in with that ID. Citizen home: `/account`. Request form `/services/{slug}/apply`, which reuses the API's `StoreApplicationRequest` via `Web\SubmitApplicationRequest` (adds the declaration) |
| `app/Actions/Auth/SignInStaffWithEntra.php` | Staff sign-in: Entra ID via Socialite (`entra` driver). App role `{CODE}.Reviewer` (e.g. `MOI.Reviewer`) decides the ministry; users are linked by `entra_object_id` |
| `app/Policies/ApplicationPolicy.php` | Who can see/decide. Cross-tenant or other people's records return **404, not 403** |
| `app/Certificates/CertificatePdf.php` | Certificate PDF (mPDF, Arabic shaping, QR to `/verify/{code}`), from the snapshot on download. Font: `resources/fonts/ibm-plex-sans-arabic/` (OFL). Later: generate once at issue, sign with Key Vault, store in immutable Blob |
| `resources/js/` | React 19 + TypeScript + Inertia v3. `pages/`, `layouts/`, `components/site/`, `lib/i18n.tsx` |
| `resources/css/app.css` | Design tokens ("Modernist": Archivo / IBM Plex Sans Arabic / Reem Kufi, red `#ec3013`, 2px rules, no radius) in Tailwind v4 `@theme` |
| `docs/api/openapi.json` | Generated Swagger file. Regenerate with `php artisan scramble:export` after any API change |
| `docs/v1/ministry-of-interior.md` | V1 documentation, also served at `/docs/ministry-of-interior` |
| `docs/learning/`, `docs/architecture/erd.md` | Study guides and the full (future) data model |
| `docs/journal/` | The dated work log (see above) |
| `docs/setup/` | Step-by-step guides for everything the user did by hand in Azure/Entra (see above) |
| `infra/main.bicep` | Azure resources (user-written Bicep): Postgres Flexible Server, App Service plan and web app (managed identity), Key Vault `kv-sahl-dev-sdc-vmi3r3` (secrets as Key Vault references), Sweden Central. Values in `infra/main.bicepparam`, `rg-sahl-dev-sdc`. Live at `https://app-sahl-dev-sdc-vmi3r3.azurewebsites.net` |
| `infra/app-service/` | App Service glue: `nginx.conf` (web root `public/`), `startup.sh` (startup command), `package.sh` (builds `build/sahl.zip`; deploy with `az webapp deploy`) |

## Conventions

- **Bilingual:** Arabic is the default (`APP_LOCALE=ar`, RTL). UI strings are `t({ en, ar })` pairs, the same `{ ar, en }` shape the API returns. Use logical Tailwind classes (`ms-`, `pe-`, `start-`, `border-s`) and `rtl:` variants, never left/right.
- **Language switching:** `SetLocale` middleware reads `?lang=` then the `lang` cookie (unencrypted on purpose).
- **IDs and data:** applications and certificates use UUIDv7. Money is in minor units (`fee_minor`). Certificate content is a snapshot taken at issue time.
- **Motion:** use `motion-safe:` and respect `usePrefersReducedMotion`.
- **New web pages:** add a feature test with `assertInertia`. Tests call `withoutVite()` in `tests/TestCase.php`.

## Running locally

- Database: **Postgres 17 in Docker** (OrbStack engine): `docker compose up -d` (`compose.yaml`, service `pgsql`, port 5432, db/user `sahl`, password `secret`). Tests use the `sahl_testing` database in the same container (`phpunit.xml`), so the container must be running for tests. Run `php artisan migrate:fresh --seed`. That includes `DemoApplicationSeeder`: 8 MOI requests, 2 of them overdue. Staff created by Entra sign-in are recreated on their next sign-in.
- Serve on **port 8123**. Port 8000 is used by another of the user's projects (`loqmah`). Run `php artisan serve --port=8123`, plus `npm run dev`; the user often has Vite running already.
- Open the app at **`http://localhost:8123`** (not `127.0.0.1`): Entra only returns to the registered redirect URI. Staff sign in with the Entra test reviewer (`ENTRA_*` in `.env`). Citizens sign up or sign in with Sahl ID, which is Entra External ID (`ENTRA_CITIZEN_*`).
- Demo accounts (password `password`, API and seeded data): citizen `28763404821`, citizen `29163401177`, reviewer `reviewer@moi.sahl.test`.
- Checks: `php artisan test --compact`, `npm run typecheck`, `npm run build`, `vendor/bin/pint --dirty --format agent`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
