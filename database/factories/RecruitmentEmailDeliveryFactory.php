<?php

namespace Database\Factories;

use App\Enums\EmailProvider;
use App\Enums\RecruitmentEmailDeliveryStatus;
use App\Models\Company;
use App\Models\RecruitmentEmailDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecruitmentEmailDelivery> */
class RecruitmentEmailDeliveryFactory extends Factory
{
    protected $model = RecruitmentEmailDelivery::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'provider' => EmailProvider::Gmail,
            'idempotency_key' => 'recruiter-labs/'.fake()->unique()->sha256(),
            'status' => RecruitmentEmailDeliveryStatus::Pending,
            'attempts' => 0,
        ];
    }
}
