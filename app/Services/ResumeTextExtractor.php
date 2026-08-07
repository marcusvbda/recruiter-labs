<?php

namespace App\Services;

use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class ResumeTextExtractor
{
    private const MAX_CHARACTERS = 6000;

    public function extract(ApplicationDocument $document): ?string
    {
        $extension = Str::lower($document->extension);

        if (! in_array($extension, ['pdf', 'docx'], strict: true)) {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'resume-');

        if ($temporaryPath === false) {
            return null;
        }

        try {
            $contents = Storage::disk($document->disk)->get($document->path);

            if ($contents === null) {
                return null;
            }

            file_put_contents($temporaryPath, $contents);

            $text = $extension === 'pdf'
                ? $this->extractPdf($temporaryPath)
                : $this->extractDocx($temporaryPath);

            $text = trim((string) preg_replace('/\s+/', ' ', $text ?? ''));

            return $text === '' ? null : Str::limit($text, self::MAX_CHARACTERS, '');
        } catch (Throwable $exception) {
            Log::warning('Resume text extraction failed.', [
                'application_document_id' => $document->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function extractPdf(string $path): ?string
    {
        return (new PdfParser)->parseFile($path)->getText();
    }

    private function extractDocx(string $path): string
    {
        $phpWord = IOFactory::load($path);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $value = $element->getText();
                    $text .= (is_string($value) ? $value : '').' ';
                }
            }
        }

        return $text;
    }
}
