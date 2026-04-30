<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisaRate;
use App\Models\VisaType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisaRateCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureVisaTablesExist();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function ensureVisaTablesExist(): void
    {
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
                $table->foreignId('visa_type_id')->constrained('visa_types')->cascadeOnDelete();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('currency', 3)->default('PKR');
                $table->decimal('amount', 12, 2);
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_list_filter_create_update_and_delete_visa_rates(): void
    {
        $admin = $this->admin();
        $typeA = VisaType::query()->create(['name' => 'Umrah', 'slug' => 'umrah', 'is_active' => true]);
        $typeB = VisaType::query()->create(['name' => 'Tourist', 'slug' => 'tourist', 'is_active' => true]);

        VisaRate::query()->create([
            'visa_type_id' => $typeA->id,
            'currency' => 'PKR',
            'amount' => 35000,
            'valid_from' => '2027-01-01',
            'valid_to' => '2027-06-01',
            'is_active' => true,
        ]);
        VisaRate::query()->create([
            'visa_type_id' => $typeB->id,
            'currency' => 'SAR',
            'amount' => 500,
            'valid_from' => '2027-02-01',
            'valid_to' => '2027-03-01',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.visa-rates.index', [
                'visa_type_id' => $typeA->id,
                'currency' => 'pkr',
                'is_active' => 1,
                'valid_from' => '2026-12-01',
                'valid_to' => '2027-12-31',
            ]))
            ->assertOk()
            ->assertSee('35,000.00')
            ->assertDontSee('500.00');

        $this->actingAs($admin)
            ->post(route('admin.visa-rates.store'), [
                'visa_type_id' => $typeA->id,
                'currency' => 'usd',
                'amount' => 100.50,
                'valid_from' => '2027-04-01',
                'valid_to' => '2027-05-01',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $visaRate = VisaRate::query()->where('amount', 100.50)->firstOrFail();
        $this->assertSame('USD', $visaRate->currency);

        $this->actingAs($admin)
            ->put(route('admin.visa-rates.update', $visaRate), [
                'visa_type_id' => $typeB->id,
                'currency' => 'SAR',
                'amount' => 120.00,
                'valid_from' => '2027-05-01',
                'valid_to' => '2027-05-31',
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.visa-rates.edit', $visaRate));

        $this->assertDatabaseHas('visa_rates', [
            'id' => $visaRate->id,
            'visa_type_id' => $typeB->id,
            'currency' => 'SAR',
            'amount' => '120.00',
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.visa-rates.destroy', $visaRate))
            ->assertRedirect(route('admin.visa-rates.index'));

        $this->assertDatabaseMissing('visa_rates', ['id' => $visaRate->id]);
    }

    public function test_visa_rate_validation_rejects_invalid_numeric_and_date_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.visa-rates.store'), [
                'visa_type_id' => 999999,
                'currency' => 'PK',
                'amount' => -10,
                'valid_from' => '2027-06-10',
                'valid_to' => '2027-05-01',
            ])
            ->assertSessionHasErrors(['visa_type_id', 'currency', 'amount', 'valid_to']);
    }
}
