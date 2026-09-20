<?php

use App\Enums\Feature;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function doNotContactCandidate(): Candidate
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [Feature::Candidates->value], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->create(['company_id' => $company->getKey()]);
    $application = Application::factory()->create(['company_id' => $company->getKey(), 'job_id' => $job->getKey()]);

    actAsCompany($company);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($company, isQuiet: true);
    Filament::bootCurrentPanel();

    return $application->candidate;
}

test('marking a candidate as do-not-contact updates the action on the same page, without a reload', function (): void {
    $candidate = doNotContactCandidate();

    Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->assertActionHasLabel('toggleDoNotContact', __('communications.actions.do_not_contact'))
        ->callAction('toggleDoNotContact')
        ->assertActionHasLabel('toggleDoNotContact', __('communications.actions.allow_contact'));

    expect($candidate->fresh()->isDoNotContact())->toBeTrue();
});

test('the same action toggles back on the second click instead of repeating the first one', function (): void {
    $candidate = doNotContactCandidate();

    Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->callAction('toggleDoNotContact')
        ->callAction('toggleDoNotContact')
        ->assertActionHasLabel('toggleDoNotContact', __('communications.actions.do_not_contact'));

    expect($candidate->fresh()->isDoNotContact())->toBeFalse();
});
