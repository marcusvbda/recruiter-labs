<?php

namespace App\Http\Controllers;

use App\Models\CandidateImportBatch;
use App\Models\Company;
use App\Services\CandidateImportReport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The two downloads an import leaves behind.
 *
 * Both are private: an import report holds candidate names and email
 * addresses, and a correction file holds the raw cells of the uploaded file,
 * so neither may ever be reachable by URL alone. They are served the way
 * {@see CandidateMaterialController} serves a CV — same disk, same headers,
 * always as an attachment, with the workspace boundary checked before a byte
 * is produced.
 *
 * Nothing is cached. The files are written to the private disk only because a
 * download is served from a disk, and they are removed once the response has
 * been sent: a report is a statement about the workspace as it is now, and a
 * copy left lying around would outlive the truth of it.
 */
class CandidateImportReportController extends Controller
{
    public function __construct(private readonly CandidateImportReport $reports) {}

    public function report(Company $company, CandidateImportBatch $candidateImportBatch): StreamedResponse
    {
        $this->authorizeBatch($company, $candidateImportBatch);

        return $this->respond(
            $this->reports->report($candidateImportBatch),
            $this->reports->reportFilename($candidateImportBatch),
        );
    }

    public function correction(Company $company, CandidateImportBatch $candidateImportBatch): StreamedResponse
    {
        $this->authorizeBatch($company, $candidateImportBatch);

        return $this->respond(
            $this->reports->correctionFile($candidateImportBatch),
            $this->reports->correctionFilename($candidateImportBatch),
        );
    }

    private function authorizeBatch(Company $company, CandidateImportBatch $batch): void
    {
        abort_unless($batch->company_id === $company->getKey(), 404);
        Gate::authorize('view', $batch);
    }

    /**
     * Hand one generated CSV to the browser and forget it.
     *
     * The temporary name carries no workspace or candidate information, and
     * the file is deleted after the response is sent rather than before, since
     * the disk is still being read while it streams.
     */
    private function respond(string $contents, string $filename): StreamedResponse
    {
        $disk = Storage::disk('candidate_materials');
        $path = 'import-reports/'.Str::uuid()->toString().'.csv';
        $disk->put($path, $contents);

        app()->terminating(function () use ($disk, $path): void {
            $disk->delete($path);
        });

        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0', 'Content-Security-Policy' => "sandbox; default-src 'none'"];

        return $disk->response($path, $filename, $headers, 'attachment');
    }
}
