<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['extension', 'sort'])]
class CvFileType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'cv_file_type_job');
    }

    public function coverLetterJobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'cover_letter_file_type_job');
    }
}
