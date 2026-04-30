<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\FlightEntry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FlightEntryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureFlightEntriesTableExists();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensureFlightEntriesTableExists(): void
    {
        if (Schema::hasTable('flight_entries')) {
            return;
        }

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

    public function test_admin_can_list_filter_create_update_and_delete_flight_entries(): void
    {
        $admin = $this->admin();

        FlightEntry::query()->create([
            'airline' => 'PIA',
            'origin' => 'KHI',
            'destination' => 'JED',
            'depart_at' => '2027-01-05 10:00:00',
            'arrive_at' => '2027-01-05 15:00:00',
            'price' => 90000,
            'currency' => 'PKR',
            'flight_no' => 'PK-701',
            'is_active' => true,
        ]);
        FlightEntry::query()->create([
            'airline' => 'Saudia',
            'origin' => 'LHE',
            'destination' => 'MED',
            'depart_at' => '2027-02-05 11:00:00',
            'arrive_at' => '2027-02-05 14:00:00',
            'price' => 1200,
            'currency' => 'SAR',
            'flight_no' => 'SV-735',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.flights.index', [
                'q' => 'PIA',
                'currency' => 'pkr',
                'is_active' => 1,
                'depart_from' => '2027-01-01',
                'depart_to' => '2027-01-31',
            ]))
            ->assertOk()
            ->assertSee('PIA')
            ->assertDontSee('Saudia');

        $this->actingAs($admin)
            ->post(route('admin.flights.store'), [
                'airline' => 'Airblue',
                'origin' => 'khi',
                'destination' => 'jed',
                'depart_at' => '2027-03-01 09:00:00',
                'arrive_at' => '2027-03-01 13:00:00',
                'price' => 85000,
                'currency' => 'pkr',
                'flight_no' => 'PA-901',
                'cabin_class' => 'economy',
                'seats_available' => 35,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $entry = FlightEntry::query()->where('airline', 'Airblue')->firstOrFail();
        $this->assertSame('PKR', $entry->currency);
        $this->assertSame('KHI', $entry->origin);
        $this->assertSame('JED', $entry->destination);

        $this->actingAs($admin)
            ->put(route('admin.flights.update', $entry), [
                'airline' => 'Airblue Updated',
                'origin' => 'ISB',
                'destination' => 'MED',
                'depart_at' => '2027-03-05 09:00:00',
                'arrive_at' => '2027-03-05 14:00:00',
                'price' => 93000,
                'currency' => 'PKR',
                'flight_no' => 'PA-902',
                'cabin_class' => 'business',
                'seats_available' => 20,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.flights.edit', $entry));

        $this->assertDatabaseHas('flight_entries', [
            'id' => $entry->id,
            'airline' => 'Airblue Updated',
            'origin' => 'ISB',
            'destination' => 'MED',
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.flights.destroy', $entry))
            ->assertRedirect(route('admin.flights.index'));

        $this->assertDatabaseMissing('flight_entries', ['id' => $entry->id]);
    }

    public function test_flight_entry_validation_rejects_invalid_payload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.flights.store'), [
                'airline' => '',
                'origin' => 'PK',
                'destination' => 'TOOLONG',
                'price' => -1,
                'currency' => 'PK',
                'arrive_at' => '2027-01-01 08:00:00',
                'depart_at' => '2027-01-02 08:00:00',
                'cabin_class' => 'invalid',
            ])
            ->assertSessionHasErrors([
                'airline',
                'origin',
                'destination',
                'price',
                'currency',
                'arrive_at',
                'cabin_class',
            ]);
    }
}
