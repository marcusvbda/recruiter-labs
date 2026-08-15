<?php

namespace App\Models;

use App\Exceptions\RecruitmentWorkflowException;
use Database\Factories\StatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $pipeline_id
 * @property string $name
 * @property string $color
 * @property int $order
 * @property bool $is_hired
 * @property bool $sends_email
 * @property string|null $email_subject
 * @property string|null $email_body
 */
#[Fillable(['company_id', 'pipeline_id', 'name', 'color', 'order', 'is_hired', 'sends_email', 'email_subject', 'email_body'])]
class Status extends Model
{
    /** @use HasFactory<StatusFactory> */
    use HasFactory;

    protected $attributes = [
        'is_hired' => false,
        'sends_email' => false,
    ];

    protected static function booted(): void
    {
        // Deleting a status with applications would erase where those candidates
        // are in the process. The `restrict` foreign key on `applications` also
        // blocks it; this hook makes the reason reportable.
        static::deleting(function (Status $status): void {
            $applicationCount = $status->applications()->count();

            if ($applicationCount > 0) {
                throw RecruitmentWorkflowException::statusInUse($applicationCount);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_hired' => 'boolean',
            'sends_email' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Whether entering this status should email the candidate. A status with the
     * toggle on but no subject or body has nothing to send.
     */
    public function sendsOnEnterEmail(): bool
    {
        return $this->sends_email
            && filled($this->email_subject)
            && filled($this->email_body);
    }
}
