<?php

namespace App\Http\Controllers;

use App\Actions\AcceptWorkspaceInvitation;
use App\Exceptions\WorkspaceInvitationEmailMismatch;
use App\Exceptions\WorkspaceInvitationEmailNotVerified;
use App\Exceptions\WorkspaceInvitationNotAcceptable;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public landing page of an invitation. It is opened by whoever holds the
 * link — frequently a guest, sometimes the wrong account — so it never renders
 * the invitation model: every prop is built explicitly here, and each piece of
 * workspace identity is emitted only for a viewer entitled to it.
 */
class WorkspaceInvitationController extends Controller
{
    /**
     * The token the viewer is currently working through. Filament's own
     * registration page reads it to bind the new account to the invited
     * address instead of an arbitrary one.
     */
    public const SESSION_TOKEN_KEY = 'workspace_invitation_token';

    public function __construct(private readonly AcceptWorkspaceInvitation $acceptInvitation) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = CompanyInvitation::findByToken($token);
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        if ($invitation === null) {
            return Inertia::render('invitation/show', $this->invalidProps($user));
        }

        $company = $invitation->company()->first();

        if (! $company instanceof Company) {
            return Inertia::render('invitation/show', $this->invalidProps($user));
        }

        $state = $this->resolveState($invitation, $company, $user);

        if (in_array($state, ['guest', 'email_unverified'], true)) {
            $this->rememberReturnPath($request, $token);
        }

        return Inertia::render('invitation/show', $this->props($state, $invitation, $company, $user, $token));
    }

    public function accept(Request $request, string $token): mixed
    {
        $invitation = CompanyInvitation::findByToken($token);
        $user = $request->user();

        if ($invitation === null || ! $user instanceof User) {
            return redirect()->route('workspace-invitations.show', ['token' => $token]);
        }

        try {
            $company = $this->acceptInvitation->handle($invitation, $user);
        } catch (WorkspaceInvitationNotAcceptable $exception) {
            $company = $this->workspaceAlreadyJoinedBy($invitation, $user);

            if (! $company instanceof Company) {
                return $this->backToInvitation($token, $exception->getMessage());
            }
        } catch (WorkspaceInvitationEmailMismatch | WorkspaceInvitationEmailNotVerified $exception) {
            return $this->backToInvitation($token, $exception->getMessage());
        }

        $request->session()->forget(self::SESSION_TOKEN_KEY);

        Notification::make()
            ->title(__('invitation.flash.accepted', ['workspace' => $company->name]))
            ->success()
            ->send();

        return Inertia::location($this->workspaceUrl($company));
    }

    /**
     * Repeating an acceptance is not an error: if this same account already
     * closed this invitation and still belongs to the workspace, the outcome
     * the link promised is true and the only useful answer is the workspace.
     */
    private function workspaceAlreadyJoinedBy(CompanyInvitation $invitation, User $user): ?Company
    {
        $invitation->refresh();

        if (! $invitation->isAccepted() || $invitation->accepted_by_id !== $user->getKey()) {
            return null;
        }

        $company = $invitation->company()->first();

        if (! $company instanceof Company || $company->roleFor($user) === null) {
            return null;
        }

        return $company;
    }

    private function backToInvitation(string $token, string $message): RedirectResponse
    {
        return redirect()
            ->route('workspace-invitations.show', ['token' => $token])
            ->with('error', $message);
    }

    /**
     * @return array{state: string, workspace: null, inviter: null, invitedEmail: null, expiresAt: null, currentEmail: string|null, urls: array<string, string|null>, translations: mixed}
     */
    private function invalidProps(?User $user): array
    {
        return [
            'state' => 'invalid',
            'workspace' => null,
            'inviter' => null,
            'invitedEmail' => null,
            'expiresAt' => null,
            'currentEmail' => $user?->email,
            'urls' => $this->urls(),
            'translations' => __('invitation'),
        ];
    }

    /**
     * @return array{state: string, workspace: string|null, inviter: string|null, invitedEmail: string|null, expiresAt: string|null, currentEmail: string|null, urls: array<string, string|null>, translations: mixed}
     */
    private function props(string $state, CompanyInvitation $invitation, Company $company, ?User $user, string $token): array
    {
        $inviter = $invitation->invitedBy()->first();

        return [
            'state' => $state,
            'workspace' => $company->name,
            'inviter' => $inviter?->name,
            'invitedEmail' => $this->disclosableInvitedEmail($invitation, $user),
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'currentEmail' => $user?->email,
            'urls' => $this->urlsFor($state, $company, $token),
            'translations' => __('invitation'),
        ];
    }

    /**
     * The invited address is disclosed to the link holder, who needs to know
     * which identity to sign in or register with, but never to an account that
     * has just proven it is somebody else.
     */
    private function disclosableInvitedEmail(CompanyInvitation $invitation, ?User $user): ?string
    {
        if ($user === null) {
            return $invitation->email;
        }

        return CompanyInvitation::normalizeEmail($user->email) === CompanyInvitation::normalizeEmail($invitation->email)
            ? $invitation->email
            : null;
    }

    private function resolveState(CompanyInvitation $invitation, Company $company, ?User $user): string
    {
        $isMember = $user !== null && $company->roleFor($user) !== null;

        if ($invitation->isRevoked()) {
            return 'revoked';
        }

        if ($invitation->isExpired()) {
            return 'expired';
        }

        if ($isMember) {
            return 'already_member';
        }

        if ($invitation->isAccepted()) {
            return 'accepted';
        }

        if ($user === null) {
            return 'guest';
        }

        if (CompanyInvitation::normalizeEmail($user->email) !== CompanyInvitation::normalizeEmail($invitation->email)) {
            return 'email_mismatch';
        }

        if (! $user->hasVerifiedEmail()) {
            return 'email_unverified';
        }

        return 'acceptable';
    }

    /**
     * `redirect()->intended()` consumes the stored path, and Filament's login,
     * registration and email-verification responses all go through it. Storing
     * it on every render is what keeps sending the recipient back to the
     * invitation instead of to tenant registration, however many auth steps
     * they need on the way.
     */
    private function rememberReturnPath(Request $request, string $token): void
    {
        $request->session()->put('url.intended', route('workspace-invitations.show', ['token' => $token]));
        $request->session()->put(self::SESSION_TOKEN_KEY, $token);
    }

    /**
     * @return array<string, string|null>
     */
    private function urlsFor(string $state, Company $company, string $token): array
    {
        return match ($state) {
            'acceptable' => $this->urls(accept: route('workspace-invitations.accept', ['token' => $token])),
            'guest' => $this->urls(
                login: Filament::getLoginUrl(),
                register: Filament::getRegistrationUrl(),
            ),
            'email_unverified' => $this->urls(verify: Filament::getEmailVerificationPromptUrl()),
            'already_member' => $this->urls(workspace: $this->workspaceUrl($company)),
            default => $this->urls(),
        };
    }

    /**
     * @return array<string, string|null>
     */
    private function urls(
        ?string $accept = null,
        ?string $login = null,
        ?string $register = null,
        ?string $verify = null,
        ?string $workspace = null,
    ): array {
        return [
            'accept' => $accept,
            'login' => $login,
            'register' => $register,
            'verify' => $verify,
            'workspace' => $workspace,
        ];
    }

    private function workspaceUrl(Company $company): string
    {
        return Filament::getUrl($company) ?? url('/admin');
    }
}
