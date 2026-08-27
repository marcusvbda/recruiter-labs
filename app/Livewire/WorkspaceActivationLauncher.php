<?php

namespace App\Livewire;

use App\Filament\Pages\Dashboard;
use App\Models\Company;
use App\Services\WorkspaceActivationJourney;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Compact floating access to the activation journey while the user navigates
 * the workspace. It reads the same {@see WorkspaceActivationJourney} the
 * Overview checklist reads, so the two surfaces can never disagree about what
 * is done or what to do next, and it performs no recruitment action of its
 * own — every link leads to an existing product flow.
 *
 * Rendered only from the panel render hook in `AdminPanelProvider`, which
 * already refuses to mount this component at all unless Filament has
 * resolved both a tenant and an authenticated user for the current request.
 * This component adds no second authorization path on top of that: it trusts
 * the company it was given and reads the current auth user the same way the
 * rest of the panel does.
 */
class WorkspaceActivationLauncher extends Component
{
    public Company $company;

    public bool $expanded = false;

    /** @var list<array{key: string, is_complete: bool, url: string|null}> */
    public array $primarySteps = [];

    public ?string $nextStepKey = null;

    public int $completedCount = 0;

    public int $totalCount = 0;

    public bool $isSetupComplete = false;

    public string $overviewUrl = '';

    /**
     * Whether this instance has anything left to show. False once the
     * workspace is activated (AC27) or this user has hidden the launcher —
     * either way the component renders nothing rather than an empty shell.
     */
    public bool $visible = false;

    public function mount(Company $company): void
    {
        $this->company = $company;
        $this->overviewUrl = Dashboard::getUrl(tenant: $company);

        $user = Auth::user();

        if ($user === null || $company->hasHiddenOnboardingLauncher($user)) {
            return;
        }

        $progress = app(WorkspaceActivationJourney::class)->for($company, $user);

        if ($progress->isActivated()) {
            return;
        }

        $this->primarySteps = $progress->primarySteps;
        $this->nextStepKey = $progress->nextStep()['key'] ?? null;
        $this->completedCount = $progress->completedCount();
        $this->totalCount = $progress->totalCount();
        $this->isSetupComplete = $progress->isSetupComplete();
        $this->visible = true;
    }

    public function toggle(): void
    {
        $this->expanded = ! $this->expanded;
    }

    /**
     * Hides the launcher for this user in this workspace only. Writes the
     * personal pivot timestamp alone — no milestone is touched and activation
     * state does not change (AC20).
     */
    public function hide(): void
    {
        $user = Auth::user();

        if ($user !== null) {
            $this->company->hideOnboardingLauncherFor($user);
        }

        $this->visible = false;
    }

    public function render(): View
    {
        return view('livewire.workspace-activation-launcher');
    }
}
