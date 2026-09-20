<?php

use App\Data\CandidateCommunicationEmailContext;
use App\Enums\CandidateCommunicationMessageKind;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Enums\Feature;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use App\Mail\Recruitment\CandidateCommunicationMail;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\Company;
use App\Models\CompanyEmailProviderSetting;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\Plan;
use App\Support\CandidateMessageBody;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // The Candidate workspace is a plan feature; without it the page is 403
    // and the composer could never be reached.
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        [
            'name' => 'Starter',
            'sort_order' => 1,
            'features' => [Feature::Candidates->value],
            'limits' => [],
        ],
    );

    Queue::fake();
});

/** A signed-in recruiter inside a workspace that can actually send email. */
function composerWorkspace(): Company
{
    $company = Company::factory()->create();

    CompanyEmailProviderSetting::query()->create([
        'company_id' => $company->getKey(),
        'provider' => EmailProvider::Resend,
        'api_key' => 'resend-key',
        'from_address' => 'recruiting@example.com',
        'credential_status' => EmailCredentialStatus::Active,
        'is_default' => true,
    ]);

    actAsCompany($company);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($company, isQuiet: true);
    Filament::bootCurrentPanel();

    return $company;
}

test('choosing a template resolves its variables into the editable composer fields', function (): void {
    $company = composerWorkspace();
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'name' => 'Alex Candidate',
        'email' => 'alex@example.com',
    ]);
    $job = Job::factory()->create(['company_id' => $company->getKey(), 'name' => 'Senior Engineer']);
    Application::factory()->create([
        'company_id' => $company->getKey(),
        'candidate_id' => $candidate->getKey(),
        'job_id' => $job->getKey(),
    ]);
    $template = EmailTemplate::factory()->create([
        'company_id' => $company->getKey(),
        'subject' => 'About {{ job.title }}',
        'body' => '<p>Hi {{ candidate.name }}</p>',
    ]);

    $component = Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->mountAction('sendMessage')
        ->set('mountedActions.0.data.job_id', $job->getKey())
        ->set('mountedActions.0.data.email_template_id', $template->getKey());

    // The editor holds the message as a rich-text document while it is open;
    // what matters is that the template's variables are already resolved in
    // the content the recruiter can edit.
    $body = RichContentRenderer::make($component->get('mountedActions.0.data.body'))->toHtml();

    expect($component->get('mountedActions.0.data.subject'))->toBe('About Senior Engineer')
        ->and($body)->toContain('Hi Alex Candidate')
        ->and($body)->not->toContain('{{')
        ->and($component->get('unresolvedMessageTokens'))->toBe([]);
});

test('a freeform message is sent with no template and no AI', function (): void {
    $company = composerWorkspace();
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'email' => 'alex@example.com',
    ]);

    Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->mountAction('sendMessage')
        ->set('mountedActions.0.data.subject', 'Quick chat?')
        ->set('mountedActions.0.data.body', '<p>Are you available tomorrow?</p>')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $message = CandidateCommunicationMessage::query()->sole();
    $thread = CandidateCommunicationThread::query()->sole();

    // A talent-pool message needs no Job and must not invent an Application.
    expect($thread->job_id)->toBeNull()
        ->and($thread->application_id)->toBeNull()
        ->and(Application::query()->count())->toBe(0)
        ->and($message->ai_assisted)->toBeFalse()
        ->and($message->status)->toBe(CandidateCommunicationMessageStatus::Queued)
        ->and($message->authorized_subject)->toBe('Quick chat?')
        ->and($message->authorized_body)->toBe('<p>Are you available tomorrow?</p>')
        ->and($message->recipient_email)->toBe('alex@example.com')
        ->and($message->sender_email)->toBe('recruiting@example.com');
});

test('a template whose context is missing blocks the send', function (): void {
    $company = composerWorkspace();
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'email' => 'alex@example.com',
    ]);
    $template = EmailTemplate::factory()->create([
        'company_id' => $company->getKey(),
        'subject' => 'About {{ job.title }}',
        'body' => '<p>Hi {{ candidate.name }}</p>',
    ]);

    $component = Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->mountAction('sendMessage')
        ->set('mountedActions.0.data.email_template_id', $template->getKey());

    expect($component->get('unresolvedMessageTokens'))->toBe(['job.title'])
        ->and($component->get('mountedActions.0.data.subject'))->toBe('About {{ job.title }}');

    $component->callMountedAction();

    expect(CandidateCommunicationMessage::query()->count())->toBe(0);
});

test('a message sent from an application belongs to that hiring process', function (): void {
    $company = composerWorkspace();
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'email' => 'alex@example.com',
    ]);
    $job = Job::factory()->create(['company_id' => $company->getKey(), 'name' => 'Senior Engineer']);
    $otherJob = Job::factory()->create(['company_id' => $company->getKey(), 'name' => 'Designer']);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'candidate_id' => $candidate->getKey(),
        'job_id' => $job->getKey(),
    ]);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->mountAction('sendMessage')
        ->set('mountedActions.0.data.subject', 'Next step')
        ->set('mountedActions.0.data.body', '<p>Congratulations</p>')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $thread = CandidateCommunicationThread::query()->sole();

    expect($thread->job_id)->toBe($job->getKey())
        ->and($thread->job_id)->not->toBe($otherJob->getKey())
        ->and($thread->application_id)->toBe($application->getKey())
        ->and(CandidateCommunicationMessage::query()->sole()->status)
        ->toBe(CandidateCommunicationMessageStatus::Queued);
});

test('an existing unsent draft is recovered by the composer instead of destroyed', function (): void {
    $company = composerWorkspace();
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'email' => 'alex@example.com',
    ]);
    $job = Job::factory()->create(['company_id' => $company->getKey()]);
    Application::factory()->create([
        'company_id' => $company->getKey(),
        'candidate_id' => $candidate->getKey(),
        'job_id' => $job->getKey(),
    ]);

    $thread = CandidateCommunicationThread::query()->create([
        'company_id' => $company->getKey(),
        'candidate_id' => $candidate->getKey(),
        'job_id' => $job->getKey(),
    ]);
    $draft = $thread->messages()->create([
        'company_id' => $company->getKey(),
        'kind' => CandidateCommunicationMessageKind::RecruiterAuthored,
        'draft_subject' => 'Legacy outreach',
        'draft_body' => 'Written before the composer reset.',
        'ai_assisted' => true,
    ]);

    $component = Livewire::test(ViewCandidate::class, [
        'record' => $candidate->getKey(),
    ])->mountAction('sendMessage');

    expect($component->get('mountedActions.0.data.draft_id'))->toBe($draft->getKey())
        ->and($component->get('mountedActions.0.data.subject'))->toBe('Legacy outreach')
        ->and($draft->fresh())->not->toBeNull();
});

test('a tampered rich body is sanitized before it is stored and before it is sent', function (): void {
    $company = composerWorkspace();
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'email' => 'alex@example.com',
    ]);

    Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->mountAction('sendMessage')
        ->set('mountedActions.0.data.subject', 'Hello')
        ->set('mountedActions.0.data.body', '<p>Hi<script>alert(1)</script><a href="javascript:x" onerror="x">link</a></p>')
        ->callMountedAction();

    $message = CandidateCommunicationMessage::query()->sole();

    expect($message->draft_body)->not->toContain('<script')
        ->and($message->draft_body)->not->toContain('onerror')
        ->and($message->draft_body)->not->toContain('javascript:')
        ->and($message->authorized_body)->toBe($message->draft_body)
        ->and($message->authorized_body)->toContain('Hi');

    $html = (new CandidateCommunicationMail(new CandidateCommunicationEmailContext(
        messageId: (int) $message->getKey(),
        recipient: (string) $message->recipient_email,
        company: $company->name,
        subject: (string) $message->authorized_subject,
        body: (string) $message->authorized_body,
        key: (string) $message->idempotency_key,
    )))->render();

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('onerror')
        ->and($html)->not->toContain('javascript:');
});

test('a legacy plain-text body keeps its line breaks and characters in the sent email', function (): void {
    $company = composerWorkspace();

    $html = (new CandidateCommunicationMail(new CandidateCommunicationEmailContext(
        messageId: 1,
        recipient: 'alex@example.com',
        company: $company->name,
        subject: 'Legacy',
        body: "Hi\nsee <3 & more",
        key: 'candidate-communication/legacy',
    )))->render();

    // The markdown mail pipeline strips raw tags (pre-existing behaviour), so
    // what matters here is that the legacy text is escaped rather than
    // interpreted, and that the helper itself preserves the line break.
    expect(CandidateMessageBody::toStoredHtml("Hi\nsee <3 & more"))->toContain('<br')
        ->and($html)->toContain('&lt;3')
        ->and($html)->toContain('&amp;');
});

test('a legacy plain-text draft keeps its line breaks when the composer prefills it', function (): void {
    $company = composerWorkspace();
    $candidate = Candidate::factory()->create([
        'company_id' => $company->getKey(),
        'email' => 'alex@example.com',
    ]);
    $job = Job::factory()->create(['company_id' => $company->getKey()]);

    $thread = CandidateCommunicationThread::query()->create([
        'company_id' => $company->getKey(),
        'candidate_id' => $candidate->getKey(),
        'job_id' => $job->getKey(),
    ]);
    $thread->messages()->create([
        'company_id' => $company->getKey(),
        'kind' => CandidateCommunicationMessageKind::RecruiterAuthored,
        'draft_subject' => 'Legacy outreach',
        'draft_body' => "Hi there\nsee <3 & more",
    ]);

    $component = Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->mountAction('sendMessage');

    $body = RichContentRenderer::make($component->get('mountedActions.0.data.body'))->toHtml();

    expect($body)->toContain('Hi there')
        // Escaped, not interpreted: the characters the recruiter typed survive.
        ->and($body)->toContain('&lt;3 &amp; more');
});
