<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->updateOrCreate(
            ['slug' => 'gravity-labs'],
            [
                'name' => 'Gravity Labs',
                'plan_id' => Plan::default()->id,
            ],
        );

        User::query()
            ->where('email', 'admin@user.com')
            ->firstOrFail()
            ->companies()
            ->syncWithoutDetaching([$company->getKey()]);
    }
}
