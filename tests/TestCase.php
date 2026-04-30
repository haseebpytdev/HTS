<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDeterministicSqliteTestSchema();
    }

    /**
     * Temporary sqlite safety net while baseline migration files are incomplete.
     * Keep this minimal and aligned with migration intent and database/schema/mysql-schema.sql.
     * Includes: tenancy/B2C (tenants, users, customers, saved travelers, password reset tokens),
     * booking documents (booking_documents, booking_document_audits, async_task_runs),
     * commerce (agency_wallets, payments, transactions, refunds, ledger_entries).
     */
    private function ensureDeterministicSqliteTestSchema(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('agencies')) {
            Schema::create('agencies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // B2C / tenancy (see database/schema/mysql-schema.sql — tenants, customers).
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('plan_tier', 32)->default('basic');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('agency_user');
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('preferred_currency', 3)->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'preferred_currency')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('preferred_currency', 3)->nullable();
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->char('uuid', 36)->unique();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('phone', 40)->nullable();
                $table->string('password');
                $table->string('preferred_currency', 3)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'preferred_currency')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('preferred_currency', 3)->nullable();
            });
        }

        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name')->nullable();
                $table->string('symbol', 16)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table): void {
                $table->id();
                $table->string('base_currency_code', 3);
                $table->string('target_currency_code', 3);
                $table->decimal('rate', 18, 8);
                $table->timestamp('effective_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_password_reset_tokens')) {
            Schema::create('customer_password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Web guard (`users` broker) — see config/auth.php + database/schema/mysql-schema.sql
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('customer_saved_travelers')) {
            Schema::create('customer_saved_travelers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('first_name');
                $table->string('last_name');
                $table->date('date_of_birth')->nullable();
                $table->string('passport_no', 64)->nullable();
                $table->string('nationality', 80)->nullable();
                $table->string('traveler_type', 16)->default('adult');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('destinations')) {
            Schema::create('destinations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('destination_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('excerpt')->nullable();
                $table->longText('description')->nullable();
                $table->unsignedSmallInteger('duration_days')->nullable();
                $table->decimal('base_price', 12, 2)->default(0);
                $table->string('currency', 3)->default('PKR');
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hotel_room_types')) {
            Schema::create('hotel_room_types', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('hotel_id');
                $table->string('name');
                $table->unsignedTinyInteger('max_adults')->default(2);
                $table->unsignedTinyInteger('max_children')->default(0);
                $table->unsignedSmallInteger('base_capacity')->default(2);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hotel_rates')) {
            Schema::create('hotel_rates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('hotel_room_type_id');
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('meal_plan')->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->decimal('rate_per_night', 12, 2);
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visa_types')) {
            Schema::create('visa_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->unsignedSmallInteger('processing_days')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visa_rates')) {
            Schema::create('visa_rates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('visa_type_id');
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->decimal('amount', 12, 2);
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('transport_types')) {
            Schema::create('transport_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('transport_rates')) {
            Schema::create('transport_rates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('transport_type_id');
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('vehicle_name')->nullable();
                $table->string('route_from')->nullable();
                $table->string('route_to')->nullable();
                $table->string('trip_type')->default('one_way');
                $table->string('currency', 3)->default('PKR');
                $table->decimal('amount', 12, 2);
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('flight_entries')) {
            Schema::create('flight_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('origin');
                $table->string('destination');
                $table->string('airline')->nullable();
                $table->string('flight_no')->nullable();
                $table->dateTime('depart_at')->nullable();
                $table->dateTime('arrive_at')->nullable();
                $table->string('cabin_class')->default('economy');
                $table->unsignedSmallInteger('seats_available')->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->decimal('price', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inquiries')) {
            Schema::create('inquiries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('source')->default('frontend');
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('destination_id')->nullable();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->date('travel_date')->nullable();
                $table->unsignedTinyInteger('adults')->default(1);
                $table->unsignedTinyInteger('children')->default(0);
                $table->decimal('budget', 12, 2)->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->text('message')->nullable();
                $table->text('admin_notes')->nullable();
                $table->string('status')->default('new');
                $table->string('pipeline_stage', 32)->default('new');
                $table->decimal('estimated_value', 12, 2)->nullable();
                $table->timestamp('last_contacted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inquiry_activities')) {
            Schema::create('inquiry_activities', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('inquiry_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type');
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inquiry_follow_ups')) {
            Schema::create('inquiry_follow_ups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('inquiry_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamp('due_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('inquiry_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('quote_number')->nullable();
                $table->string('customer_name');
                $table->string('customer_email')->nullable();
                $table->string('customer_phone')->nullable();
                $table->date('travel_date')->nullable();
                $table->date('return_date')->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->unsignedTinyInteger('adults')->default(1);
                $table->unsignedTinyInteger('children')->default(0);
                $table->unsignedTinyInteger('infants')->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->unsignedBigInteger('promo_code_id')->nullable();
                $table->string('promo_code', 80)->nullable();
                $table->decimal('promo_discount_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('status')->default('draft');
                $table->text('notes')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('quotation_id');
                $table->string('item_type');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('total_price', 12, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('quotation_id');
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('booking_number')->unique();
                $table->string('status')->default('pending');
                $table->timestamp('booked_at')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('PKR');
                $table->string('payment_status')->default('unpaid');
                $table->text('remarks')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_phone')->nullable();
                $table->date('travel_date')->nullable();
                $table->date('return_date')->nullable();
                $table->decimal('subtotal', 12, 2)->nullable();
                $table->decimal('tax_amount', 12, 2)->nullable();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->unsignedBigInteger('promo_code_id')->nullable();
                $table->string('promo_code', 80)->nullable();
                $table->decimal('promo_discount_amount', 12, 2)->default(0);
                $table->timestamp('hold_expires_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->text('internal_notes')->nullable();
                $table->string('invoice_number', 64)->nullable();
                $table->timestamp('invoice_issued_at')->nullable();
                $table->decimal('supplier_cost_total', 12, 2)->nullable();
                $table->string('supplier_cost_currency', 3)->nullable();
                $table->timestamp('supplier_cost_recorded_at')->nullable();
                $table->unsignedBigInteger('supplier_cost_recorded_by_user_id')->nullable();
                $table->string('supplier_flight_hook_status', 32)->nullable();
                $table->string('supplier_hotel_hook_status', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_items')) {
            Schema::create('booking_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->string('item_type');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('total_price', 12, 2)->default(0);
                $table->json('meta')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('travelers')) {
            Schema::create('travelers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->string('first_name');
                $table->string('last_name');
                $table->date('date_of_birth')->nullable();
                $table->string('passport_no', 64)->nullable();
                $table->string('nationality', 80)->nullable();
                $table->string('traveler_type', 16)->default('adult');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_status_histories')) {
            Schema::create('booking_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('event', 64)->nullable();
                $table->text('reason')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        // Booking documents + virus-scan task queue row (App\Models\BookingDocument* / AsyncTaskRun).
        if (! Schema::hasTable('booking_documents')) {
            Schema::create('booking_documents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
                $table->unsignedBigInteger('uploaded_by_customer_id')->nullable();
                $table->unsignedBigInteger('validated_by_user_id')->nullable();
                $table->string('document_type');
                $table->uuid('document_group_uuid')->nullable();
                $table->unsignedInteger('version_number')->default(1);
                $table->unsignedBigInteger('superseded_by_document_id')->nullable();
                $table->string('validation_status');
                $table->string('virus_scan_status')->nullable();
                $table->timestamp('virus_scanned_at')->nullable();
                $table->string('virus_scan_note')->nullable();
                $table->string('original_name');
                $table->string('storage_disk');
                $table->string('storage_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('sha256', 64)->nullable();
                $table->boolean('is_customer_visible')->default(false);
                $table->text('notes')->nullable();
                $table->text('validation_note')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->unsignedBigInteger('archived_by_user_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_document_audits')) {
            Schema::create('booking_document_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_document_id');
                $table->unsignedBigInteger('booking_id');
                $table->string('action');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->unsignedBigInteger('actor_customer_id')->nullable();
                $table->string('actor_role')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('async_task_runs')) {
            Schema::create('async_task_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('task_type', 120);
                $table->string('status', 40)->default('queued');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->json('payload')->nullable();
                $table->json('result')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hotels')) {
            Schema::create('hotels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->unsignedTinyInteger('star_rating')->nullable();
                $table->text('address')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('group_type')->nullable();
                $table->date('departure_date')->nullable();
                $table->date('return_date')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->unsignedInteger('seats_left')->nullable();
                $table->string('status')->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_connections')) {
            Schema::create('integration_connections', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('provider', 64);
                $table->string('environment', 32)->default('testing');
                $table->string('account_key')->nullable();
                $table->text('base_url')->nullable();
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_credentials')) {
            Schema::create('integration_credentials', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('integration_connection_id');
                $table->string('credential_key', 120);
                $table->text('credential_value_encrypted')->nullable();
                $table->boolean('is_secret')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_tokens')) {
            Schema::create('integration_tokens', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('integration_connection_id');
                $table->string('kind', 32)->default('access');
                $table->text('token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('scope')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('last_refreshed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_request_logs')) {
            Schema::create('integration_request_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('integration_connection_id')->nullable();
                $table->string('provider', 64);
                $table->string('operation', 128);
                $table->string('environment', 32)->default('testing');
                $table->string('correlation_id', 64);
                $table->string('trace_id', 64)->nullable();
                $table->string('http_method', 16)->nullable();
                $table->text('url')->nullable();
                $table->json('request_headers')->nullable();
                $table->json('request_body')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('integration_events')) {
            Schema::create('integration_events', function (Blueprint $table): void {
                $table->id();
                $table->string('event_type', 128);
                $table->string('provider', 64)->nullable();
                $table->string('correlation_id', 64);
                $table->unsignedBigInteger('integration_connection_id')->nullable();
                $table->string('aggregate_type', 64)->nullable();
                $table->string('aggregate_id', 64)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at');
            });
        }

        if (! Schema::hasTable('integration_response_logs')) {
            Schema::create('integration_response_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('integration_request_log_id');
                $table->string('correlation_id', 64);
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->json('response_headers')->nullable();
                $table->json('response_body')->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->string('error_category', 64)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('package_images')) {
            Schema::create('package_images', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('package_id');
                $table->string('path')->nullable();
                $table->boolean('is_cover')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('group_images')) {
            Schema::create('group_images', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->string('path')->nullable();
                $table->boolean('is_cover')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('package_departures')) {
            Schema::create('package_departures', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('package_id');
                $table->date('departure_date')->nullable();
                $table->date('return_date')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seo_pages')) {
            Schema::create('seo_pages', function (Blueprint $table): void {
                $table->id();
                $table->string('page_key')->unique();
                $table->string('title')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->text('meta_keywords')->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->json('schema_markup')->nullable();
                $table->boolean('is_indexable')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('content_blocks')) {
            Schema::create('content_blocks', function (Blueprint $table): void {
                $table->id();
                $table->string('block_key')->unique();
                $table->string('title')->nullable();
                $table->longText('body')->nullable();
                $table->string('status', 30)->default('draft');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->string('group')->nullable();
                $table->boolean('is_public')->default(false);
                $table->timestamps();
            });
        }

        // Backward-compatibility for code paths still pointing at application_settings.
        if (! Schema::hasTable('application_settings')) {
            Schema::create('application_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('export_histories')) {
            Schema::create('export_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('export_type', 64);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->json('meta')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        // Payments / wallet / ledger (see database/schema/mysql-schema.sql).
        if (! Schema::hasTable('agency_wallets')) {
            Schema::create('agency_wallets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('agency_id')->unique();
                $table->string('currency', 3)->default('PKR');
                $table->decimal('balance', 14, 2)->default(0);
                $table->decimal('credit_limit', 14, 2)->nullable();
                $table->unsignedSmallInteger('payment_terms_days')->default(0);
                $table->timestamp('last_payment_at')->nullable();
                $table->timestamp('first_negative_balance_at')->nullable();
                $table->timestamp('overdue_since')->nullable();
                $table->boolean('is_overdue')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('agency_id');
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('recorded_by_user_id')->nullable();
                $table->string('flow_type', 32);
                $table->string('source', 32);
                $table->string('status', 32);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('PKR');
                $table->string('idempotency_key', 64)->nullable()->unique();
                $table->string('gateway_driver', 32)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('payment_id');
                $table->string('gateway_driver', 32);
                $table->string('external_id', 191)->nullable();
                $table->string('direction', 16)->default('charge');
                $table->string('status', 32);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('PKR');
                $table->json('raw_response')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->string('status', 32);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('PKR');
                $table->string('gateway_refund_id', 191)->nullable();
                $table->string('reason', 191)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('agency_id');
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->unsignedBigInteger('refund_id')->nullable();
                $table->string('entry_type', 32);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('PKR');
                $table->string('description')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }
}
