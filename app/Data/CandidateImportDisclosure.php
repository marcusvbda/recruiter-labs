<?php

namespace App\Data;

/**
 * A contact value the file supplies that the import will deliberately not write.
 *
 * Reusing an existing candidate never edits that candidate: every stored
 * contact field is preserved, including the ones that are currently empty. When
 * the file disagrees with what is on record, the difference is not an update
 * waiting to be applied — it is something the recruiter is entitled to see
 * before confirming, and to act on themselves afterwards.
 */
final readonly class CandidateImportDisclosure
{
    public function __construct(
        /** Canonical header identifier of the differing field. */
        public string $field,
        /** The value the file supplies. */
        public ?string $supplied,
        /** The value already stored on the candidate; null when the field is empty on record. */
        public ?string $existing,
    ) {}

    /** @return array{field: string, supplied: string|null, existing: string|null} */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'supplied' => $this->supplied,
            'existing' => $this->existing,
        ];
    }
}
