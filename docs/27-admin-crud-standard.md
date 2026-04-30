# Admin CRUD Standard (Master-Data Modules)

## Goal

Provide one consistent implementation pattern for all remaining admin master-data modules so future scaffolding stays uniform across controllers, requests, routes, views, and tests.

This standard applies to:

- Hotels
- Room Types
- Hotel Rates
- Visa Types
- Visa Rates
- Transport Types
- Transport Rates
- Flight Entries
- Packages
- Groups
- Settings (where CRUD is appropriate)

---

## 1) Required file structure per module

For every module, create exactly this shape:

- Controller
  - `app/Http/Controllers/Admin/<ModuleController>.php`
- Form Requests
  - `app/Http/Requests/Admin/Store<Module>Request.php`
  - `app/Http/Requests/Admin/Update<Module>Request.php`
  - `app/Http/Requests/Admin/Filter<Module>Request.php`
- Views
  - `resources/views/admin/<module-kebab>/index.blade.php`
  - `resources/views/admin/<module-kebab>/create.blade.php`
  - `resources/views/admin/<module-kebab>/edit.blade.php`
  - `resources/views/admin/<module-kebab>/show.blade.php` (optional; use when detail page adds value)
  - `resources/views/admin/<module-kebab>/_form.blade.php` (shared create/edit form partial)
- Routes
  - One route block in `routes/admin.php`
- Tests
  - `tests/Feature/Admin/<Module>CrudTest.php`

---

## 2) Naming convention (mandatory)

Use these exact naming rules:

- Controller: `<Module>Controller`
  - Example: `HotelController`
- Requests:
  - `Store<Module>Request`
  - `Update<Module>Request`
  - `Filter<Module>Request`
- Route names:
  - `admin.<module-plural>.index`
  - `admin.<module-plural>.create`
  - `admin.<module-plural>.store`
  - `admin.<module-plural>.show` (if used)
  - `admin.<module-plural>.edit`
  - `admin.<module-plural>.update`
  - `admin.<module-plural>.destroy`
- Blade folder:
  - `resources/views/admin/<module-kebab>/`

Notes:

- Keep model names singular in PascalCase (`HotelRate`), route segment plural kebab/snake style (`hotel-rates`), and route name plural dotted style (`hotel-rates`).
- Do not introduce alternate aliases for the same module.

---

## 3) Controller method contract

Every admin CRUD controller must use this baseline method set:

- `index(Filter<Module>Request $request): View`
- `create(): View`
- `store(Store<Module>Request $request): RedirectResponse`
- `show(<Model> $<model>): View` (optional)
- `edit(<Model> $<model>): View`
- `update(Update<Module>Request $request, <Model> $<model>): RedirectResponse`
- `destroy(<Model> $<model>): RedirectResponse`

Controller responsibilities only:

- Orchestrate request/response flow
- Call services/actions/repositories when business logic is non-trivial
- Return views/redirects with flash messages

Do not put business/domain logic in Blade templates.

---

## 4) Route block standard (`routes/admin.php`)

Preferred pattern:

- Define routes inside existing admin middleware group.
- Use one `Route::resource()` block per module whenever standard CRUD fits.
- Add explicit `->middleware('permission:...')` per route or grouped block if permission matrix requires it.

Example shape:

```php
Route::resource('hotels', HotelController::class);
```

If permissions vary by action, expand resource into explicit routes while preserving naming convention.

---

## 5) Index page standard

`index.blade.php` must include:

- Page title + primary "Create" action
- Filter form (GET) using `Filter<Module>Request`
- Reset action back to index route
- Responsive table/list for key columns
- Row actions: `View` (if available), `Edit`, `Delete`
- Laravel pagination links

Index query behavior:

- Build from validated filter payload only
- Use `->withQueryString()` on pagination
- Use default ordering (`latest('id')` or domain-specific stable order)

---

## 6) Create/Edit form standard

Use one shared `_form.blade.php` partial with:

- `@csrf`
- `@method('PUT')` on edit
- `old()` fallback handling
- Inline validation feedback (`@error`) and summary block for errors
- Bootstrap form controls and spacing

Create page:

- Includes `_form` with create action

Edit page:

- Includes `_form` with update action
- Never display sensitive stored values in plaintext (secrets remain write-only)

---

## 7) Validation standard (Form Requests)

Use dedicated Form Requests only (no inline controller validation):

- `Store<Module>Request`: full create rules
- `Update<Module>Request`: update rules (usually same + uniqueness ignore)
- `Filter<Module>Request`: safe optional filters for index

Rules guidance:

- Strong typing (`integer`, `numeric`, `boolean`, `array`, `date`, etc.)
- Enumerations with `Rule::in(...)` when applicable
- Existence checks (`exists:<table>,id`) for relations
- Max lengths for text fields

---

## 8) Flash messages and redirects

Use consistent flash keys:

- Success: `with('success', '...')`
- Error (rare expected flows): `with('error', '...')`

Redirect pattern:

- After create: redirect to `edit` or `index` consistently per module
- After update: redirect to `edit` or `index` consistently per module
- After delete: redirect to `index`

For master-data modules, preferred default:

- Create -> `edit`
- Update -> `edit`
- Delete -> `index`

---

## 9) Feature test standard

Each module must have `tests/Feature/Admin/<Module>CrudTest.php` covering:

- Admin can open index
- Admin can open create
- Admin can store valid payload
- Validation rejects invalid payload
- Admin can open edit
- Admin can update record
- Admin can delete record
- Filter endpoint returns expected subset (minimum one filter assertion)

Use `RefreshDatabase` and route names (not hardcoded URLs).

---

## 10) Consistency checklist (pre-merge)

Before merging a new module:

- Controller + 3 Form Requests exist with correct names
- Route block follows naming standard
- Blade folder contains `index/create/edit/_form` (+ `show` if used)
- Pagination + filter query string persistence implemented
- Validation errors render properly in UI
- Feature CRUD test exists and passes
- No duplicate flow introduced under alternate path names

---

## 11) Module mapping examples

- Hotels
  - Controller: `HotelController`
  - Requests: `StoreHotelRequest`, `UpdateHotelRequest`, `FilterHotelRequest`
  - Views: `resources/views/admin/hotels/*`
  - Route name prefix: `admin.hotels.*`

- Hotel Rates
  - Controller: `HotelRateController`
  - Requests: `StoreHotelRateRequest`, `UpdateHotelRateRequest`, `FilterHotelRateRequest`
  - Views: `resources/views/admin/hotel-rates/*`
  - Route name prefix: `admin.hotel-rates.*`

- Flight Entries
  - Controller: `FlightEntryController`
  - Requests: `StoreFlightEntryRequest`, `UpdateFlightEntryRequest`, `FilterFlightEntryRequest`
  - Views: `resources/views/admin/flight-entries/*`
  - Route name prefix: `admin.flight-entries.*`

---

## 12) Out-of-scope reminder

This document defines standards only. It does not generate module code by itself.
