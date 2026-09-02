# HTS

HTS is a Laravel application for multi-tenant travel operations. It brings agency administration, quotations, bookings, traveler records, payments, documents, support workflows, and external integrations into one operational system.

## Engineering focus

- Tenant-aware access controls and agency boundaries
- Quotation-to-booking workflows
- Traveler and booking lifecycle management
- Payment and document handling
- Support and external-integration workflows
- Regression and smoke coverage across authentication, agency access, quotations, bookings, tenancy, payments, documents, support, and integrations

## Stack

- PHP
- Laravel
- Blade / Vite
- Relational database

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Configure the required database and integration settings in `.env` before running environment-dependent workflows.

## Testing

```bash
php artisan test
```

The test suite includes targeted coverage for authentication, tenancy boundaries, agency workflows, quotations, booking flows, payments, documents, support, and integrations.
