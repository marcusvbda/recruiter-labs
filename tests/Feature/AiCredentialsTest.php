<?php

use App\Actions\ChangeCompanyPlan;
use App\Actions\RemoveCompanyAiCredentials;
use App\Actions\TestCompanyAiCredentials;
use App\Actions\UpdateCompanyAiSettings;
use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Exceptions\PlanFeatureUnavailableException;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\CompanyAuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\AiCredentialsResolver;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

function configureOwnAiForTest(Company $company, User $user, string $apiKey = 'sk-test-secret-1234'): CompanyAiSetting
{
    return app(UpdateCompanyAiSettings::class)->run(
        company: $company,
        changedBy: $user,
        provider: AiProvider::Own,
        model: 'gpt-4o-mini',
        apiKey: $apiKey,
    );
}

it('stores an own OpenAI key encrypted and only exposes its masked representation', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'pro')->sole()->id,
    ]);
    $user = User::factory()->create();
    $company->users()->attach($user);

    $setting = configureOwnAiForTest($company, $user);
    $storedValue = DB::table('company_ai_settings')
        ->where('company_id', $company->id)
        ->value('openai_api_key');

    expect($storedValue)->not->toBe('sk-test-secret-1234')
        ->and($storedValue)->not->toContain('sk-test-secret-1234')
        ->and($setting->maskedKey())->toBe('sk-••••••••••••1234')
        ->and($setting->credential_status)->toBe(AiCredentialStatus::PendingValidation);
});

it('rejects own credentials on an incompatible plan in the backend', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    $user = User::factory()->create();
    $company->users()->attach($user);

    expect(fn () => configureOwnAiForTest($company, $user))
        ->toThrow(PlanFeatureUnavailableException::class);

    expect(CompanyAiSetting::query()->whereBelongsTo($company)->exists())->toBeFalse();
});

it('rejects credential changes from a user outside the company', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'pro')->sole()->id,
    ]);

    expect(fn () => configureOwnAiForTest($company, User::factory()->create()))
        ->toThrow(AuthorizationException::class);

    expect(CompanyAiSetting::query()->whereBelongsTo($company)->exists())->toBeFalse();
});

it('validates a working OpenAI key without making a real HTTP request', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => []]),
    ]);
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'pro')->sole()->id,
    ]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    configureOwnAiForTest($company, $user);

    $result = app(TestCompanyAiCredentials::class)->run($company, $user);
    $setting = $company->fresh()->aiSetting;

    expect($result->success)->toBeTrue()
        ->and($setting->credential_status)->toBe(AiCredentialStatus::Active)
        ->and($setting->validated_at)->not->toBeNull();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk-test-secret-1234')
        && str_starts_with($request->url(), 'https://api.openai.com/')
    );
});

it('marks invalid OpenAI credentials without leaking the key', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'error' => ['message' => 'Incorrect API key provided'],
        ], 401),
    ]);
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'pro')->sole()->id,
    ]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    configureOwnAiForTest($company, $user);

    $result = app(TestCompanyAiCredentials::class)->run($company, $user);
    $setting = $company->fresh()->aiSetting;

    expect($result->success)->toBeFalse()
        ->and($setting->credential_status)->toBe(AiCredentialStatus::Invalid)
        ->and(app(AiCredentialsResolver::class)->resolve($company->fresh())->provider)->toBe(AiProvider::Platform)
        ->and(json_encode($result))->not->toContain('sk-test-secret-1234')
        ->and(CompanyAuditLog::query()->get()->toJson())->not->toContain('sk-test-secret-1234');
});

it('replaces and removes an own key without retaining plaintext in audit records', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'pro')->sole()->id,
    ]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    configureOwnAiForTest($company, $user, 'sk-old-secret-1111');

    $replacement = configureOwnAiForTest($company, $user, 'sk-new-secret-9876');

    expect($replacement->maskedKey())->toBe('sk-••••••••••••9876')
        ->and($replacement->credential_status)->toBe(AiCredentialStatus::PendingValidation)
        ->and(CompanyAiSetting::query()->whereBelongsTo($company)->count())->toBe(1)
        ->and(CompanyAuditLog::query()->get()->toJson())->not->toContain('sk-old-secret-1111')
        ->and(CompanyAuditLog::query()->get()->toJson())->not->toContain('sk-new-secret-9876');

    $removed = app(RemoveCompanyAiCredentials::class)->run($company, $user);

    expect($removed->openai_api_key)->toBeNull()
        ->and($removed->provider)->toBe(AiProvider::Platform)
        ->and($removed->credential_status)->toBe(AiCredentialStatus::NotConfigured);
});

it('retains an own key on downgrade and resolves it again after upgrading', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => []]),
    ]);
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $company = Company::factory()->create(['plan_id' => $pro->id]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    configureOwnAiForTest($company, $user);
    app(TestCompanyAiCredentials::class)->run($company, $user);

    app(ChangeCompanyPlan::class)->run($company, $starter, $user);

    $downgraded = app(AiCredentialsResolver::class)->resolve($company->fresh());

    expect($company->fresh()->aiSetting->openai_api_key)->not->toBeNull()
        ->and($downgraded->provider)->toBe(AiProvider::Platform)
        ->and($downgraded->usesOwnKey)->toBeFalse();

    app(ChangeCompanyPlan::class)->run($company->fresh(), $pro, $user);

    $upgraded = app(AiCredentialsResolver::class)->resolve($company->fresh());

    expect($upgraded->provider)->toBe(AiProvider::Own)
        ->and($upgraded->usesOwnKey)->toBeTrue()
        ->and($upgraded->apiKey())->toBe('sk-test-secret-1234')
        ->and(json_encode($upgraded))->not->toContain('sk-test-secret-1234');
});

it('never resolves another company own key', function () {
    config(['services.openai.api_key' => 'sk-platform-secret']);
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => []]),
    ]);
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $company = Company::factory()->create(['plan_id' => $pro->id]);
    $otherCompany = Company::factory()->create(['plan_id' => $pro->id]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    configureOwnAiForTest($company, $user);
    app(TestCompanyAiCredentials::class)->run($company, $user);

    $configuration = app(AiCredentialsResolver::class)->resolve($otherCompany);

    expect($configuration->provider)->toBe(AiProvider::Platform)
        ->and($configuration->usesOwnKey)->toBeFalse()
        ->and($configuration->apiKey())->toBe('sk-platform-secret')
        ->and(json_encode($configuration))->not->toContain('sk-platform-secret');
});

it('authorizes testing and removal against the same tenant membership boundary', function () {
    Http::preventStrayRequests();
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'pro')->sole()->id,
    ]);
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $company->users()->attach($owner);
    configureOwnAiForTest($company, $owner);

    expect(fn () => app(TestCompanyAiCredentials::class)->run($company, $outsider))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(RemoveCompanyAiCredentials::class)->run($company, $outsider))
        ->toThrow(AuthorizationException::class);

    expect($company->fresh()->aiSetting->openai_api_key)->toBe('sk-test-secret-1234')
        ->and($company->fresh()->aiSetting->credential_status)->toBe(AiCredentialStatus::PendingValidation);

    Http::assertNothingSent();
});
