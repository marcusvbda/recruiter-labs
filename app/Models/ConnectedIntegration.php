<?php

namespace App\Models;

use App\Enums\ConnectedIntegrationStatus;
use Database\Factories\ConnectedIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $company_id
 * @property int $user_id
 * @property string $plugin_key
 * @property ConnectedIntegrationStatus $status
 * @property string|null $external_account_id
 * @property string|null $account_email
 * @property string|null $account_name
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property list<string>|null $granted_scopes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $expires_at
 * @property Carbon|null $connected_at
 * @property Carbon|null $last_refreshed_at
 * @property Carbon|null $last_error_at
 */
#[Fillable(['company_id', 'user_id', 'plugin_key', 'status', 'external_account_id', 'account_email', 'account_name', 'access_token', 'refresh_token', 'granted_scopes', 'metadata', 'expires_at', 'connected_at', 'last_refreshed_at', 'last_error_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class ConnectedIntegration extends Model
{
    /** @use HasFactory<ConnectedIntegrationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ConnectedIntegrationStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'granted_scopes' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
