<?php

namespace Database\Factories;

use App\Enums\AutomationActionType;
use App\Enums\AutomationEventType;
use App\Models\AutomationEvent;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationEvent>
 */
class AutomationEventFactory extends Factory
{
    protected $model = AutomationEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            // Resolved after `company_id`: a caller who forgets `->for()` still
            // gets a tenancy-consistent fixture, since the automatable job
            // belongs to the same company as the automation event.
            'automatable_id' => fn (array $attributes) => Job::factory()->create(['company_id' => $attributes['company_id']])->id,
            // Uses the morph map alias (not the FQCN) to match how `associate()`
            // actually persists this column in production.
            'automatable_type' => (new Job)->getMorphClass(),
            'event_type' => $this->faker->randomElement(AutomationEventType::cases()),
            'action_type' => AutomationActionType::SendEmail,
            'action_config' => [],
            'is_active' => true,
        ];
    }
}
