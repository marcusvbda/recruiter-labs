<?php

namespace Database\Seeders;

use App\Models\CvFileType;
use Illuminate\Database\Seeder;

class CvFileTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['pdf', 'doc', 'docx'] as $index => $extension) {
            CvFileType::query()->updateOrCreate(
                ['extension' => $extension],
                ['sort' => $index + 1],
            );
        }
    }
}
