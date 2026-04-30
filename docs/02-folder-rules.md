# Folder Rules

## Routing

- Keep `routes/web.php` as a loader for module route files.
- Use dedicated files: `frontend.php`, `auth.php`, `admin.php`, `agency.php`, `api.php`.

## Views

- Keep reusable UI inside `resources/views/components`.
- Keep base layouts inside `resources/views/layouts`.

## Controllers

- Use module namespaces under `app/Http/Controllers`.
- Avoid mixed module responsibilities in one controller.
