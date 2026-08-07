<?php

use App\Models\ApplicationDocument;
use App\Services\ResumeTextExtractor;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Builds a minimal, valid, uncompressed PDF containing the given text, with a
 * correct xref table (byte offsets computed here) so smalot/pdfparser can read
 * it without a real PDF-authoring tool. Generated in code rather than pasted
 * as a raw fixture, since PDF xref offsets are exact-byte-sensitive and a raw
 * fixture would be one accidental whitespace edit away from becoming invalid.
 */
function minimalPdfWithText(string $text): string
{
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[3] = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 300 150] /Contents 5 0 R >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $stream = "BT /F1 18 Tf 20 100 Td ({$text}) Tj ET";
    $objects[5] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n".sprintf("%010d %05d f \n", 0, 65535);

    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d %05d n \n", $offsets[$i], 0);
    }

    return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
}

it('extracts plain text from a PDF resume', function () {
    Storage::fake('local');
    Storage::disk('local')->put('resume.pdf', minimalPdfWithText('Hello Resume'));
    $document = new ApplicationDocument(['disk' => 'local', 'path' => 'resume.pdf', 'extension' => 'pdf']);

    expect(app(ResumeTextExtractor::class)->extract($document))->toBe('Hello Resume');
});

it('extracts plain text from a DOCX resume', function () {
    Storage::fake('local');
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Senior Laravel Engineer');
    $section->addText('5 years of experience with PHP and React.');
    $temporaryPath = tempnam(sys_get_temp_dir(), 'docx-');
    IOFactory::createWriter($phpWord, 'Word2007')->save($temporaryPath);
    Storage::disk('local')->put('resume.docx', (string) file_get_contents($temporaryPath));
    unlink($temporaryPath);
    $document = new ApplicationDocument(['disk' => 'local', 'path' => 'resume.docx', 'extension' => 'docx']);

    $text = app(ResumeTextExtractor::class)->extract($document);

    expect($text)->toContain('Senior Laravel Engineer')
        ->toContain('5 years of experience with PHP and React.');
});

it('returns null without throwing for an unsupported extension', function () {
    Storage::fake('local');
    Storage::disk('local')->put('resume.txt', 'plain text resume');
    $document = new ApplicationDocument(['disk' => 'local', 'path' => 'resume.txt', 'extension' => 'txt']);

    expect(app(ResumeTextExtractor::class)->extract($document))->toBeNull();
});

it('returns null without throwing for a corrupt PDF', function () {
    Storage::fake('local');
    Storage::disk('local')->put('resume.pdf', 'not a real pdf');
    $document = new ApplicationDocument(['disk' => 'local', 'path' => 'resume.pdf', 'extension' => 'pdf']);

    expect(app(ResumeTextExtractor::class)->extract($document))->toBeNull();
});
