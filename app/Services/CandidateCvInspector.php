<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Normalizer;
use PhpOffice\PhpWord\Shared\XMLReader;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

class CandidateCvInspector
{
    /** Ceiling on what one document may decompress to while being inspected. */
    private const MAX_DECOMPRESSED_BYTES = 50 * 1048576;

    public function filename(string $name): string
    {
        $name = trim(Normalizer::normalize($name, Normalizer::FORM_C) ?: $name);
        if ($name === '' || mb_strlen($name) > 255 || preg_match('/[\\x00-\\x1f\\x7f\/\\\\:]/u', $name)
            || $name === '.' || $name === '..') {
            throw ValidationException::withMessages(['file' => 'Use a filename of at most 255 characters, without paths, URLs, or control characters.']);
        }

        return $name;
    }

    /** @return array{original_name: string, extension: string, mime_type: string, size: int, checksum: string} */
    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            $this->invalid();
        }
        $name = $this->filename($file->getClientOriginalName());
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = $file->getSize();
        if ($size === false || $size === 0 || $size > CandidateImportLimits::CV_BYTES) {
            throw ValidationException::withMessages(['file' => 'A CV must be nonempty and at most 10 MiB.']);
        }
        if (! in_array($extension, ['pdf', 'docx'], true)) {
            $this->invalid();
        }
        try {
            $this->read($file->getPathname(), $extension);
        } catch (Throwable) {
            $this->invalid();
        }
        $checksum = hash_file('sha256', $file->getPathname());
        if ($checksum === false) {
            $this->invalid();
        }

        return ['original_name' => $name, 'extension' => $extension, 'mime_type' => $extension === 'pdf'
            ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => $size, 'checksum' => $checksum];
    }

    /** Read document bytes only; never render active content or resolve document links. */
    public function read(string $path, string $extension): string
    {
        if ($extension === 'pdf') {
            $prefix = file_get_contents($path, false, null, 0, 5);
            if ($prefix !== '%PDF-') {
                $this->invalid();
            }
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(self::MAX_DECOMPRESSED_BYTES);
            $document = (new Parser([], $config))->parseFile($path);
            if (count($document->getPages()) === 0) {
                $this->invalid();
            }

            return $document->getText();
        }
        if ($extension !== 'docx') {
            $this->invalid();
        }

        return $this->readDocx($path);
    }

    private function readDocx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            $this->invalid();
        }
        try {
            $declared = 0;
            $extracted = 0;
            $names = [];
            $text = '';
            if ($zip->numFiles > 2000) {
                $this->invalid();
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry === false || $entry['encryption_method'] !== 0) {
                    $this->invalid();
                }
                $name = $entry['name'];
                $declared += $entry['size'];
                if ($declared > self::MAX_DECOMPRESSED_BYTES || isset($names[$name]) || str_contains($name, '..')
                    || str_starts_with($name, '/') || str_contains($name, '\\')
                    || preg_match('/(?:vbaProject|embeddings\/|activeX\/)/i', $name)) {
                    $this->invalid();
                }
                $names[$name] = true;
                if (! str_ends_with($name, '.xml') && ! str_ends_with($name, '.rels')) {
                    continue;
                }
                // Every size above is the archive's own declaration about itself.
                // libzip does stop at the declared size, so understating it only
                // truncates the entry into XML that then fails to parse, but the
                // read is capped explicitly rather than resting on that: never
                // more than the entry claims, and never past the budget left.
                $xml = $zip->getFromIndex($i, (int) min($entry['size'] + 1, self::MAX_DECOMPRESSED_BYTES - $extracted));
                if ($xml === false) {
                    $this->invalid();
                }
                $extracted += strlen($xml);
                if ($extracted > self::MAX_DECOMPRESSED_BYTES || preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $xml)) {
                    $this->invalid();
                }
                $dom = $this->xml($xml);
                if ($name === '[Content_Types].xml') {
                    $hasDocumentType = false;
                    foreach ($dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override') as $override) {
                        if ($override->getAttribute('PartName') === '/word/document.xml'
                            && $override->getAttribute('ContentType') === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml') {
                            $hasDocumentType = true;
                        }
                    }
                    if (! $hasDocumentType) {
                        $this->invalid();
                    }
                }
                if ($name === 'word/document.xml' && ($dom->documentElement?->localName !== 'document'
                    || $dom->documentElement->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main')) {
                    $this->invalid();
                }
                // PHPWord's XML reader reads literal text without constructing image,
                // link, or embedded-object elements that could resolve external targets.
                if (preg_match('#^word/(document|header\d+|footer\d+|footnotes|endnotes)\.xml$#', $name)) {
                    foreach ($dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't') as $node) {
                        $text .= $node->textContent.' ';
                    }
                }
            }
            if (! isset($names['[Content_Types].xml'], $names['_rels/.rels'], $names['word/document.xml'])) {
                $this->invalid();
            }

            return $text;
        } finally {
            $zip->close();
        }
    }

    private function xml(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = (new XMLReader)->getDomFromString($xml);
            if ($dom->documentElement === null || libxml_get_errors() !== []) {
                $this->invalid();
            }

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['file' => 'Supply a valid, unencrypted PDF or DOCX CV whose contents match its extension.']);
    }
}
