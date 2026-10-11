# 01 · Entra ID for staff sign-in

**Done:** 2026-10-11, on the current (temporary) Azure account. **Redo** every step on the new free account when we switch, then update `.env`.

**Goal:** ministry employees (MOI reviewers) sign in to Sahl with Microsoft Entra ID, the "workforce" directory, instead of a password stored by Sahl. Entra handles the password and MFA, and tells Sahl who the person is and which role they have.

**Cost:** free. Entra ID Free covers app registrations, users, MFA (security defaults) and app roles assigned to users.

---

## Words used here

| Term | Meaning |
|---|---|
| **Tenant / directory** | Your organisation's identity space: users, groups and app registrations. Created automatically when you sign up for Azure ("Default Directory"). |
| **Subscription** | Where Azure resources and billing live. Entra ID does **not** need one. |
| **App registration** | Tells Entra that an app called Sahl exists, where it may send people back to, and how it proves its identity (the secret). |
| **Enterprise application** | Created automatically with the registration. The app's presence in your tenant, where you assign users to roles. |
| **App role** | A role that belongs to the Sahl app (e.g. `MOI.Reviewer`). It appears in the sign-in token as `roles`. |
| **OIDC** | OpenID Connect, the sign-in protocol. Sahl uses the server-side *authorization code* flow. |

---

## Step 1: Find the tenant

1. Open **https://entra.microsoft.com** and sign in with the Azure account.
2. Go to **Entra ID → Overview**.
3. Note the following:

| Value | This account |
|---|---|
| Primary domain | `swambfirehotmail.onmicrosoft.com` |
| License | Microsoft Entra ID Free |
| Tenant ID | in `.env` as `ENTRA_TENANT_ID` |

4. Check your role: **Users → (you) → Assigned roles** shows **Global Administrator**.

## Step 2: Create a test staff user

The Azure login (a personal hotmail account) sits in the tenant as an *external* account. A real reviewer would be a user created **in** the ministry's directory, so we create one.

1. Go to **Entra ID → Users → All users → + New user → Create new user**.
2. **Basics** tab:
   - **User principal name:** `reviewer@swambfirehotmail.onmicrosoft.com`
   - **Display name:** a fictional reviewer name
   - **Password:** auto-generate, then copy it
   - **Account enabled:** ticked
3. **Properties** tab: **Job title** `Reviewer`, **Department** `Ministry of Interior`.
4. **Assignments** tab: leave empty. No admin roles.
5. Click **Review + create → Create**.
6. Test it in a private window at **https://myapps.microsoft.com**:
   - sign in as the new user
   - change the temporary password
   - set up MFA with Microsoft Authenticator. New tenants turn on "security defaults", which require MFA.

**Result:** the sign-in worked and MFA was required and set up. The password is not stored anywhere in the repo.

## Step 3: Register the Sahl app

1. Go to **Entra ID → App registrations → + New registration**.
   - **Name:** `Sahl (local)`
   - **Supported account types:** *Accounts in this organizational directory only (Single tenant)*
   - **Redirect URI:** platform **Web**, `http://localhost:8123/auth/staff/callback`
2. Click **Register**. From **Overview**, copy the **Application (client) ID** and the **Directory (tenant) ID**.
3. Go to **Certificates & secrets → Client secrets → + New client secret**:
   - **Description:** `local dev`, **Expires:** 180 days
   - Copy the **Value** right away; it's shown once. The **Secret ID** is not the secret.
4. Go to **Authentication**: confirm the redirect URI. Leave **Implicit grant** (access tokens, ID tokens) **unticked**. Sahl uses the server-side code flow.
5. Put the values in `.env` (never commit them):

```dotenv
ENTRA_TENANT_ID=
ENTRA_CLIENT_ID=
ENTRA_CLIENT_SECRET=
ENTRA_REDIRECT_URI=http://localhost:8123/auth/staff/callback
```

> Browse the app at `http://localhost:8123`, **not** `127.0.0.1:8123`. Entra only redirects to the exact registered address, and cookies are kept separately for each hostname.
>
> The secret expires after 180 days. In Azure it will move to **Key Vault**, and later be replaced by a **managed identity** (no secret at all).

## Step 4: Create the Reviewer app role and assign it

**A. Define the role** on the app registration:
1. Go to **App registrations → Sahl (local) → App roles → + Create app role**:
   - **Display name:** `MOI Reviewer`
   - **Allowed member types:** Users/Groups
   - **Value:** `MOI.Reviewer`. This exact string appears in the token.
   - **Description:** `Reviews and decides Ministry of Interior applications`
   - **Enable:** ticked
2. Click **Apply**.

**B. Assign it** on the enterprise application:

3. Go to **Enterprise applications → Sahl (local) → Users and groups → + Add user/group**. Pick the reviewer user and the role **MOI Reviewer**, then click **Assign**.
   - On Entra ID Free you can assign **users** only. Assigning **groups** to roles needs P1.

**C. Lock it down:**

4. Go to **Enterprise applications → Sahl (local) → Properties**, set **Assignment required?** to **Yes**, and click **Save**. Users who haven't been assigned are refused by Entra before they reach Sahl.

**Result:** when the reviewer signs in to Sahl, the ID token contains `"roles": ["MOI.Reviewer"]`.

## Step 5: Grant admin consent

**What happened first:** the reviewer's first sign-in stopped at **"Need admin approval: Sahl (Local) needs permission to access resources in your organisation that only an admin can grant."**

**Why:** Sahl asks for `openid profile User.Read`. New tenants only let normal users approve permissions for apps from a *verified publisher*, and "Sahl (local)" isn't one. An admin approves it once for everyone, which is also how a real ministry works: IT approves apps, not employees.

1. On the Microsoft screen, click **Return to the application without granting consent**. Sahl shows the "cancelled" message.
2. As the Global Admin, go to **Entra ID → App registrations → Sahl (local) → API permissions**.
3. Click **Grant admin consent for Default Directory → Yes**.
4. **Microsoft Graph → User.Read** (Delegated) now shows a green tick: **Granted for Default Directory**.

> **Delegated** means Sahl can only read the profile of the person who is signed in, never anyone else's.

## Step 6: Test the sign-in

1. Open a private window at `http://localhost:8123/login`.
2. Choose **Government staff → Continue with Microsoft**.
3. Sign in as `reviewer@swambfirehotmail.onmicrosoft.com` and complete MFA.
4. You land on `/staff`, which shows the name, **Ministry of Interior (MOI)**, role Reviewer, and "Signed in with Microsoft Entra ID".
5. **Sign out → Continue with Microsoft** again: it skips the password, because Sahl's sign-out leaves the Microsoft session open.

**Result (2026-10-11):** worked. Sahl created the employee row on first sign-in: type `employee`, ministry `MOI`, linked by `entra_object_id`, no password stored.

| If you see… | It means… |
|---|---|
| "Need admin approval" | Step 5 was skipped |
| AADSTS50105 "not assigned to a role" | The user isn't assigned under Enterprise applications → Users and groups (step 4B) |
| AADSTS50011 "redirect URI mismatch" | The browser used `127.0.0.1` or another port. Use `http://localhost:8123`, or fix the redirect URI (step 3) |
| Sahl says "isn't assigned a reviewer role" | The token had no `{CODE}.Reviewer` role. Check the role **Value** is exactly `MOI.Reviewer` |
| Sahl says "couldn't verify" | Token exchange or validation failed. The reason is in `storage/logs/laravel.log` ("Staff sign-in refused"). Often a wrong or expired client secret |

---

## Checklist for the new account

- [ ] Step 1: note the new primary domain and tenant ID
- [ ] Step 2: create the `reviewer@<new-domain>` user, then test sign-in and MFA
- [ ] Step 3: create the `Sahl (local)` registration with the same redirect URI and a new secret, then update all `ENTRA_*` in `.env`
- [ ] Step 4: create the `MOI.Reviewer` role, assign the reviewer, and set Assignment required to Yes
- [ ] Step 5: grant admin consent for `User.Read`
- [ ] Step 6: sign in from `/login` → Government staff, and land on `/staff`
