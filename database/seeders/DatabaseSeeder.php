<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);
        $this->call(CvFileTypeSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(CompanySeeder::class);
        // Pipelines before jobs: a job cannot exist without the workflow it runs on.
        $this->call(PipelineSeeder::class);
        $this->call(JobSeeder::class);
        $this->call(ApplicationSeeder::class);
    }
}
