# CMS-lite, SEO, and Media (Phase 14)

## Goal

Allow operational teams to manage public SEO metadata, homepage banners and content blocks, and package/group image galleries without code changes.

## Database

- **`seo_pages`** (existing): one row per logical page key (`page_key`). Used for meta description, keywords, OG image, canonical URL, optional JSON-LD, indexability.
- **`content_blocks`** (new): keyed blocks (`block_key`) with JSON `payload` for homepage sections (hero, why us, group row, deals row).

## Configuration

- `config/seo.php` maps **route names** to `seo_pages.page_key` for automatic meta injection on public routes.
- Dynamic detail pages (package/group show) merge the subject title with the template row `package_detail` / `group_detail`.

## Admin routes (prefix `admin/`)

| Area | Route name prefix | Purpose |
|------|-------------------|---------|
| SEO | `admin.seo-pages.*` | CRUD SEO rows |
| Homepage | `admin.content-blocks.*` | Edit structured homepage blocks |
| Catalog | `admin.cms.packages.index`, `admin.cms.groups.index` | Jump-off lists to galleries |
| Galleries | `admin.packages.gallery.*`, `admin.groups.gallery.*` | Upload, sort, cover, delete images |

## Frontend

- Middleware `frontend.seo` (`ShareFrontendSeo`) shares `sharedSeo` and resets `pageSeo` before each request.
- `layouts/frontend.blade.php` pushes `<x-seo.meta>` using `$pageSeo ?? $sharedSeo`.
- `App\Support\Media::url()` resolves storage paths on the `public` disk and passes through absolute URLs.

## Deployment notes

1. Run migrations (including `content_blocks`).
2. `php artisan storage:link` so `/storage/...` URLs work for uploads.
3. Seed defaults: `php artisan db:seed --class=CmsSeeder` (requires `seo_pages` and related tables to exist).
4. If MySQL is missing `seo_pages`, run the core frontend migration that creates it before seeding.

## Files (reference)

- `app/Http/Middleware/ShareFrontendSeo.php`
- `app/Http/Controllers/Admin/SeoPageController.php`
- `app/Http/Controllers/Admin/ContentBlockController.php`
- `app/Http/Controllers/Admin/CmsCatalogController.php`
- `app/Http/Controllers/Admin/PackageGalleryController.php`
- `app/Http/Controllers/Admin/GroupGalleryController.php`
- `app/Actions/Admin/StorePublicDiskImageAction.php`
- `database/seeders/CmsSeeder.php`
