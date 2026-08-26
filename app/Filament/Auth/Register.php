<?php

namespace App\Filament\Auth;

use App\Http\Controllers\WorkspaceInvitationController;
use App\Models\CompanyInvitation;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

/**
 * The stock registration page, except when the visitor arrived through an
 * invitation: then the account being created *is* the invited identity, so the
 * address is fixed rather than asked for. With no invitation in session this
 * behaves exactly like Filament's own page.
 */
class Register extends BaseRegister
{
    private bool $invitedEmailResolved = false;

    private ?string $invitedEmail = null;

    protected function getEmailFormComponent(): Component
    {
        $component = parent::getEmailFormComponent();

        $invitedEmail = $this->invitedEmail();

        if ($invitedEmail === null || ! $component instanceof TextInput) {
            return $component;
        }

        // `readOnly` rather than `disabled`: the value has to stay in the
        // submitted state so it is still validated and dehydrated normally.
        return $component
            ->default($invitedEmail)
            ->readOnly()
            ->helperText(__('invitation.register.email_locked'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(#[SensitiveParameter] array $data): array
    {
        $invitedEmail = $this->invitedEmail();

        if ($invitedEmail !== null) {
            // The client can post anything; the invited identity is decided here.
            $data['email'] = $invitedEmail;
        }

        return parent::mutateFormDataBeforeRegister($data);
    }

    /**
     * Only a still-pending invitation whose address has no account yet locks the
     * form: an address that already registered belongs to someone who should
     * sign in, and forcing the field onto it would only produce a duplicate.
     */
    private function invitedEmail(): ?string
    {
        if ($this->invitedEmailResolved) {
            return $this->invitedEmail;
        }

        $this->invitedEmailResolved = true;

        $token = session()->get(WorkspaceInvitationController::SESSION_TOKEN_KEY);

        if (! is_string($token)) {
            return null;
        }

        $invitation = CompanyInvitation::findByToken($token);

        if ($invitation === null || ! $invitation->isPending()) {
            return null;
        }

        $emailTaken = User::query()
            ->whereRaw('lower(email) = ?', [CompanyInvitation::normalizeEmail($invitation->email)])
            ->exists();

        return $this->invitedEmail = $emailTaken ? null : $invitation->email;
    }
}
