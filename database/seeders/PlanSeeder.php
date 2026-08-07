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
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'sort_order' => 10,
                'features' => [Feature::Candidates->value],
                'limits' => [
                    Limit::Users->value => 2,
                    Limit::Jobs->value => 1,
                    Limit::Applications->value => 100,
                    Limit::AiAnalyses->value => 100,
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'sort_order' => 20,
                'features' => [Feature::Candidates->value, Feature::OwnAiKey->value],
                'limits' => [
                    Limit::Users->value => 10,
                    Limit::Jobs->value => 20,
                    Limit::Applications->value => 1000,
                    Limit::AiAnalyses->value => 1000,
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'sort_order' => 30,
                'features' => [Feature::Candidates->value, Feature::OwnAiKey->value],
                'limits' => [
                    Limit::Users->value => null,
                    Limit::Jobs->value => null,
                    Limit::Applications->value => null,
                    Limit::AiAnalyses->value => null,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
