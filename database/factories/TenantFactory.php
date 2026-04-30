<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->company());

        return [
            'name' => fake()->company(),
            'slug' => $slug !== '' ? $slug : 'tenant-'.fake()->unique()->numerify('####'),
        ];
    }
}
