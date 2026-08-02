<?php

namespace Database\Factories;

use App\Enums\ApplicationDocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'doc', 'docx']);

        return [
            'company_id' => Company::factory(),
            'application_id' => fn (array $attributes): int => Application::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'type' => ApplicationDocumentType::Cv,
            'disk' => 'local',
            'path' => 'companies/'.fake()->uuid().'/applications/'.fake()->uuid().'/'.fake()->uuid().'.'.$extension,
            'original_name' => 'resume.'.$extension,
            'mime_type' => $extension === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'extension' => $extension,
            'size' => fake()->numberBetween(1_000, 1_000_000),
            'checksum' => hash('sha256', fake()->uuid()),
            'uploaded_at' => now(),
        ];
    }
}
