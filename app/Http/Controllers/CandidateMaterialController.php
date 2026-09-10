<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateMaterial;
use App\Models\Company;
use App\Services\CandidateMaterialPreparation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateMaterialController extends Controller
{
    public function __construct(private readonly CandidateMaterialPreparation $preparation) {}

    public function show(Company $company, Candidate $candidate, CandidateMaterial $material): StreamedResponse
    {
        return $this->respond($company, $candidate, $material, $material->extension !== 'pdf');
    }

    public function download(Company $company, Candidate $candidate, CandidateMaterial $material): StreamedResponse
    {
        return $this->respond($company, $candidate, $material, true);
    }

    private function respond(Company $company, Candidate $candidate, CandidateMaterial $material, bool $download): StreamedResponse
    {
        abort_unless($candidate->company_id === $company->getKey()
            && $material->company_id === $company->getKey() && $material->candidate_id === $candidate->getKey(), 404);
        Gate::authorize('view', $material);
        if ($material->disk !== 'candidate_materials' || $material->path === null || ! Storage::disk('candidate_materials')->exists($material->path)) {
            $this->preparation->markFileUnavailable($company->getKey(), $material->getKey());
            abort(404);
        }
        $headers = ['Content-Type' => $material->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0', 'Content-Security-Policy' => "sandbox; default-src 'none'"];

        return Storage::disk('candidate_materials')->response($material->path, $material->original_name, $headers, $download ? 'attachment' : 'inline');
    }
}
