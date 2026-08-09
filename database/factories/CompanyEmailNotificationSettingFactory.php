<?php

namespace Database\Factories;

use App\Enums\EmailNotificationType;
use App\Models\Company;
use App\Models\CompanyEmailNotificationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyEmailNotificationSetting>
 */
class CompanyEmailNotificationSettingFactory extends Factory
{
    protected $model = CompanyEmailNotificationSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'notification_type' => $this->faker->randomElement(EmailNotificationType::cases()),
            'enabled' => false,
        ];
    }
}
