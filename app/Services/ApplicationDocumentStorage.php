<?php

namespace App\Services;

use App\Enums\ApplicationDocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ApplicationDocumentStorage
{
    public function store(
        Application $application,
        UploadedFile $file,
        ApplicationDocumentType $type,
    ): ApplicationDocument {
        if (! $file->isValid()) {
            throw new RuntimeException('The application document upload is not valid.');
        }

        $disk = (string) config('filesystems.application_documents_disk', 'local');
        $extension = Str::lower($file->getClientOriginalExtension());
        $directory = "companies/{$application->company_id}/applications/{$application->getKey()}/{$type->value}";
        $filename = Str::uuid().'.'.$extension;
        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            throw new RuntimeException('The application document checksum could not be calculated.');
        }

        $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);

        if (! is_string($path)) {
            throw new RuntimeException('The application document could not be stored.');
        }

        try {
            return $application->documents()->create([
                'company_id' => $application->company_id,
                'type' => $type,
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => Str::limit($file->getMimeType() ?? 'application/octet-stream', 150, ''),
                'extension' => $extension,
                'size' => $file->getSize(),
                'checksum' => $checksum,
                'uploaded_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    /** @param iterable<ApplicationDocument> $documents */
    public function deleteStored(iterable $documents): void
    {
        foreach ($documents as $document) {
            Storage::disk($document->disk)->delete($document->path);
        }
    }
}
