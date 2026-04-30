<?php

namespace Tests\Feature\Frontend;

use App\Models\Agency;
use App\Models\ApplicationSetting;
use App\Models\Category;
use App\Models\Destination;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FrontendServiceVisibilityToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureEnterpriseSettingsColumns();
    }

    private function ensureEnterpriseSettingsColumns(): void
    {
        if (! Schema::hasTable('application_settings')) {
            return;
        }

        Schema::table('application_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_settings', 'scope')) {
                $table->string('scope', 32)->default(ApplicationSetting::SCOPE_PLATFORM)->after('id');
            }
            if (! Schema::hasColumn('application_settings', 'scope_id')) {
                $table->unsignedBigInteger('scope_id')->nullable()->after('scope');
            }
            if (! Schema::hasColumn('application_settings', 'provider')) {
                $table->string('provider', 64)->nullable()->after('scope_id');
            }
            if (! Schema::hasColumn('application_settings', 'module')) {
                $table->string('module', 64)->nullable()->after('provider');
            }
            if (! Schema::hasColumn('application_settings', 'category')) {
                $table->string('category', 64)->nullable()->after('module');
            }
            if (! Schema::hasColumn('application_settings', 'value_type')) {
                $table->string('value_type', 16)->default(ApplicationSetting::TYPE_STRING)->after('value');
            }
        });
    }

    public function test_group_ticketing_routes_return_not_found_when_feature_is_disabled(): void
    {
        $agency = Agency::query()->create(['name' => 'Toggle Agency', 'code' => 'TGL-GRP', 'is_active' => true]);
        $group = TravelGroup::query()->create([
            'agency_id' => $agency->id,
            'name' => 'Toggle Group',
            'slug' => 'toggle-group',
            'status' => 'open',
            'departure_date' => now()->addMonth()->toDateString(),
        ]);

        ApplicationSetting::setValue('app.feature_flags.group_ticketing_enabled', '0', [
            'scope' => ApplicationSetting::SCOPE_PLATFORM,
            'category' => 'app',
            'value_type' => ApplicationSetting::TYPE_BOOL,
        ]);

        $this->get(route('frontend.groups.index'))->assertNotFound();
        $this->get(route('frontend.groups.show', $group->slug))->assertNotFound();
    }

    public function test_umrah_packages_routes_return_not_found_when_feature_is_disabled(): void
    {
        $agency = Agency::query()->create(['name' => 'Toggle Agency', 'code' => 'TGL-PKG', 'is_active' => true]);
        $destination = Destination::query()->create(['name' => 'Makkah', 'slug' => 'toggle-makkah', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Umrah', 'slug' => 'toggle-umrah', 'is_active' => true]);
        $package = TravelPackage::query()->create([
            'agency_id' => $agency->id,
            'destination_id' => $destination->id,
            'category_id' => $category->id,
            'title' => 'Toggle Package',
            'slug' => 'toggle-package',
            'base_price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        ApplicationSetting::setValue('app.feature_flags.umrah_packages_enabled', '0', [
            'scope' => ApplicationSetting::SCOPE_PLATFORM,
            'category' => 'app',
            'value_type' => ApplicationSetting::TYPE_BOOL,
        ]);

        $this->get(route('frontend.packages.index'))->assertNotFound();
        $this->get(route('frontend.packages.show', $package->slug))->assertNotFound();
    }
}
