# SignageFlow Agent Guide

This file is the primary repository guide for coding agents working on SignageFlow. Read it before changing code. It describes the application as it currently exists; the default `README.md` is still mostly Laravel boilerplate, and `GEMINI.md` contains older/incomplete guidance.

## 1. Project Summary

SignageFlow is an internal business application for a signage company. It combines:

- consumable and product master data;
- purchases, opening stock, outward issues, stock ownership, and stock thresholds;
- product internal-name grouping and weighted/average pricing;
- open-stock balances and manual adjustments;
- signage, cabinet, and letter cost sheets;
- enquiries that can be converted into sales orders;
- clients, suppliers, expenses, users, roles, permissions, activity logs, and sign-in logs;
- PDF printing, spreadsheet imports/exports, barcode/QR workflows, and enquiry attachments.

The application is primarily a server-driven Laravel application with Vue pages rendered through Inertia. It is not organized as a separate REST API plus SPA.

## 2. Technology Stack

### Backend

- PHP `^8.2`
- Laravel `^12`
- Inertia Laravel `^2`
- MySQL
- Spatie Laravel Permission
- Spatie Query Builder
- a repository-local fork of `protonemedia/inertiajs-tables-laravel-query-builder`
- PhpSpreadsheet for imports/exports
- DomPDF for printable documents
- Laravel Sanctum is installed, but the application mainly uses web/session authentication

### Frontend

- Vue 3 using `<script setup>`
- Inertia Vue 3
- Pinia
- Vite 6
- Tailwind CSS
- Ziggy route helpers
- Chart.js, vue-multiselect, Vue Datepicker, barcode/QR libraries

### Tooling and deployment

- Composer for PHP dependencies
- pnpm is the canonical JavaScript package manager because CI uses `pnpm install --frozen-lockfile`
- Laravel Pint is available for PHP formatting
- GitHub Actions builds frontend assets and deploys `main` and `staging`

There are also `package-lock.json` and `yarn.lock` files, but do not update multiple JavaScript lockfiles for routine work. Prefer `pnpm-lock.yaml` unless the task explicitly changes the package-manager policy.

## 3. Repository Map

| Path | Purpose |
| --- | --- |
| `app/Http/Controllers/Admin` | Main application controllers and business workflows |
| `app/Models` | Eloquent models, relationships, query scopes, and form/table metadata |
| `app/Services` | Cross-controller domain logic such as open stock and average pricing |
| `app/Traits` | Shared model behavior, especially financial-year filtering |
| `app/Helpers` | Global helper classes, including activity logging |
| `app/Http/Middleware` | Authentication, 2FA, Inertia sharing, and financial-year session setup |
| `routes/web.php` | Almost all application routes |
| `routes/auth.php` | Login, password reset, logout, and 2FA routes |
| `routes/api.php` | Minimal Sanctum user endpoint; not the main application interface |
| `resources/js/Pages/Admin` | Inertia page components |
| `resources/js/components` | Shared UI, generic forms, tables, menus, modals, barcode tools |
| `resources/js/layouts` | Authenticated and guest layouts |
| `resources/js/menuAside.js` | Main application navigation and resource permission keys |
| `resources/js/utils/permissions.js` | Client-side permission checks using `window.permissions` |
| `resources/views/Admin` | PDF/print-oriented Blade templates |
| `database/migrations` | Schema history; never delete or rewrite applied migrations |
| `database/seeders` | Permissions, settings, and data backfills |
| `database/factories` | Model factories; several are inherited from older project areas |
| `packages/protonemedia/...` | Local Composer path dependency used by Inertia tables |
| `specdata` | CSV samples/source data; treat as data fixtures, not application code |
| `.github/workflows` | production and staging deployment workflows |

## 4. Request and Rendering Flow

A normal admin page follows this path:

1. A named route in `routes/web.php` points to an admin controller.
2. The route is inside the `/admin` group protected by `auth` and `2fa`.
3. The controller applies action-specific `can:<permission>` middleware.
4. The controller builds a query, usually with Spatie Query Builder.
5. The controller returns `Inertia::render('Admin/PageName', props)`.
6. `resources/js/app.js` resolves the Vue page under `resources/js/Pages`.
7. Vue uses Ziggy's global `route()` helper for named Laravel routes.
8. Shared flash data and the selected financial year come from `HandleInertiaRequests`.

Use named routes rather than hard-coded URLs whenever possible.

## 5. Core Architectural Pattern: Metadata-Driven CRUD

Many master-data modules share generic list and edit pages:

- `Admin/IndexView.vue`
- `Admin/AddEditView.vue`
- `Admin/MultiAddEditView.vue`
- `Admin/ImportView.vue`
- shared data-table and form components

Their controllers/models provide configuration rather than dedicated markup for every resource.

### `resourceNeo`

Controllers commonly maintain a `$resourceNeo` array containing:

- `resourceName`: route and permission prefix;
- `resourceTitle`;
- `iconPath`;
- CRUD `actions`;
- `formInfo` and sometimes `formInfoMulti`;
- bulk actions, extra links, modal configuration, totals, and layout flags.

Keep `resourceName`, route names, menu `resource` values, and permission prefixes aligned. A mismatch can hide actions in Vue even when the backend route works.

### `formInfo()` and `formInfoMulti()`

Models frequently define field metadata with keys such as:

- `label`
- `type`
- `options`
- `optionType`
- `vRule`
- `searchable`
- `sortable`
- `readonly`
- `default`
- `align`
- `showTotal`
- `autoFill`
- `filter`
- `canAdd` or `addAndRefresh`

For metadata-driven resources, adding a database field usually requires coordinated changes in:

1. migration;
2. model `$fillable`;
3. model metadata;
4. controller validation and save/update/import logic;
5. query select/filter/sort configuration;
6. seed permissions if new actions are introduced;
7. Vue only when the generic components cannot express the behavior.

Do not create a new dedicated Vue page before checking whether the generic metadata system already supports the requirement.

## 6. Main Business Domains

### 6.1 Master data

Important master models include:

- `Munit`: measurement units;
- `Pgroup`: product groups/subgroups;
- `Location`;
- `Expuser` and `Expcate`;
- `Supplier` and `Client`;
- `Product`;
- `ConsumableInternalName`;
- `ConsumableInternalNameGroup`.

`Product` represents invoice-facing products and maps them to an internal consumable name. Internal names are important string identifiers used across purchases, outwards, stock calculations, open stock, and cost sheets.

Avoid casually renaming internal-name strings. They are referenced by value in several tables rather than exclusively by foreign keys. A rename may require a coordinated data migration.

### 6.2 Purchases and opening stock

Both purchases and opening entries are stored in the `purchases` table:

- `entry_type = 0`: normal purchase;
- `entry_type = 1`: opening stock.

`PurchaseInfo` stores invoice/header information for normal purchases, while `Purchase` stores line items.

Purchasing/opening logic also updates the internal-name average unit price through `AverageUnitPriceService`. When changing quantity, internal quantity, rate, update, delete, or import behavior, verify that average-price recalculation remains correct.

Unit fields are intentionally duplicated:

- billed quantity/unit;
- optional billed alternative quantity/unit;
- internal quantity/unit;
- optional internal alternative quantity/unit;
- conversion rate;
- billed and internal rates.

Do not simplify these fields without understanding stock and pricing reports.

### 6.3 Outwards and stock

`Outward` records material issued from stock. Stock reports are calculated from aggregated purchase/opening quantities minus outward quantities; they are not represented by one simple stock table.

Stock access may be limited by the logged-in user's permissions, incharge name, or location. Preserve permission-sensitive query branches in `OutwardController`, `OpeningController`, and `StocksController`.

Creating an outward can also create an open-stock transaction through `OpenStockService`. Use a database transaction when a change affects both ordinary stock data and open-stock balances.

### 6.4 Open stock

Open stock is maintained separately in:

- `open_stock_balances`: current balance by internal name, location, and incharge;
- `open_stock_transactions`: immutable transaction history;
- `OpenStockService`: balance locking, validation, adjustments, consumption, and reversal.

Important invariants:

- balance uniqueness is `(internal_name, location, incharge)`;
- quantities must be positive for recorded movements;
- minus adjustments cannot exceed available quantity;
- the configured open-stock unit must match the balance unit;
- updates should lock the balance row and run inside a DB transaction;
- transaction history should accompany every balance mutation.

Do not update `open_stock_balances.qty` directly from a controller when the service can perform the operation.

### 6.5 Cost sheets

`CostSheet` supports three product types:

- `signage`
- `cabinet`
- `letters`

The three controllers extend/use the shared `BaseCostSheetController` behavior. Cost sheets can be imported, edited, and composed from raw-material groups or child cost sheets.

`CostSheetComposition` is recursive: a composition can refer to another cost sheet. Cost calculation depends on:

- quantity;
- composition margin;
- raw-material/internal-name pricing;
- internal-name open-stock margin;
- child cost sheet total cost and unit count;
- fallback cost-sheet rate.

When changing composition schema or pricing logic, inspect the latest migrations, `CostSheet`, `CostSheetCompositionController`, `BaseCostSheetController`, import logic, and the composition Vue modal together. This area has recent schema evolution from individual internal names toward internal-name groups.

### 6.6 Enquiries

An enquiry contains:

- a financial-year-based enquiry number;
- client and product type;
- cost-sheet-backed line items;
- custom informational items;
- GST and transport calculations;
- file attachments stored on the local disk;
- status (`open` or `pushed_to_sales`).

Enquiry creation/update is transactional. Number generation has duplicate-key retry handling. File metadata is stored in `enquiry_files`; physical files are stored under `storage/app/enquiry_files/{enquiry_id}`. New-enquiry uploads first use `storage/app/temp_enquiry_files`.

When deleting enquiry files or enquiries, delete both database metadata and physical files.

### 6.7 Sales orders

Sales orders are built from cost sheets and may be created from an enquiry. They include:

- financial-year and product-type based order numbering;
- line quantity calculations;
- GST per line;
- transport charge and transport GST;
- optional round-off;
- PDF output through `resources/views/Admin/sales-order-invoice.blade.php`.

For square-area units, line quantity may derive from length, width, and pieces rather than only a directly entered quantity. Preserve the normalization and server-side recalculation in `SalesOrderController`; never trust totals calculated only in Vue.

Creating a sales order from an enquiry marks the enquiry as pushed. Keep these state changes in the same transaction.

### 6.8 Expenses and administration

Expense modules use categories, users, deposits/expenses, and dashboard summaries. Administration includes:

- users;
- Spatie roles and permissions;
- settings;
- selected financial year;
- sign-in logs;
- activity logs;
- email-based 2FA.

Mutating controller actions commonly call `\ActivityLog::add(...)`. New write operations should follow the adjacent module's logging convention.

## 7. Financial-Year Behavior

The business financial year runs from April 1 through March 31.

`FinancialYearMiddleware` initializes these session values:

- `financial_year`
- `financial_year_start`
- `financial_year_end`

`SettingController::changeFinancialYear()` changes them. Models using `FinancialYearScope` expose `->inFinancialYear()`, with a model-specific date column through `DATE_COLUMN`.

Important: financial-year filtering is not consistently enabled everywhere; several controllers intentionally have commented-out scopes. Do not globally enable or remove scopes as cleanup. Decide per report/workflow and verify expected historical behavior.

Number generation for enquiries and sales orders also depends on the date's financial year. Preserve unique `(prefix, financial year, sequence)` behavior and duplicate-key retry logic.

## 8. Authentication and Permissions

Admin routes require session authentication and 2FA.

Authorization conventions:

- controller middleware: `can:<resource>_<action>`;
- common actions: `list`, `create`, `edit`, `delete`, `export`, `import`;
- some modules have special permissions such as `stocks_transfer`, `openStock_adjust`, or list-for-all variants;
- the role named by `APP_SUPER_ADMIN` (default `super-admin`) bypasses gates in `AuthServiceProvider`;
- Vue checks permissions through `can()` in `resources/js/utils/permissions.js`;
- menu items use a `resource` prefix from `resources/js/menuAside.js`.

When adding a protected feature:

1. add/seed the permission in `UnifiedPermissionSeeder` or the relevant seeder;
2. protect the backend action;
3. expose/hide the frontend action using the same permission key;
4. update menu configuration if it is a new module;
5. verify behavior for a regular role and super admin.

Frontend permission checks are only presentation. Backend middleware/gates are mandatory.

## 9. Query, Table, and Export Conventions

List pages commonly use:

- Spatie `QueryBuilder`;
- explicit `allowedSorts`;
- explicit `allowedFilters`;
- callback global search;
- `InertiaTable` column/filter metadata;
- `paginate($perPage)->withQueryString()`.

When adding a visible/filterable column:

- select or join the required value;
- add an allowed sort, using `AllowedSort::field` for aliased/joined columns;
- add an allowed filter;
- add table metadata;
- include alignment/type/total metadata where relevant;
- ensure export uses the same meaning and does not expose hidden data.

Do not pass arbitrary request columns into `orderBy`, filters, or raw SQL.

## 10. Frontend Conventions

- Use Vue 3 Composition API and `<script setup>`.
- Use Inertia `router`, `useForm`, `Link`, and `Head`.
- Use global Ziggy `route()` names instead of string URLs.
- Use `@/` imports for `resources/js`.
- The actual shared directories are lowercase: `components`, `layouts`, `stores`, and `utils`. Preserve exact casing for Linux deployment.
- Reuse `LayoutAuthenticated`, shared form controls, `CardBox`, data-table components, notifications, and modals.
- Page-level server validation errors should flow through Inertia `useForm`.
- Keep authoritative calculations and permission checks on the server even when Vue mirrors them for responsiveness.
- New navigation entries belong in `resources/js/menuAside.js`.

The frontend intentionally has HMR disabled in `vite.config.js`. `pnpm dev` still starts Vite, but full reload behavior may be needed.

## 11. Database and Migration Rules

- Never delete existing migrations.
- Never edit an already-deployed migration to change production schema; add a new migration.
- Never run `migrate:fresh`, `migrate:refresh`, destructive seeders, or broad truncation unless the user explicitly authorizes data loss and the target database is confirmed disposable.
- Add foreign keys and indexes when they match existing data realities.
- For string-linked business keys such as internal names, inspect existing rows before converting to foreign keys.
- Wrap multi-table writes and inventory/accounting changes in `DB::transaction()` or explicit begin/commit/rollback handling.
- Use row locks for concurrent balance/sequence-sensitive work.
- Preserve decimal precision and explicit rounding rules for quantities, rates, GST, and totals.
- Validate imports row by row and report useful failures without partially corrupting business data.

## 12. Local Setup

Typical setup:

```bash
composer install
pnpm install --frozen-lockfile
cp .env.example .env
php artisan key:generate
php artisan migrate
pnpm dev
php artisan serve
```

The project expects MySQL. Configure a local database and mail settings in `.env`.

Useful inspection commands:

```bash
php artisan about
php artisan route:list
php artisan migrate:status
php artisan tinker
```

## 13. Validation and Testing

Start with the narrowest relevant checks:

```bash
vendor/bin/pint --test
php artisan test --filter=RelevantTestName
pnpm build
```

For PHP syntax-only validation:

```bash
php -l path/to/changed-file.php
```

### Current test-suite warning

The only committed feature test currently references models/routes from an older production module that are not present in this repository. It is stale and should not be treated as a valid regression suite.

`phpunit.xml` is configured for a named MySQL database and the stale test uses `RefreshDatabase`. Do not run the full suite until you have confirmed that the configured database is an isolated disposable test database. Never point tests using `RefreshDatabase` at development or production data.

For changes without reliable automated coverage:

- add focused tests when the surrounding module has a viable test pattern;
- otherwise validate PHP syntax, build assets, inspect routes, and manually exercise the affected workflow;
- explicitly report validation that could not safely be run.

## 14. Formatting and Code Style

- Follow existing Laravel/PHP style and PSR-12.
- Use Laravel Pint for touched PHP files when practical.
- Keep controller/model naming consistent with existing resource names, even where legacy naming is unconventional.
- Prefer focused service methods for shared or transaction-heavy business logic.
- Avoid unrelated refactors while fixing a module.
- Preserve existing user changes in a dirty worktree.
- Do not add comments that merely restate code; document business invariants and non-obvious calculations.

There is no configured JavaScript lint/test script in `package.json`. Use `pnpm build` as the baseline frontend validation.

## 15. Deployment Notes

Production deployment:

- branch: `main`;
- workflow: `.github/workflows/deploy.yml`;
- frontend built with Node 22 and pnpm 10;
- built `public/build` assets are rsynced;
- server hard-resets to `origin/main`, installs production Composer dependencies, runs migrations, rebuilds caches, and restarts PHP 8.3 FPM.

Staging follows the same process from the `staging` branch.

Deployment scripts and workflows use destructive Git reset on the server. Do not run `deploy.sh`, `deploy_jtsflow.sh`, or deployment workflow commands locally as a validation step.

Because production is Linux:

- file and import casing must be exact;
- route caching must remain supported;
- code must not depend on local untracked files;
- generated frontend assets are not committed under `public/build`.

## 16. Local Package

Composer resolves `protonemedia/inertiajs-tables-laravel-query-builder` from:

`packages/protonemedia/inertiajs-tables-laravel-query-builder`

Changes to generic table behavior may require editing this package, but do so only when the behavior cannot be implemented in the application layer. Treat it as maintained source code, not as `vendor/`.

## 17. Known Caveats

- `README.md` is Laravel boilerplate and does not describe SignageFlow.
- `GEMINI.md` incorrectly suggests `php artisan tinker` as the test command and calls the stack “TALL” even though Livewire and Alpine are not the application architecture.
- The automated test suite is currently stale and potentially destructive if pointed at a non-test MySQL database.
- Financial-year scopes are applied inconsistently by design/history; inspect each module before changing them.
- Several business relationships use names/strings rather than foreign keys.
- Cost-sheet composition schema has recent migrations and should be checked end-to-end before modification.
- Some old/unused Vue pages, components, factories, and example files remain. Confirm references before deleting anything.
- The repository may contain active user changes. Always inspect `git status` and avoid overwriting unrelated modifications.

## 18. Agent Workflow

Before implementing:

1. inspect `git status`;
2. identify the route, controller, model, migration, page, permission, and menu entries for the feature;
3. trace all writes and derived totals;
4. inspect recent migrations rather than assuming the original create migration is the final schema;
5. check whether a generic CRUD component already supports the requirement.

While implementing:

1. make the smallest coherent change;
2. enforce validation and permissions on the backend;
3. use transactions for related writes;
4. preserve stock, pricing, numbering, and financial-year invariants;
5. update imports, exports, metadata, logging, and permissions when the field/action affects them.

Before handing off:

1. review the diff for accidental changes;
2. run safe targeted validation;
3. verify route names and frontend imports;
4. confirm no destructive database command was used;
5. report any stale tests or environment-dependent checks that prevented full validation.
