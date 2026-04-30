<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsSeoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_seo_pages_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->actingAs($user)->get(route('admin.seo-pages.index'));

        $response->assertOk();
    }

    public function test_admin_can_open_content_blocks_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->actingAs($user)->get(route('admin.content-blocks.index'));

        $response->assertOk();
    }
}
