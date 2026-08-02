<?php

use App\Enums\ApplicationDocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Storage::fake('local');
});

it('allows a company member to view and download a private application document', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create();
    $path = "companies/{$company->id}/applications/{$application->id}/cv/resume.pdf";
    Storage::disk('local')->put($path, 'private resume contents');
    $document = createApplicationDocument($company, $application, $path);
    $user = User::factory()->create();
    $user->companies()->attach($company);

    $view = $this->actingAs($user)->get(route('application-documents.view', [
        'company' => $company,
        'application' => $application,
        'document' => $document,
    ]));

    $view->assertSuccessful();
    expect($view->headers->get('content-disposition'))->toContain('inline')
        ->and($view->streamedContent())->toBe('private resume contents');

    $download = $this->actingAs($user)->get(route('application-documents.download', [
        'company' => $company,
        'application' => $application,
        'document' => $document,
    ]));

    $download->assertSuccessful()->assertDownload('resume.pdf');
    expect($download->streamedContent())->toBe('private resume contents');
});

it('denies a document to a user outside the application company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $application = Application::factory()->for($company)->create();
    $path = "companies/{$company->id}/applications/{$application->id}/cv/resume.pdf";
    Storage::disk('local')->put($path, 'private resume contents');
    $document = createApplicationDocument($company, $application, $path);
    $foreignUser = User::factory()->create();
    $foreignUser->companies()->attach($otherCompany);

    $this->actingAs($foreignUser)
        ->get(route('application-documents.download', [
            'company' => $company,
            'application' => $application,
            'document' => $document,
        ]))
        ->assertForbidden();
});

it('does not resolve applications or documents under a mismatched tenant path', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $application = Application::factory()->for($company)->create();
    $otherApplication = Application::factory()->for($company)->create();
    $path = "companies/{$company->id}/applications/{$application->id}/cv/resume.pdf";
    Storage::disk('local')->put($path, 'private resume contents');
    $document = createApplicationDocument($company, $application, $path);
    $user = User::factory()->create();
    $user->companies()->attach([$company->id, $otherCompany->id]);

    $this->actingAs($user)
        ->get(route('application-documents.view', [
            'company' => $otherCompany,
            'application' => $application,
            'document' => $document,
        ]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('application-documents.view', [
            'company' => $company,
            'application' => $otherApplication,
            'document' => $document,
        ]))
        ->assertNotFound();
});

it('does not return metadata for a missing private file', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create();
    $document = createApplicationDocument(
        $company,
        $application,
        "companies/{$company->id}/applications/{$application->id}/cv/missing.pdf",
    );
    $user = User::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->get(route('application-documents.view', [
            'company' => $company,
            'application' => $application,
            'document' => $document,
        ]))
        ->assertNotFound();
});

function createApplicationDocument(
    Company $company,
    Application $application,
    string $path,
): ApplicationDocument {
    return ApplicationDocument::query()->create([
        'company_id' => $company->id,
        'application_id' => $application->id,
        'type' => ApplicationDocumentType::Cv,
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'resume.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 23,
        'checksum' => hash('sha256', 'private resume contents'),
        'uploaded_at' => now(),
    ]);
}
