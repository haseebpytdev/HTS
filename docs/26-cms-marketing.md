# CMS & Marketing (Phase 16.1-16.4)

## Goal

Add a dedicated and scalable CMS/marketing structure for landing pages, blog, promo codes, and referral management.

## 16.1 Landing pages

- Table/model: `landing_pages`, `App\Models\LandingPage`
- Admin CRUD (create/edit/list):
  - `App\Http\Controllers\Admin\LandingPageController`
  - `resources/views/admin/landing-pages/*`
- Frontend public page route:
  - `GET /landing/{slug}` -> `Frontend\LandingPageController@show`

## 16.2 Blog

- Table/model: `blog_posts`, `App\Models\BlogPost`
- Admin CRUD (create/edit/list):
  - `App\Http\Controllers\Admin\BlogPostController`
  - `resources/views/admin/blog-posts/*`
- Frontend routes:
  - `GET /blog`
  - `GET /blog/{slug}`

## 16.3 Promo codes

- Table/model: `promo_codes`, `App\Models\PromoCode`
- Admin CRUD (create/edit/list):
  - `App\Http\Controllers\Admin\PromoCodeController`
  - `resources/views/admin/promo-codes/*`
- Service:
  - `App\Services\Marketing\PromoCodeService`
  - validates active window/usage and computes discount
- Public validation endpoint:
  - `POST /promotions/validate`

### Wired into financial flow

- Promo input (`promo_code`) is now accepted in quotation payload rules.
- Quotation totals now apply:
  - manual discount (`discount_amount`)
  - validated promo discount (computed by `PromoCodeService`)
- Stored on quotation:
  - `promo_code_id`
  - `promo_code`
  - `promo_discount_amount`
- Booking draft now inherits promo/discount fields from quotation so payment balance uses discounted `total_amount`.

## 16.4 Referral system

- Table/model: `referrals`, `App\Models\Referral`
- Service:
  - `App\Services\Marketing\ReferralService`
  - create referral codes, record conversions
- Admin screen:
  - `App\Http\Controllers\Admin\ReferralController@index/store`
  - `resources/views/admin/referrals/index.blade.php`
- Public conversion endpoint:
  - `POST /referrals/convert`

## Permissions and routing

- New permission keys:
  - `module.marketing.view`
  - `action.marketing.manage`
- Admin marketing routes added under `routes/admin.php` with permission middleware.
- Dashboard now links to landing pages, blog, promo codes, and referrals.
