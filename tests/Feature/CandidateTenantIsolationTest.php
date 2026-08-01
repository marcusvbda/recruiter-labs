<?php

use App\Enums\PhoneCountry;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('changes the phone mask by country and stores the international number', function (PhoneCountry $country, string $phone, string $internationalPhone) {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);

    actAsCompany($company);

    Livewire::test(CreateCandidate::class)
        ->set('data.phone_country', $country->value)
        ->assertFormFieldExists('phone_country')
        ->assertFormFieldExists('phone', fn ($field): bool => $field instanceof TextInput
            && $field->getMask() instanceof RawJs
            && $field->getPrefixLabel() === $country->callingCode())
        ->fillForm([
            'name' => "{$country->value} Phone Candidate",
            'phone_country' => $country->value,
            'phone' => $phone,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Candidate::query()->where('name', "{$country->value} Phone Candidate")->sole()->phone)
        ->toBe($internationalPhone);
})->with([
    'Brazil' => [PhoneCountry::Brazil, '(12) 12121-2121', '+5512121212121'],
    'Ireland' => [PhoneCountry::Ireland, '87 123 4567', '+353871234567'],
    'United States' => [PhoneCountry::UnitedStates, '(415) 555-2671', '+14155552671'],
]);

it('hydrates the country and national number from a stored international phone', function () {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $candidate = Candidate::factory()->for($company)->create(['phone' => '+353871234567']);

    actAsCompany($company);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormSet([
            'phone_country' => PhoneCountry::Ireland->value,
            'phone' => '871234567',
        ]);
});

it('formats the phone by country in the candidates list', function (string $phone, string $formattedPhone) {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $candidate = Candidate::factory()->for($company)->create(['phone' => $phone]);

    actAsCompany($company);

    Livewire::test(ListCandidates::class)
        ->assertTableColumnFormattedStateSet('phone', $formattedPhone, $candidate);
})->with([
    'Brazil mobile' => ['+5512121212121', '+55 (12) 12121-2121'],
    'Brazil landline' => ['+551212121212', '+55 (12) 1212-1212'],
    'Ireland' => ['+353871234567', '+353 87 123 4567'],
    'United States' => ['+14155552671', '+1 (415) 555-2671'],
]);

it('does not let a user from one company view a candidate belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $candidateB = Candidate::factory()->for($companyB)->create();

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditCandidate::class, ['record' => $candidateB->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);
});

it('does not let a user from one company edit a candidate belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $candidateB = Candidate::factory()->for($companyB)->create(['name' => 'Original Name']);

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditCandidate::class, ['record' => $candidateB->getRouteKey()])
        ->set('data.name', 'Tampered Name')
        ->call('save'))
        ->toThrow(ModelNotFoundException::class);

    expect($candidateB->fresh()->name)->toBe('Original Name');
});

it('scopes the candidates list to the current tenant only', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $otherCandidate = Candidate::factory()->for($companyB)->create();

    actAsCompany($companyA);

    $ownCandidate = Candidate::factory()->for($companyA)->create();

    Livewire::test(ListCandidates::class)
        ->assertCanSeeTableRecords([$ownCandidate])
        ->assertCanNotSeeTableRecords([$otherCandidate])
        ->assertCountTableRecords(1);
});
