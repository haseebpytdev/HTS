<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\TransportRate;
use App\Models\TransportType;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransportRateCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureTransportTablesExist();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensureTransportTablesExist(): void
    {
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
                $table->foreignId('transport_type_id')->constrained('transport_types')->cascadeOnDelete();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('vehicle_name')->nullable();
                $table->string('route_from')->nullable();
                $table->string('route_to')->nullable();
                $table->string('trip_type')->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->decimal('amount', 12, 2);
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_list_filter_create_update_and_delete_transport_rates(): void
    {
        $admin = $this->admin();
        $typeA = TransportType::query()->create(['name' => 'Sedan', 'slug' => 'sedan', 'is_active' => true]);
        $typeB = TransportType::query()->create(['name' => 'Bus', 'slug' => 'bus', 'is_active' => true]);

        TransportRate::query()->create([
            'transport_type_id' => $typeA->id,
            'vehicle_name' => 'Makkah Shuttle',
            'route_from' => 'JED',
            'route_to' => 'MAK',
            'trip_type' => 'one_way',
            'currency' => 'PKR',
            'amount' => 2500,
            'valid_from' => '2027-01-01',
            'valid_to' => '2027-03-01',
            'is_active' => true,
        ]);
        TransportRate::query()->create([
            'transport_type_id' => $typeB->id,
            'vehicle_name' => 'Tour Bus',
            'route_from' => 'MED',
            'route_to' => 'MAK',
            'trip_type' => 'round_trip',
            'currency' => 'SAR',
            'amount' => 300,
            'valid_from' => '2027-01-15',
            'valid_to' => '2027-02-15',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.transport-rates.index', [
                'transport_type_id' => $typeA->id,
                'label' => 'Shuttle',
                'currency' => 'pkr',
                'is_active' => 1,
                'valid_from' => '2026-12-01',
                'valid_to' => '2027-12-31',
            ]))
            ->assertOk()
            ->assertSee('Makkah Shuttle')
            ->assertDontSee('Tour Bus');

        $this->actingAs($admin)
            ->post(route('admin.transport-rates.store'), [
                'transport_type_id' => $typeA->id,
                'label' => 'Airport Taxi',
                'route_from' => 'JED',
                'route_to' => 'MAK',
                'trip_type' => 'one_way',
                'currency' => 'usd',
                'amount' => 80.50,
                'valid_from' => '2027-04-01',
                'valid_to' => '2027-05-01',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $transportRate = TransportRate::query()->where('vehicle_name', 'Airport Taxi')->firstOrFail();
        $this->assertSame('USD', $transportRate->currency);

        $this->actingAs($admin)
            ->put(route('admin.transport-rates.update', $transportRate), [
                'transport_type_id' => $typeB->id,
                'label' => 'Airport Taxi Updated',
                'route_from' => 'JED',
                'route_to' => 'MED',
                'trip_type' => 'round_trip',
                'currency' => 'SAR',
                'amount' => 95.00,
                'valid_from' => '2027-05-02',
                'valid_to' => '2027-05-30',
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.transport-rates.edit', $transportRate));

        $this->assertDatabaseHas('transport_rates', [
            'id' => $transportRate->id,
            'vehicle_name' => 'Airport Taxi Updated',
            'transport_type_id' => $typeB->id,
            'currency' => 'SAR',
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.transport-rates.destroy', $transportRate))
            ->assertRedirect(route('admin.transport-rates.index'));

        $this->assertDatabaseMissing('transport_rates', ['id' => $transportRate->id]);
    }

    public function test_transport_rate_validation_fails_for_invalid_numeric_and_dates(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.transport-rates.store'), [
                'transport_type_id' => 999999,
                'label' => '',
                'trip_type' => 'invalid',
                'currency' => 'PK',
                'amount' => -1,
                'valid_from' => '2027-06-10',
                'valid_to' => '2027-05-01',
            ])
            ->assertSessionHasErrors([
                'transport_type_id',
                'label',
                'trip_type',
                'currency',
                'amount',
                'valid_to',
            ]);
    }
}
