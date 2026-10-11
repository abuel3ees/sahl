# 02 · Entra External ID for citizen sign-in

**Started:** 2026-10-11, on the current (temporary) Azure account. **Redo** every step on the new account when we switch, then update `.env`.

**Goal:** citizens sign up and sign in to Sahl themselves, in a directory separate from staff. Staff use the workforce tenant ([01](01-entra-id-staff-sign-in.md)); citizens use an **external tenant**.

**Cost:** free up to 50,000 monthly active users. Paid add-ons (e.g. SMS one-time codes) are not used.

---

## Words used here

| Term | Meaning |
|---|---|
| **Workforce tenant** | The directory for employees (the "Default Directory" from guide 01) |
| **External tenant** | A separate directory for customers or citizens: self sign-up, branded pages, consumer accounts. Formerly "Azure AD B2C" / "CIAM" |
| **User flow** | The sign-up/sign-in journey citizens go through: which fields to collect and how they authenticate |
| **ciamlogin.com** | External tenants sign people in at `<tenant>.ciamlogin.com`, not `login.microsoftonline.com` |

**Why a separate tenant:** citizens and employees never share a directory. A citizen account can never see staff, and an admin mistake in one doesn't touch the other.

---

## Step 1: Create the external tenant

1. In **https://entra.microsoft.com** (signed in as Global Admin of the workforce tenant), go to **Entra ID → Overview → Manage tenants → + Create**.
2. Choose **External → Continue**.
3. **Basics:**

| Field | This account |
|---|---|
| Tenant name | `Sahl Citizens` |
| Domain name | `sahlcitizens` → **`sahlcitizens.onmicrosoft.com`**, sign-in at **`sahlcitizens.ciamlogin.com`** |
| Location (where citizen data is stored) | **Germany** |

4. **Add a subscription:** the current subscription, new resource group `rg-sahl-identity`, resource group location **Germany**.
5. Click **Review + create → Create**. It takes a few minutes.
6. Switch to it: top right → **Switch directory** → **Sahl Citizens**. The creator is Global Admin of the new tenant automatically.

> **Architecture note:** citizen data lives in Germany for this PoC. A real Al-Wahah deployment would need in-country data residency; record that in the ADR.
>
> **Always check which tenant you're in** (top right) before changing anything. The two tenants look almost identical.

**Result:** tenant `sahlcitizens.onmicrosoft.com` created in Germany, linked to the subscription through `rg-sahl-identity`.

> **If the new tenant isn't under Switch directory:** check the bell (Notifications) for "Tenant creation succeeded", and look in `rg-sahl-identity` for the tenant resource. Sign out and back in to refresh the directory list, or open it directly at `https://entra.microsoft.com/sahlcitizens.onmicrosoft.com`. If creation failed with a resource-provider error, go to **Subscriptions → Resource providers**, register `Microsoft.AzureActiveDirectory`, and create the tenant again.
>
> This happened the first time: the directory didn't show up right after creation. It appeared afterwards.

**Check (no portal needed):** the tenant's public sign-in configuration answers at
`https://sahlcitizens.ciamlogin.com/sahlcitizens.onmicrosoft.com/v2.0/.well-known/openid-configuration`.

## Step 2: Register the Sahl app in the citizen tenant

Make sure the top right says **Sahl Citizens**.

1. Go to **Entra ID → App registrations → + New registration**:
   - **Name:** `Sahl (local)`
   - **Supported account types:** *Accounts in this organizational directory only*
   - **Redirect URI:** platform **Web**, `http://localhost:8123/auth/citizen/callback`
2. From **Overview**, copy the **Application (client) ID** and the **Directory (tenant) ID**.
3. Go to **Certificates & secrets → + New client secret** (`local dev`, 180 days) and copy the **Value**.
4. Go to **API permissions → + Add a permission → Microsoft Graph → Delegated**: `openid`, `profile`, `email`. Then click **Grant admin consent for Sahl Citizens**; every row shows the green "Granted" tick.
   - Citizens can't approve app permissions themselves, so the admin approves them once.
5. Add to `.env` (never commit):

```dotenv
ENTRA_CITIZEN_TENANT_ID=        # 004a7659-… for this account
ENTRA_CITIZEN_CLIENT_ID=
ENTRA_CITIZEN_CLIENT_SECRET=
ENTRA_CITIZEN_REDIRECT_URI=http://localhost:8123/auth/citizen/callback
ENTRA_CITIZEN_DOMAIN=sahlcitizens
```

**Result:**
- The tenant ID in `.env` matches the tenant's published configuration.
- The client ID differs from the staff app's.
- The sign-in endpoint accepts the client ID and redirect URI: no AADSTS700016 "app not found", no AADSTS50011 "redirect URI mismatch".

## Step 3: National ID attribute and the sign-up/sign-in user flow

> **V1 decision:** the National ID is **self-declared** at sign-up (option A). In V2 a mock *Al-Wahah National Digital ID* provider will supply it verified (option B). Until then Sahl enforces **one National ID per account**, and a seeded demo citizen can be claimed once by the first person who signs up with that ID.

**3a. Custom attribute:** go to **External Identities → Custom user attributes → + Add**:
- **Name:** `NationalId`
- **Data type:** String
- **Description:** `11-digit Al-Wahah National ID`

**3b. User flow:** go to **External Identities → User flows → + New user flow**:
- **Name:** `SignUpSignIn`
- **Email accounts:** **Email with password** (the email address is verified with a one-time code)
- **User attributes:** **Display Name**, **NationalId**

**3c. Page layout:** go to **SignUpSignIn → Page layouts**, find NationalId, and set:
- **Label:** `National ID number (11 digits)`
- **Required:** Yes
- **Pattern:** `^[0-9]{11}$`, if a field for it is offered

**3d. Link the app:** go to **SignUpSignIn → Applications → + Add application → Sahl (local)**.

**3e. Test:** go to **SignUpSignIn → Run user flow** (app Sahl (local), reply URL `http://localhost:8123/auth/citizen/callback`), in a **private window**:
- choose **Create one** and sign up with a real email and a made-up ID (not the seeded demo IDs)
- the browser ends at `…/auth/citizen/callback?code=…`

**What went wrong the first time:** the test stopped with

> AADSTS50020: User account '…@gmail.com' from identity provider 'mail' does not exist in tenant 'Sahl Citizens' and cannot access the application …

The page treated the address as an existing account to *sign in* with. To fix it:
- make sure the app is linked to the flow (3d)
- click **"No account? Create one"** instead of typing the email on the sign-in screen
- test in a private window, so the admin's Microsoft session doesn't interfere
- check the page is on `sahlcitizens.ciamlogin.com`

**Result:** a citizen signed up with email, password, display name and National ID, and was redirected to the callback URL with a code.

## Step 4: Put the National ID into the token

By default custom attributes are stored but not sent to the app. A **claims mapping** adds them to the ID token.

**4a. Add the claim:**
1. Go to **App registrations → Sahl (local) → Overview → "Managed application in local directory"** (the link to the enterprise app).
2. Go to **Single sign-on → Attributes & Claims → Edit → + Add new claim**:
   - **Name:** `national_id`. Sahl reads exactly this name (`SignInCitizenWithEntra::NATIONAL_ID_CLAIM`).
   - **Source:** **Directory schema extension**, then **b2c-extensions-app**, then the `…_NationalId` attribute → **Add → Save**.

**4b. Accept mapped claims:** go to **App registrations → Sahl (local) → Manifest** and set **`acceptMappedClaims`** (in the `api` section) to **`true`** → **Save**.
- Without it, Entra won't add custom claims to this app's tokens.
- Microsoft's guide also sets `isFallbackPublicClient: true`. We skip it: that's for mobile and single-page apps, and Sahl is a server-side app with a secret.

## Step 5: Test the sign-in

1. Open a private window at `http://localhost:8123/login`, stay on **Citizens & residents**, and click **Continue with Sahl ID**.
2. Sign in with the account from step 3. You land on `/account` with your name and the National ID you entered.
3. **Demo claim:** sign up with a *second* email and the National ID `28763404821`. You land on `/account` with Ahmed's demo requests.

**What went wrong the first time:** the login page said "We couldn't verify your sign-in with Microsoft". The log said:

> Citizen sign-in refused {"reason":"failed","detail":"ID token signature is invalid: JWK must contain an \"alg\" parameter"}

The external tenant's published signing keys (`/discovery/v2.0/keys`) have no `alg` field. The fix was in code: `EntraExternalIdProvider` now supplies `RS256`. Nothing to change in the portal.

**Result (2026-10-11):**
- Sign-in worked.
- The ID token carried `aud, iss, iat, nbf, exp, email, name, oid, preferred_username, rh, sid, sub, tid, uti, ver, national_id`.
- Sahl created the citizen and linked it by `oid`.

**Why citizens still type an email:** External ID's own accounts sign in with email (or phone/username) plus a password or code. Real GCC portals use a national digital ID instead: type the ID, then approve in the phone app. That's V2 (mock national ID provider federated into External ID, email made optional). The ID number alone is never a login, because it isn't secret.

| If you see… | It means… |
|---|---|
| AADSTS50020 "does not exist in tenant" | You tried to *sign in* with a new email. Use **Create one**, check the app is linked to the user flow (3d), and use a private window |
| "no valid National ID" on Sahl | The token has no `national_id`. Check the claim name and `acceptMappedClaims` (step 4) |
| "already linked to another Sahl account" | Another Sahl ID holds that National ID (one ID per account) |
| "couldn't verify your sign-in" | Token exchange or validation failed. Read `Citizen sign-in refused` in `storage/logs/laravel.log`. Often a wrong or expired client secret |

---

## Checklist for the new account

- [ ] Step 1: create the external tenant `Sahl Citizens` (location, subscription, `rg-sahl-identity`) and note the domain
- [ ] Step 2: register `Sahl (local)` with redirect `/auth/citizen/callback`, add a secret and `openid profile email`, grant admin consent, then fill all `ENTRA_CITIZEN_*` in `.env`
- [ ] Step 3: create custom attribute `NationalId`, user flow `SignUpSignIn` (email + password, Display Name + NationalId required), link Sahl (local), then test sign-up in a private window
- [ ] Step 4: add claim `national_id` (directory schema extension → b2c-extensions-app → NationalId) and set `acceptMappedClaims: true` in the manifest
- [ ] Step 5: sign in from `/login` → Citizens & residents, land on `/account`, then claim a demo citizen with a second account
