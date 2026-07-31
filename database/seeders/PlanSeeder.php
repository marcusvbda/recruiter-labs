<?php

namespace Database\Seeders;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default',
                'features' => [Feature::Leads->value],
                'limits' => [
                    Limit::Jobs->value => 5,
                    Limit::Applications->value => 1000,
                ],
            ],
        );
    }
}
