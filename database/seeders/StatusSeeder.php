<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /** @var list<array{name: string, color: string, order: int, is_hired: bool}> */
    private const DEFAULT_STATUSES = [
        ['name' => 'Applied', 'color' => '#3b82f6', 'order' => 1, 'is_hired' => false],
        ['name' => 'Screening', 'color' => '#f59e0b', 'order' => 2, 'is_hired' => false],
        ['name' => 'Interview', 'color' => '#8b5cf6', 'order' => 3, 'is_hired' => false],
        ['name' => 'Offer', 'color' => '#06b6d4', 'order' => 4, 'is_hired' => false],
        ['name' => 'Hired', 'color' => '#22c55e', 'order' => 5, 'is_hired' => true],
        ['name' => 'Rejected', 'color' => '#ef4444', 'order' => 6, 'is_hired' => false],
    ];

    public function run(): void
    {
        Company::query()
            ->select(['id', 'slug'])
            ->eachById(function (Company $company): void {
                foreach (self::DEFAULT_STATUSES as $status) {
                    $createdStatus = Status::query()->firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'name' => $status['name'],
                        ],
                        [
                            'color' => $status['color'],
                            'order' => $status['order'],
                            'is_hired' => $status['is_hired'],
                        ],
                    );

                    if ($status['is_hired'] && ! $createdStatus->is_hired) {
                        $createdStatus->update(['is_hired' => true]);
                    }
                }

                $this->seedDemoApplications($company);
            });
    }

    private function seedDemoApplications(Company $company): void
    {
        if ($company->slug !== 'gravity-labs') {
            return;
        }

        $job = Job::query()
            ->whereBelongsTo($company)
            ->where('name', 'Senior Full Stack Engineer')
            ->first();

        if (! $job) {
            return;
        }

        $candidates = [
            ['name' => 'Sofia Martins', 'email' => 'sofia.martins@example.test', 'phone' => '+353 85 123 4567', 'status' => 'Applied'],
            ['name' => 'Daniel Silva', 'email' => 'daniel.silva@example.test', 'phone' => '+55 11 99876-5432', 'status' => 'Applied'],
            ['name' => 'Emma Walsh', 'email' => 'emma.walsh@example.test', 'phone' => '+353 87 234 5678', 'status' => 'Screening'],
            ['name' => 'Lucas Ferreira', 'email' => 'lucas.ferreira@example.test', 'phone' => '+55 21 98765-4321', 'status' => 'Interview'],
            ['name' => 'Maya Patel', 'email' => 'maya.patel@example.test', 'phone' => '+44 7700 900123', 'status' => 'Interview'],
            ['name' => 'Noah Bennett', 'email' => 'noah.bennett@example.test', 'phone' => '+1 415 555 0198', 'status' => 'Offer'],
            ['name' => 'Ana Costa', 'email' => 'ana.costa@example.test', 'phone' => '+351 912 345 678', 'status' => 'Hired'],
            ['name' => 'Oliver Jones', 'email' => 'oliver.jones@example.test', 'phone' => '+44 7700 900456', 'status' => 'Rejected'],
        ];

        foreach ($candidates as $candidateData) {
            $candidate = Candidate::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email' => $candidateData['email'],
                ],
                [
                    'name' => $candidateData['name'],
                    'phone' => $candidateData['phone'],
                    'socials' => [],
                ],
            );

            $status = Status::query()
                ->whereBelongsTo($company)
                ->where('name', $candidateData['status'])
                ->firstOrFail();

            Application::query()->updateOrCreate(
                [
                    'job_id' => $job->id,
                    'candidate_id' => $candidate->id,
                ],
                [
                    'company_id' => $company->id,
                    'status_id' => $status->id,
                ],
            );
        }
    }
}
