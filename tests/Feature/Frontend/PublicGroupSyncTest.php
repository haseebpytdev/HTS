<?php

namespace Tests\Feature\Frontend;

use App\Models\Agency;
use App\Models\TravelGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGroupSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_group_pages_reflect_admin_managed_name_updates(): void
    {
        $agency = Agency::query()->create(['name' => 'Group Agency', 'code' => 'PUB-GRP', 'is_active' => true]);

        $group = TravelGroup::query()->create([
            'agency_id' => $agency->id,
            'name' => 'Original Public Group',
            'slug' => 'original-public-group',
            'status' => 'open',
            'departure_date' => now()->addMonth()->toDateString(),
        ]);

        $this->get(route('frontend.groups.index'))
            ->assertOk()
            ->assertSee('Original Public Group');

        $group->update([
            'name' => 'Updated Public Group',
            'status' => 'closed',
        ]);

        $this->get(route('frontend.groups.index'))
            ->assertOk()
            ->assertSee('Updated Public Group')
            ->assertDontSee('Original Public Group');

        $this->get(route('frontend.groups.show', $group->slug))
            ->assertOk()
            ->assertSee('Updated Public Group');
    }

    public function test_group_status_filter_reflects_recent_admin_status_changes(): void
    {
        $agency = Agency::query()->create(['name' => 'Group Filter Agency', 'code' => 'FLT-GRP', 'is_active' => true]);

        $group = TravelGroup::query()->create([
            'agency_id' => $agency->id,
            'name' => 'Status Sync Group',
            'slug' => 'status-sync-group',
            'status' => 'open',
            'departure_date' => now()->addMonths(2)->toDateString(),
        ]);

        $this->get(route('frontend.groups.index', ['status' => 'open']))
            ->assertOk()
            ->assertSee('Status Sync Group');

        $group->update(['status' => 'closed']);

        $this->get(route('frontend.groups.index', ['status' => 'open']))
            ->assertOk()
            ->assertDontSee('Status Sync Group');

        $this->get(route('frontend.groups.index', ['status' => 'closed']))
            ->assertOk()
            ->assertSee('Status Sync Group');
    }
}
