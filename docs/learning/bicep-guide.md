# Bicep from Zero: a Step-by-Step Course

> Companion to `sahl-architecture-guide.md` (Chapter 20 covers *why* we use Infrastructure as Code; this course teaches *how*).
> Assumes: you know programming (PHP/Laravel), you've never written Bicep, and you've read **Chapter A (Azure from absolute zero)**.

---

## How to use this course

- Do the lessons **in order**. Each one builds on the previous.
- Every lesson has: **concept → guided practice (you type it) → exercise (you write it alone) → solution (hidden, open only after trying)**.
- Lessons 1–10 need **no Azure account**: we only *compile* Bicep on your laptop. Lesson 11 (deploying) waits for Phase 2, when the free account is activated.
- All exercise files go in `docs/learning/exercises/bicep/`.
- When you finish a lesson's exercise, ask for a review — treat it like a pull request.

| # | Lesson | Needs Azure? |
|---|---|---|
| 0 | Setup | No |
| 1 | What Bicep is | No |
| 2 | Your first file, step by step | No |
| 3 | Parameters and parameter files | No |
| 4 | Variables, expressions and functions | No |
| 5 | Referencing resources, dependencies, child resources | No |
| 6 | Conditions and loops | No |
| 7 | `existing` resources | No |
| 8 | Modules | No |
| 9 | Scopes: resource group vs subscription | No |
| 10 | Linting, secrets and good practice | No |
| 11 | Deploying for real: what-if, create, clean up | **Yes (Phase 2)** |
| 12 | How Sahl's `/infra` folder will be organized | No |

---

## Lesson 0 — Setup

### What you need

| Tool | Status | Check with |
|---|---|---|
| Azure CLI | ✅ installed (2.90) | `az version` |
| Bicep CLI | ✅ installed (0.48) via `az bicep install` | `az bicep version` |
| Editor extension | ⬜ install now | — |

### Install the editor extension (do not skip)

The extension is what makes Bicep learnable: autocomplete for every resource and property, red underlines for errors, hover documentation, and a visual diagram of your file.

- **VS Code**: Extensions panel → search **"Bicep"** (publisher: Microsoft) → Install.
- **PhpStorm / JetBrains**: Settings → Plugins → Marketplace → search **"Bicep"** → Install. (The VS Code extension is the most complete; it's worth having VS Code just for Bicep.)

### Check it works

1. Create the folder `docs/learning/exercises/bicep/`.
2. Create an empty file `test.bicep` in it and open it.
3. Type `res` and wait — you should see suggestions like `res-storage`, `res-keyvault`. That's the extension working.
4. Delete `test.bicep`.

---

## Lesson 1 — What Bicep is

### 1.1 The idea

A Bicep file is a **description of what should exist in Azure**. You hand it to Azure; Azure makes reality match the description.

### 1.2 Bicep vs Laravel migrations

| Laravel migration | Bicep |
|---|---|
| **Steps**: "create table, then add a column, then rename…" | **Desired state**: "this is what should exist" |
| Files run in order, each once | No history; the file *is* the current truth |
| To change something, write a *new* migration | To change something, **edit the same file** and deploy again |
| — | Deploying twice gives the **same result** → **idempotent** |

If you add a tag to a resource in your Bicep file and redeploy, Azure notices that only the tag is different and only changes the tag.

### 1.3 What happens under the hood

```
main.bicep
   │  az bicep build          → compiles on your laptop (no Azure needed)
   ▼
main.json  (ARM template)    ← the only format Azure Resource Manager understands
   │  az deployment group create
   ▼
Azure Resource Manager → compares desired vs actual → creates / updates resources
```

Bicep → ARM JSON is like TypeScript → JavaScript. You write the nice language; the machine gets the verbose one.

### 1.4 The four building blocks

```bicep
param  location string = 'germanywestcentral'   // INPUT — like a function argument
var    name = 'stsahldev'                        // COMPUTED VALUE — like a local variable
resource st 'Microsoft.Storage/storageAccounts@2023-05-01' = { … }   // THING TO CREATE
output storageName string = st.name              // RESULT — like a return value
```

### 1.5 Reading a resource declaration

```bicep
resource myStorage 'Microsoft.Storage/storageAccounts@2023-05-01' = {
```

| Part | Meaning |
|---|---|
| `resource` | Keyword: "declare something to create" |
| `myStorage` | **Symbolic name** — used only *inside this file* to refer to it. Not the Azure name |
| `Microsoft.Storage` | **Resource provider** |
| `storageAccounts` | **Resource type** |
| `@2023-05-01` | **API version** — which version of that resource's schema you're writing against |

Common properties inside `{ }`:
- `name` — the real name in Azure (what you see in the portal)
- `location` — the region
- `sku` — size/tier (if the resource has one)
- `kind` — sub-variety (some resources)
- `properties` — resource-specific settings
- `tags` — labels

### 1.6 Where do property names come from?

Nobody memorizes them. You use:
1. **Editor autocomplete** (Ctrl+Space inside `{ }`).
2. **The Azure template reference**: search the web for *"Bicep Microsoft.Storage storageAccounts template reference"*. Every resource type has a page listing every property, its type, and allowed values.

### 1.7 Syntax cheat sheet

| Thing | Bicep | PHP equivalent |
|---|---|---|
| String | `'hello'` (single quotes only) | `'hello'` |
| Interpolation | `'st${env}sahl'` | `"st{$env}sahl"` |
| Comment | `// …` or `/* … */` | same |
| Object | `{ key: 'value' }` (newline-separated, no commas) | `['key' => 'value']` |
| Array | `[ 'a', 'b' ]` or one item per line | `['a', 'b']` |
| Boolean | `true` / `false` | same |
| Ternary | `env == 'prod' ? 'a' : 'b'` | same |
| Property access | `myStorage.name`, `myStorage.properties.x` | `$myStorage->name` |

### ✅ Self-check

- Why can you deploy the same Bicep file 10 times safely?
- What's the difference between the symbolic name and the `name` property?
- What does `@2023-05-01` mean?

---

## Lesson 2 — Your first file, step by step

**Goal:** a Bicep file that describes one Storage account, compiled locally.

Create `docs/learning/exercises/bicep/lesson02-storage.bicep`. Type each step yourself — don't copy-paste. Typing is how the syntax sticks, and you'll see the autocomplete help you.

### Step 1 — A parameter for the region

```bicep
param location string = 'germanywestcentral'
```

- `param` → this is an input.
- `location` → its name.
- `string` → its type. (Bicep types: `string`, `int`, `bool`, `object`, `array`.)
- `= 'germanywestcentral'` → a **default value**, used if nobody passes one.

### Step 2 — A parameter with rules (a decorator)

```bicep
@allowed([
  'dev'
  'prod'
])
param environment string = 'dev'
```

`@allowed` is a **decorator**: a rule attached to the line below it. If someone tries `environment=staging`, the deployment is rejected *before* anything is created. Like a Laravel validation rule `in:dev,prod`.

Add a description too (it shows up in tooling and the portal):

```bicep
@description('Which environment this deployment is for.')
@allowed([
  'dev'
  'prod'
])
param environment string = 'dev'
```

### Step 3 — A variable for the name

```bicep
var storageAccountName = 'stsahl${environment}'
```

Storage account names have strict rules: **3–24 characters, lowercase letters and digits only, globally unique across all of Azure**. `stsahldev` follows our naming convention (`st` = storage account prefix from the Cloud Adoption Framework).

> ⚠ "Globally unique" means if anyone in the world already has `stsahldev`, your deploy fails. Lesson 4 shows how to fix this with `uniqueString()`.

### Step 4 — Start the resource

Type `resource sa ` and then a quote — autocomplete will offer resource types. Pick `Microsoft.Storage/storageAccounts` and the newest stable API version it offers. You should end up with:

```bicep
resource sa 'Microsoft.Storage/storageAccounts@2023-05-01' = {
  name: storageAccountName
  location: location
}
```

Notice: `name` and `location` use your **variable** and **parameter** — no quotes, because they're references, not text.

The editor will now show a **red underline**: required properties are missing. Hover to read the error. Learning to read these is the main skill.

### Step 5 — Add SKU and kind

```bicep
  sku: {
    name: 'Standard_LRS'
  }
  kind: 'StorageV2'
```

- `Standard_LRS` → **L**ocally **R**edundant **S**torage: 3 copies in one datacenter. Cheapest. Other options: `ZRS` (across availability zones), `GRS` (copied to the paired region — careful with residency!).
- `StorageV2` → the modern general-purpose account type.

### Step 6 — Add security settings

```bicep
  properties: {
    minimumTlsVersion: 'TLS1_2'
    allowBlobPublicAccess: false
    supportsHttpsTrafficOnly: true
  }
```

These three lines are a mini security baseline — every Sahl storage account will have them:
- reject old TLS versions,
- never allow anonymous public access to files,
- refuse plain HTTP.

### Step 7 — Add tags

```bicep
  tags: {
    workload: 'sahl'
    environment: environment
  }
```

The left `environment` is the **tag key** (text); the right `environment` is your **parameter** (a reference).

### Step 8 — Add an output

After the closing `}` of the resource:

```bicep
output storageAccountName string = sa.name
```

`sa.name` reads the `name` from the resource via its **symbolic name** `sa`.

### Step 9 — Compile it

In the terminal, from the project root:

```bash
az bicep build --file docs/learning/exercises/bicep/lesson02-storage.bicep
```

- No output = success. A file `lesson02-storage.json` appears next to it.
- Errors print with **line numbers** — fix and rebuild.

### Step 10 — Look at what Azure actually receives

Open `lesson02-storage.json`. You'll see `$schema`, `parameters`, `variables`, `resources`, `outputs` — the same four building blocks, in verbose JSON. Notice how much shorter Bicep is. Then **delete the .json** — we never commit compiled output.

### Step 11 — Break it on purpose (important!)

Try each, rebuild, read the error, undo:
1. Change `'Standard_LRS'` to `'Cheap'`.
2. Remove `location: location`.
3. Use double quotes: `"dev"`.
4. Write `envirnoment` (typo) in the tag.
5. Add a parameter `param unused string = 'x'` that you never use → that's a **linter warning**, not an error.

Knowing what errors look like *before* you need to is half of debugging.

### ✏ Exercise 2

Without looking at the steps, write `lesson02-keyvault.bicep` describing a **Key Vault**:
- parameters `location` and `environment` (dev/prod only),
- name `kv-sahl-<environment>`,
- SKU family `A`, name `standard`,
- `tenantId` set to `subscription().tenantId` (a built-in function — the Entra tenant of the subscription),
- `enableRbacAuthorization: true` (use Azure RBAC instead of old access policies),
- `enableSoftDelete: true` and `enablePurgeProtection: true` (Chapter 14 — why are these important?),
- tag `workload: 'sahl'`,
- output the vault URI (`properties.vaultUri`).

Use autocomplete and the template reference for `Microsoft.KeyVault/vaults`. Compile it.

<details>
<summary>Solution (open only after trying)</summary>

```bicep
param location string = 'germanywestcentral'

@allowed([
  'dev'
  'prod'
])
param environment string = 'dev'

resource kv 'Microsoft.KeyVault/vaults@2023-07-01' = {
  name: 'kv-sahl-${environment}'
  location: location
  properties: {
    sku: {
      family: 'A'
      name: 'standard'
    }
    tenantId: subscription().tenantId
    enableRbacAuthorization: true
    enableSoftDelete: true
    enablePurgeProtection: true
  }
  tags: {
    workload: 'sahl'
  }
}

output vaultUri string = kv.properties.vaultUri
```

Note: for Key Vault, `sku` sits **inside** `properties` — unlike Storage, where it's top-level. Every resource type has its own shape; that's why the template reference and autocomplete matter.
</details>

---

## Lesson 3 — Parameters and parameter files

### 3.1 Decorators you'll use constantly

```bicep
@description('Short explanation shown in tooling.')
@allowed([ 'dev', 'prod' ])
@minLength(3)
@maxLength(24)
@minValue(1)
@maxValue(10)
@secure()            // value is never logged or shown in deployment history
param example string
```

`@secure()` is for passwords/keys passed in. (In Sahl we'll avoid secrets entirely with managed identities, but you need to know it.)

### 3.2 Parameters without defaults are required

```bicep
param environment string          // no default → MUST be provided at deploy time
param location string = 'germanywestcentral'   // optional
```

### 3.3 Object and array parameters

```bicep
param tags object = {
  workload: 'sahl'
  owner: 'platform-team'
}

param allowedIpRanges array = [
  '203.0.113.0/24'
]
```

### 3.4 Parameter files (`.bicepparam`)

Instead of typing values on the command line, put each environment's values in its own file. **Same Bicep file, different parameter files** — that's how one codebase produces dev and prod.

`main.bicep`:
```bicep
param environment string
param location string
param postgresSku string
```

`dev.bicepparam`:
```bicep
using './main.bicep'

param environment = 'dev'
param location = 'germanywestcentral'
param postgresSku = 'Standard_B1ms'      // cheap, burstable
```

`prod.bicepparam`:
```bicep
using './main.bicep'

param environment = 'prod'
param location = 'germanywestcentral'
param postgresSku = 'Standard_D2ds_v5'   // general purpose
```

- `using` links the parameter file to its Bicep file, so the editor checks names and types.
- Laravel analogy: `.env.dev` vs `.env.prod` for the same app.

Compile a parameter file to check it: `az bicep build-params --file dev.bicepparam`.

### ✏ Exercise 3

Copy your Lesson 2 storage file to `lesson03/main.bicep`. Change it so:
- `environment` has **no default** (required),
- there's a `tags` object parameter merged into the resource's tags,
- there are two parameter files: `dev.bicepparam` and `prod.bicepparam`,
- in prod, the SKU is `Standard_ZRS`; in dev, `Standard_LRS` (hint: make the SKU a parameter with `@allowed`).

Compile both parameter files.

<details>
<summary>Solution</summary>

`main.bicep`
```bicep
param location string = 'germanywestcentral'

@allowed([ 'dev', 'prod' ])
param environment string

@allowed([ 'Standard_LRS', 'Standard_ZRS' ])
param storageSku string

param tags object = {}

resource sa 'Microsoft.Storage/storageAccounts@2023-05-01' = {
  name: 'stsahl${environment}'
  location: location
  sku: {
    name: storageSku
  }
  kind: 'StorageV2'
  properties: {
    minimumTlsVersion: 'TLS1_2'
    allowBlobPublicAccess: false
    supportsHttpsTrafficOnly: true
  }
  tags: union(tags, { environment: environment })
}
```

`dev.bicepparam`
```bicep
using './main.bicep'
param environment = 'dev'
param storageSku = 'Standard_LRS'
param tags = { workload: 'sahl' }
```

`prod.bicepparam`
```bicep
using './main.bicep'
param environment = 'prod'
param storageSku = 'Standard_ZRS'
param tags = { workload: 'sahl' }
```

`union()` merges objects (like PHP's `array_merge`).
</details>

---

## Lesson 4 — Variables, expressions and functions

### 4.1 Variables

```bicep
var prefix = 'sahl'
var isProd = environment == 'prod'                   // bool
var regionShort = location == 'germanywestcentral' ? 'gwc' : 'qac'
var rgTagSet = {
  workload: prefix
  environment: environment
}
```

Variables have no type annotation — Bicep infers it.

### 4.2 Functions you'll use all the time

| Function | What it does | Example |
|---|---|---|
| `resourceGroup()` | Info about the resource group you're deploying into | `resourceGroup().location`, `resourceGroup().id` |
| `subscription()` | Info about the subscription | `subscription().tenantId` |
| `uniqueString(...)` | Deterministic 13-char hash of its inputs | `uniqueString(resourceGroup().id)` |
| `toLower()` / `toUpper()` | Case | `toLower('ABC')` |
| `take(str, n)` | First n characters | `take(name, 24)` |
| `replace()` | Replace text | `replace('a-b', '-', '')` |
| `union()` | Merge objects/arrays | `union(tags, { x: 'y' })` |
| `contains()` | Membership test | `contains(['dev'], env)` |
| `length()` | Length of string/array | `length(names)` |
| `concat()` | Join arrays | `concat(a, b)` |

### 4.3 Solving "globally unique" names with `uniqueString`

```bicep
var storageAccountName = take('stsahl${environment}${uniqueString(resourceGroup().id)}', 24)
```

- `uniqueString(resourceGroup().id)` gives something like `x7k2m9q4p1abc`.
- It's **deterministic**: same resource group → same string every time. So redeploying doesn't create a second account (idempotency preserved).
- `take(…, 24)` enforces the 24-character limit.

### 4.4 The Sahl naming convention as variables

Pattern: `<type>-<workload>-<env>-<regionShort>`

```bicep
var regionShort = 'gwc'
var names = {
  keyVault:   'kv-sahl-${environment}-${regionShort}'
  postgres:   'psql-sahl-${environment}-${regionShort}'
  logs:       'log-sahl-${environment}-${regionShort}'
  storage:    take('stsahl${environment}${uniqueString(resourceGroup().id)}', 24)  // no hyphens allowed
}
```

Then use `names.keyVault`, `names.postgres`, etc.

### ✏ Exercise 4

In `lesson04.bicep`: build a `names` object for **Key Vault, Storage, Log Analytics workspace, and Container Registry** (`cr` prefix — note: Container Registry names also allow **no hyphens**, 5–50 alphanumerics). Output the whole `names` object (`output names object = names`). Compile and read the JSON to see how expressions compile.

<details>
<summary>Solution</summary>

```bicep
@allowed([ 'dev', 'prod' ])
param environment string = 'dev'
param location string = 'germanywestcentral'

var regionShort = location == 'germanywestcentral' ? 'gwc' : 'qac'
var suffix = uniqueString(resourceGroup().id)

var names = {
  keyVault: 'kv-sahl-${environment}-${regionShort}'
  storage: take('stsahl${environment}${suffix}', 24)
  logAnalytics: 'log-sahl-${environment}-${regionShort}'
  containerRegistry: take('crsahl${environment}${suffix}', 50)
}

output names object = names
```
</details>

---

## Lesson 5 — Referencing resources, dependencies, child resources

### 5.1 Referencing another resource

```bicep
resource logs 'Microsoft.OperationalInsights/workspaces@2023-09-01' = {
  name: 'log-sahl-dev-gwc'
  location: location
  properties: {
    sku: { name: 'PerGB2018' }
    retentionInDays: 30
  }
}

resource appInsights 'Microsoft.Insights/components@2020-02-02' = {
  name: 'appi-sahl-dev-gwc'
  location: location
  kind: 'web'
  properties: {
    Application_Type: 'web'
    WorkspaceResourceId: logs.id      // ← reference to the resource above
  }
}
```

`logs.id` is the full **resource ID** (Chapter A.2).

### 5.2 Implicit dependencies (the magic part)

Because `appInsights` uses `logs.id`, Bicep **knows** the workspace must exist first and orders the deployment automatically. Resources with no references between them are deployed **in parallel**.

You almost never write ordering yourself. If you must (rare), there's `dependsOn: [ logs ]`.

### 5.3 Child resources

Some resources live *inside* others: a blob **container** lives inside a storage account's **blob service**.

Option A — the `parent` property (preferred, flat and readable):

```bicep
resource sa 'Microsoft.Storage/storageAccounts@2023-05-01' = { … }

resource blobService 'Microsoft.Storage/storageAccounts/blobServices@2023-05-01' = {
  parent: sa
  name: 'default'          // blob service is always named 'default'
}

resource documents 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = {
  parent: blobService
  name: 'documents'
  properties: {
    publicAccess: 'None'
  }
}
```

Option B — nesting inside the parent (works, but deep nesting gets hard to read).

The type string shows the hierarchy: `storageAccounts/blobServices/containers`.

### ✏ Exercise 5

Extend your storage file: add the blob service and **two** containers — `documents` and `audit`. Then add a Log Analytics workspace and output both the storage account ID and the workspace ID. Open the editor's **Bicep Visualizer** (VS Code: right-click the file → "Open Bicep Visualizer") and look at the dependency arrows.

<details>
<summary>Solution (containers part)</summary>

```bicep
resource blobService 'Microsoft.Storage/storageAccounts/blobServices@2023-05-01' = {
  parent: sa
  name: 'default'
}

resource documents 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = {
  parent: blobService
  name: 'documents'
  properties: { publicAccess: 'None' }
}

resource audit 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = {
  parent: blobService
  name: 'audit'
  properties: { publicAccess: 'None' }
}
```
(Lesson 6 shows how to avoid repeating the container block.)
</details>

---

## Lesson 6 — Conditions and loops

### 6.1 Conditions: deploy only if…

```bicep
param deployFirewall bool = false

resource firewall 'Microsoft.Network/azureFirewalls@2024-01-01' = if (deployFirewall) {
  …
}
```

This is exactly how Sahl's **lean vs gov profile** works: the same file, with expensive resources behind `if (profile == 'gov')`.

```bicep
@allowed([ 'lean', 'gov' ])
param profile string = 'lean'

var isGov = profile == 'gov'

resource firewall '…' = if (isGov) { … }
```

### 6.2 Loops: one block, many resources

```bicep
param containerNames array = [
  'documents'
  'audit'
  'issued'
]

resource containers 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = [for name in containerNames: {
  parent: blobService
  name: name
  properties: { publicAccess: 'None' }
}]
```

Like PHP's `foreach ($containerNames as $name)`. Also available:
- With index: `[for (name, i) in containerNames: { … }]`
- Loop + condition: `[for name in containerNames: if (name != 'audit') { … }]`
- Loops in variables/outputs: `var upper = [for n in containerNames: toUpper(n)]`

### ✏ Exercise 6

Rewrite Lesson 5's containers with a loop over an array parameter. Then add a `profile` parameter (`lean`/`gov`) and make the `audit` container's storage SKU `Standard_ZRS` only when `profile == 'gov'` (hint: ternary on the storage account's SKU).

<details>
<summary>Solution (key parts)</summary>

```bicep
@allowed([ 'lean', 'gov' ])
param profile string = 'lean'

param containerNames array = [ 'documents', 'audit', 'issued' ]

resource sa 'Microsoft.Storage/storageAccounts@2023-05-01' = {
  …
  sku: {
    name: profile == 'gov' ? 'Standard_ZRS' : 'Standard_LRS'
  }
  …
}

resource containers 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = [for name in containerNames: {
  parent: blobService
  name: name
  properties: { publicAccess: 'None' }
}]
```
</details>

---

## Lesson 7 — `existing` resources

Sometimes a resource already exists (created elsewhere, or by another team) and you only need to **refer** to it — not create or change it.

```bicep
resource sharedLogs 'Microsoft.OperationalInsights/workspaces@2023-09-01' existing = {
  name: 'log-platform-shared'
  scope: resourceGroup('rg-platform-shared')   // optional: it lives in another resource group
}

resource appInsights 'Microsoft.Insights/components@2020-02-02' = {
  …
  properties: {
    WorkspaceResourceId: sharedLogs.id
  }
}
```

- `existing` → "look this up; don't touch it."
- Real-world use: application teams referencing the **platform team's** shared hub network, DNS zones, or Log Analytics workspace (Chapter 15, landing zones).

### ✏ Exercise 7

Write a file that references an existing Key Vault named `kv-sahl-dev-gwc` and outputs its URI. (It won't deploy without a real vault, but it compiles — that's the point.)

<details>
<summary>Solution</summary>

```bicep
resource kv 'Microsoft.KeyVault/vaults@2023-07-01' existing = {
  name: 'kv-sahl-dev-gwc'
}

output vaultUri string = kv.properties.vaultUri
```
</details>

---

## Lesson 8 — Modules

### 8.1 Why

One giant `main.bicep` with 60 resources is like a 3,000-line controller. **Modules** split infrastructure into reusable files — like Laravel service classes or Blade components.

### 8.2 How

`modules/storage.bicep` — a normal Bicep file with params and outputs:

```bicep
param name string
param location string
param containerNames array = []

resource sa 'Microsoft.Storage/storageAccounts@2023-05-01' = { … uses name, location … }
resource blobService '…/blobServices@2023-05-01' = { parent: sa, name: 'default' }
resource containers '…/containers@2023-05-01' = [for c in containerNames: { parent: blobService, name: c }]

output id string = sa.id
output name string = sa.name
```

`main.bicep` — calls the module:

```bicep
module storage './modules/storage.bicep' = {
  name: 'storage-deployment'          // name of this nested deployment (shows in deployment history)
  params: {
    name: names.storage
    location: location
    containerNames: [ 'documents', 'audit' ]
  }
}

// use a module's outputs:
output storageId string = storage.outputs.id
```

- A module's **params** are its inputs; its **outputs** are what callers can use.
- Dependencies between modules work the same way: if module B uses `moduleA.outputs.x`, A deploys first.

### 8.3 Public modules: Azure Verified Modules (AVM)

Microsoft publishes tested, best-practice modules in a public registry:

```bicep
module kv 'br/public:avm/res/key-vault/vault:<version>' = {
  name: 'kv'
  params: {
    name: names.keyVault
    // …
  }
}
```

`br/public:` = the public Bicep registry. AVM modules bundle good defaults (diagnostics, private endpoints, RBAC). **Learn to write your own first** (you need to understand what AVM does for you), then use AVM where it saves effort. ⚠ Look up the current version on the AVM website.

### ✏ Exercise 8

Create `lesson08/modules/storage.bicep` and `lesson08/main.bicep`. The module takes `name`, `location`, `containerNames`, `tags`; outputs `id` and `name`. `main.bicep` calls it **twice**: once for a `documents` account and once for an `audit` account (different names). Compile `main.bicep` — the module is compiled into it automatically.

---

## Lesson 9 — Scopes: resource group vs subscription

Every Bicep file deploys **at a scope**. The default is a **resource group** — your resources go into an existing RG.

But who creates the resource group itself? A file deployed at **subscription** scope:

```bicep
targetScope = 'subscription'

param location string = 'germanywestcentral'
param environment string = 'dev'

resource rg 'Microsoft.Resources/resourceGroups@2024-03-01' = {
  name: 'rg-sahl-${environment}-gwc'
  location: location
  tags: { workload: 'sahl', environment: environment }
}

// deploy a module INTO that new resource group:
module workload './workload.bicep' = {
  name: 'workload'
  scope: rg                       // ← change scope for the module
  params: {
    location: location
    environment: environment
  }
}
```

| `targetScope` | Typical contents | Deploy command (Lesson 11) |
|---|---|---|
| `resourceGroup` (default) | Workload resources | `az deployment group create` |
| `subscription` | Resource groups, budgets, policy assignments, role assignments | `az deployment sub create` |
| `managementGroup` | Policies across many subscriptions | `az deployment mg create` |
| `tenant` | Management groups | `az deployment tenant create` |

**Sahl's `main.bicep` will be subscription-scoped**: it creates the resource group(s), then calls modules into them.

### ✏ Exercise 9

Make a subscription-scoped `lesson09/main.bicep` that creates `rg-sahl-dev-gwc` and deploys your Lesson 8 storage module into it.

---

## Lesson 10 — Linting, secrets and good practice

### 10.1 The linter

The Bicep linter flags bad practices (unused params, hard-coded locations, secrets in outputs…). It runs in the editor and on build. Run it explicitly:

```bash
az bicep lint --file main.bicep
```

Configure it with a `bicepconfig.json` next to your files:

```json
{
  "analyzers": {
    "core": {
      "rules": {
        "no-unused-params": { "level": "error" },
        "no-hardcoded-location": { "level": "warning" },
        "outputs-should-not-contain-secrets": { "level": "error" }
      }
    }
  }
}
```

### 10.2 Secrets: the rules

1. **Never** put a secret literal in a Bicep file (it ends up in Git).
2. If you must pass one in, use `@secure()` params — they aren't stored in deployment history.
3. **Never output a secret** (outputs are visible in deployment history).
4. Best: **don't have secrets** — use **managed identities** + **RBAC role assignments** (Sahl's approach). Example of granting a managed identity access to Key Vault in Bicep:

```bicep
resource kvSecretsUser 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(kv.id, apiIdentityPrincipalId, 'kv-secrets-user')   // deterministic name
  scope: kv
  properties: {
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', '4633458b-17de-408a-b874-0445c86b69e6') // Key Vault Secrets User
    principalId: apiIdentityPrincipalId
    principalType: 'ServicePrincipal'
  }
}
```

(Role definition IDs are fixed GUIDs per built-in role — you look them up in the "Azure built-in roles" docs.)

### 10.3 Good-practice checklist

- [ ] Every resource has `location: location` (no hard-coded regions).
- [ ] Every resource has tags.
- [ ] Names follow the convention via a `names` variable.
- [ ] Security settings explicit (TLS 1.2, no public access, HTTPS only, RBAC on Key Vault).
- [ ] No secrets in files or outputs.
- [ ] Expensive resources behind the `profile` condition.
- [ ] Modules small and focused; `main.bicep` reads like a table of contents.
- [ ] Linter clean.
- [ ] Compiled `.json` files are **not committed** (add `*.json` under `infra/` to `.gitignore`, except `bicepconfig.json`).

---

## Lesson 11 — Deploying for real (Phase 2)

> **Do this only after activating the Azure account.** Read it now so you know what's coming.

### 11.1 Log in

```bash
az login                      # opens the browser
az account show               # which subscription am I using?
az account set --subscription "<name or id>"   # if you have several
```

### 11.2 Create a resource group (CLI, for practice)

```bash
az group create --name rg-sahl-learn-gwc --location germanywestcentral
```

### 11.3 Preview with what-if (always do this first)

```bash
az deployment group what-if \
  --resource-group rg-sahl-learn-gwc \
  --template-file lesson02-storage.bicep \
  --parameters environment=dev
```

Output symbols: `+` create, `~` modify, `-` delete, `=` no change, `*` ignore. Read it like a `git diff` for infrastructure. **Never deploy without reading what-if.**

### 11.4 Deploy

```bash
az deployment group create \
  --resource-group rg-sahl-learn-gwc \
  --template-file lesson02-storage.bicep \
  --parameters environment=dev
```

Or with a parameter file:

```bash
az deployment group create --resource-group rg-sahl-learn-gwc --parameters dev.bicepparam
```

Then:
1. Open the portal → resource group → see your storage account.
2. Resource group → **Deployments**: see the deployment history, inputs, outputs.
3. Run the **same command again** → nothing changes. That's idempotency.
4. Add a tag in the Bicep file → what-if shows `~` modify → deploy → only the tag changes.

### 11.5 Deployment modes

- **Incremental** (default): creates/updates what's in the file; **leaves other resources in the RG alone**.
- **Complete**: deletes anything in the RG that isn't in the file. Powerful, dangerous; rarely used directly.
- **Deployment stacks** (newer): manage a set of resources as a unit, can clean up removed resources and block manual changes ⚠ Verify current guidance.

### 11.6 Clean up (always)

```bash
az group delete --name rg-sahl-learn-gwc --yes --no-wait
```

Deleting the resource group deletes everything in it — the cheapest habit you'll ever learn.

### 11.7 When deployments fail

Read the error fully. Common ones:
| Error contains | Meaning |
|---|---|
| `StorageAccountAlreadyTaken` | Name not globally unique → use `uniqueString` |
| `QuotaExceeded` | Subscription limit (common on free accounts) |
| `SkuNotAvailable` / `LocationNotAvailableForResourceType` | That SKU/service isn't offered in this region |
| `MissingSubscriptionRegistration` | Resource provider not registered → `az provider register --namespace Microsoft.X` |
| `AuthorizationFailed` | Your identity lacks the RBAC role at that scope |
| `InvalidTemplate` | Bicep/ARM mistake — usually caught earlier by build/lint |

---

## Lesson 12 — How Sahl's `/infra` will be organized

You'll write this yourself in Phase 1 using everything above.

```
infra/
├── bicepconfig.json              # linter rules
├── main.bicep                    # targetScope = 'subscription': creates RGs, calls modules
├── env/
│   ├── dev.bicepparam            # profile = 'lean', cheap SKUs
│   └── prod.bicepparam           # profile = 'gov' (burst), production SKUs
└── modules/
    ├── monitoring.bicep          # Log Analytics + App Insights
    ├── network.bicep             # VNet, subnets, NSGs, private DNS zones
    ├── keyvault.bicep            # Key Vault + private endpoint + RBAC
    ├── storage.bicep             # documents / audit (immutability) / issued
    ├── postgres.bicep            # Flexible Server, private access, Entra auth
    ├── messaging.bicep           # Service Bus or Event Grid (open decision O2)
    ├── compute.bicep             # Container Apps or App Service (open decision O1)
    ├── apim.bicep                # API Management (system-to-system APIs)
    ├── frontdoor.bicep           # Front Door + WAF
    ├── ai.bicep                  # AI Search + OpenAI
    └── gov/                      # gov-profile-only pieces
        ├── hub-network.bicep     # hub VNet, peering
        ├── firewall.bicep
        └── bastion.bicep
```

Each module gets built in the step where we learn that service, so you always know what you're writing and why.

---

## Quick reference card

```bicep
targetScope = 'subscription'                         // optional; default is resourceGroup

@description('…')  @allowed([…])  @secure()         // decorators
param name string = 'default'                        // input

var x = 'computed-${name}'                           // variable

resource sym 'Provider/type@api-version' = {          // create
  name: x
  location: location
  properties: { … }
}

resource ex 'Provider/type@api-version' existing = { name: 'already-there' }   // reference only

resource cond 'Provider/type@api' = if (isGov) { … }                          // conditional
resource many 'Provider/type@api' = [for n in names: { name: n }]             // loop

module m './modules/thing.bicep' = { name: 'm', scope: rg, params: { … } }    // module

output out string = sym.properties.something         // result
```

| Command | Purpose |
|---|---|
| `az bicep build --file x.bicep` | Compile to ARM JSON (local) |
| `az bicep build-params --file x.bicepparam` | Check a parameter file (local) |
| `az bicep lint --file x.bicep` | Run linter (local) |
| `az bicep decompile --file x.json` | Convert ARM JSON → Bicep (useful with portal "export template") |
| `az deployment group what-if …` | Preview changes (Azure) |
| `az deployment group create …` | Deploy to a resource group (Azure) |
| `az deployment sub create --location … …` | Deploy at subscription scope (Azure) |
| `az group delete --name … --yes` | Delete everything in a resource group (Azure) |
