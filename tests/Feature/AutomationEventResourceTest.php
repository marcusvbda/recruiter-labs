<?php

use App\Enums\AutomationActionType;
use App\Enums\AutomationEventType;
use App\Filament\Resources\AutomationEvents\Pages\CreateAutomationEvent;
use App\Filament\Resources\AutomationEvents\Pages\EditAutomationEvent;
use App\Filament\Resources\AutomationEvents\Pages\ListAutomationEvents;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Filament\Resources\Jobs\RelationManagers\AutomationEventsRelationManager;
use App\Models\AutomationEvent;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Job;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files. Any fixture
// belonging to a *different* company must be created before calling
// `actAsCompany()`: once the tenant is active, Filament's tenancy observer
// stamps every newly created owned model with the current tenant, which
// would silently overwrite a cross-tenant fixture's `company_id`.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('lists automation events belonging to the current tenant', function () {
    $company = Company::factory()->create();
    $otherEvent = AutomationEvent::factory()->create();

    actAsCompany($company);

    $event = AutomationEvent::factory()->for($company)->create();

    Livewire::test(ListAutomationEvents::class)
        ->assertCanSeeTableRecords([$event])
        ->assertCanNotSeeTableRecords([$otherEvent]);
});

it('creates an automation event with a send_email action and persists the email template id inside action_config', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $job = Job::factory()->for($company)->create();
    $emailTemplate = EmailTemplate::factory()->for($company)->create();

    Livewire::test(CreateAutomationEvent::class)
        ->fillForm([
            'automatable_type' => (new Job)->getMorphClass(),
            'automatable_id' => $job->id,
            'event_type' => AutomationEventType::ApplicationSubmitted->value,
            'action_type' => AutomationActionType::SendEmail->value,
            'action_config.email_template_id' => $emailTemplate->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = AutomationEvent::query()
        ->where('company_id', $company->id)
        ->where('event_type', AutomationEventType::ApplicationSubmitted->value)
        ->sole();

    expect($event->automatable_id)->toBe($job->id)
        ->and($event->automatable_type)->toBe((new Job)->getMorphClass())
        ->and($event->action_config)->toBe(['email_template_id' => $emailTemplate->id]);
});

it('requires the email template field when action type is send_email', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $job = Job::factory()->for($company)->create();

    Livewire::test(CreateAutomationEvent::class)
        ->fillForm([
            'automatable_type' => (new Job)->getMorphClass(),
            'automatable_id' => $job->id,
            'event_type' => AutomationEventType::ApplicationSubmitted->value,
            'action_type' => AutomationActionType::SendEmail->value,
            'action_config.email_template_id' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['action_config.email_template_id' => 'required']);
});

it('shows the email template select when action type is send_email', function () {
    // Note: `send_email` is currently the only `AutomationActionType` case,
    // so there is no alternative action type to contrast against yet. This
    // test only confirms the field is visible for the one existing action
    // type; it should be extended with a "hidden for other action types"
    // counterpart once a second `AutomationActionType` case is introduced.
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateAutomationEvent::class)
        ->set('data.action_type', AutomationActionType::SendEmail->value)
        ->assertFormFieldVisible('action_config.email_template_id');
});

it('scopes the automation events relation manager to the bound job', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $job = Job::factory()->for($company)->create();
    $ownEvent = AutomationEvent::factory()->for($company)->create();
    $ownEvent->automatable()->associate($job);
    $ownEvent->save();

    $otherJob = Job::factory()->for($company)->create();
    $otherEvent = AutomationEvent::factory()->for($company)->create();
    $otherEvent->automatable()->associate($otherJob);
    $otherEvent->save();

    Livewire::test(AutomationEventsRelationManager::class, ['ownerRecord' => $job, 'pageClass' => EditJob::class])
        ->assertCanSeeTableRecords([$ownEvent])
        ->assertCanNotSeeTableRecords([$otherEvent]);
});

it('creates an automation event through the job relation manager without a morph type selector', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $job = Job::factory()->for($company)->create();
    $emailTemplate = EmailTemplate::factory()->for($company)->create();

    Livewire::test(AutomationEventsRelationManager::class, ['ownerRecord' => $job, 'pageClass' => EditJob::class])
        ->callTableAction('create', data: [
            'event_type' => AutomationEventType::StatusChanged->value,
            'action_type' => AutomationActionType::SendEmail->value,
            'action_config.email_template_id' => $emailTemplate->id,
        ])
        ->assertHasNoTableActionErrors();

    $event = AutomationEvent::query()
        ->where('automatable_id', $job->id)
        ->where('event_type', AutomationEventType::StatusChanged->value)
        ->sole();

    expect($event->action_config)->toBe(['email_template_id' => $emailTemplate->id]);
});

it('edits an existing automation event', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $emailTemplate = EmailTemplate::factory()->for($company)->create();
    $event = AutomationEvent::factory()->for($company)->create([
        'action_config' => ['email_template_id' => $emailTemplate->id],
    ]);
    $event->automatable()->associate($job);
    $event->save();

    actAsCompany($company);

    Livewire::test(EditAutomationEvent::class, ['record' => $event->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->is_active)->toBeFalse();
});
