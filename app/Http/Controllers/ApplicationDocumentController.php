<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationDocumentController extends Controller
{
    public function show(
        Company $company,
        Application $application,
        ApplicationDocument $document,
    ): StreamedResponse {
        $this->authorizeDocument($company, $application, $document);

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
        );
    }

    public function download(
        Company $company,
        Application $application,
        ApplicationDocument $document,
    ): StreamedResponse {
        $this->authorizeDocument($company, $application, $document);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
        );
    }

    private function authorizeDocument(
        Company $company,
        Application $application,
        ApplicationDocument $document,
    ): void {
        abort_unless(
            $application->company_id === $company->getKey()
            && $document->company_id === $company->getKey()
            && $document->application_id === $application->getKey(),
            404,
        );

        Gate::authorize('view', $application);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
    }
}
