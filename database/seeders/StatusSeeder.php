<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /** @var list<array{name: string, color: string, order: int}> */
    private const DEFAULT_STATUSES = [
        ['name' => 'Applied', 'color' => '#3b82f6', 'order' => 1],
        ['name' => 'Screening', 'color' => '#f59e0b', 'order' => 2],
        ['name' => 'Interview', 'color' => '#8b5cf6', 'order' => 3],
        ['name' => 'Offer', 'color' => '#06b6d4', 'order' => 4],
        ['name' => 'Hired', 'color' => '#22c55e', 'order' => 5],
        ['name' => 'Rejected', 'color' => '#ef4444', 'order' => 6],
    ];

    public function run(): void
    {
        Company::query()
            ->select('id')
            ->eachById(function (Company $company): void {
                foreach (self::DEFAULT_STATUSES as $status) {
                    Status::query()->firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'name' => $status['name'],
                        ],
                        [
                            'color' => $status['color'],
                            'order' => $status['order'],
                        ],
                    );
                }
            });
    }
}
