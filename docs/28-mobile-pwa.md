# Mobile / PWA (Phase 18.1-18.3)

## Goal

Improve mobile usability and add Progressive Web App capabilities for installability and basic offline resilience.

## 18.1 Responsive UI

- Enhanced responsive CSS in `public/assets/css/frontend.css`:
  - tighter mobile spacing/hero sizing
  - improved nav/dropdown behavior on smaller screens
  - mobile typography/card/table adjustments
- Added persistent bottom mobile quick-action bar for frontend shell:
  - implemented in `resources/views/layouts/frontend-public.blade.php`
  - actions: Home, Packages, Quote, Contact

## 18.2 PWA setup

- Added web manifest:
  - `public/manifest.webmanifest`
- Added service worker:
  - `public/sw.js`
  - caches core shell assets and fallback offline page
- Added offline fallback page:
  - `public/offline.html`
- Added app icons:
  - `public/assets/pwa/icon-192.svg`
  - `public/assets/pwa/icon-512.svg`
- Wired PWA metadata + SW registration in frontend layout:
  - `<link rel="manifest">`
  - `theme-color`
  - service worker registration script

## 18.3 Mobile flows

- Quote inquiry flow improved for mobile:
  - added sticky mobile submit button tied to form on `frontend/inquiries/quote.blade.php`
- Home page install flow:
  - added PWA install CTA using `beforeinstallprompt` handling in `frontend/home.blade.php`

## Notes

- Current offline mode is shell-level and best-effort cache-first fallback.
- You can extend this with route-specific caching strategies and background sync later.
