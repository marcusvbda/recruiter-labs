<?php

namespace App\Http\Controllers;

use App\Services\CandidateImportCsvReader;
use Symfony\Component\HttpFoundation\Response;

/**
 * The static CSV template recruiters download before starting an import.
 *
 * Its two example rows are clearly fabricated — invented names, invented
 * emails, no `cv_filename` a real upload would ever match — so nobody can
 * mistake them for real candidate data, and they can never be imported by
 * accident: they are never associated with any batch.
 */
class CandidateImportTemplateController extends Controller
{
    public function download(): Response
    {
        $headers = CandidateImportCsvReader::HEADERS;

        $rows = [
            $headers,
            ['Ada Example', 'ada.example@example.com', '+15551230001', 'https://www.linkedin.com/in/ada-example', 'ada-example-cv.pdf', '2024-01-15', 'Referral'],
            ['Grace Example', 'grace.example@example.com', '+15551230002', '', '', '2024-02-01', 'Job board'],
        ];

        $contents = implode("\r\n", array_map(
            fn (array $row): string => implode(',', array_map(fn (string $value): string => str_contains($value, ',') ? '"'.$value.'"' : $value, $row)),
            $rows,
        ))."\r\n";

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="candidate-import-template.csv"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
