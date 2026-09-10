<?php

namespace App\Exceptions;

use App\Enums\CandidateImportRefusalCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * An import action the product refused to perform.
 *
 * Always the result of an invalid request — a second browser tab confirming a
 * review that has since moved on, a row being included while it still carries
 * an unanswered question — never a fault, so it is not reported. The code
 * travels with the exception so the caller can render it in the recruiter's
 * language; the message exists for logs and for developers.
 */
class CandidateImportRefused extends RuntimeException implements ShouldntReport
{
    /** @param array<string, int|string> $context */
    public function __construct(
        public readonly CandidateImportRefusalCode $refusalCode,
        public readonly array $context = [],
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : 'The import action was refused: '.$refusalCode->value.'.');
    }

    /** The refusal itself, which the caller renders in the recruiter's language. */
    public function refusalCode(): CandidateImportRefusalCode
    {
        return $this->refusalCode;
    }

    /** @param array<string, int|string> $context */
    public static function make(CandidateImportRefusalCode $code, array $context = [], string $message = ''): self
    {
        return new self($code, $context, $message);
    }

    public static function staleRevision(int $reviewed, int $current): self
    {
        return new self(
            CandidateImportRefusalCode::StaleRevision,
            ['reviewed' => $reviewed, 'current' => $current],
            'This import changed since it was reviewed; review it again before confirming.',
        );
    }

    public static function notReviewable(int $validated, int $current): self
    {
        return new self(
            CandidateImportRefusalCode::NotReviewable,
            ['validated' => $validated, 'current' => $current],
            'This import has to be validated again before it can be reviewed.',
        );
    }

    public static function unconfirmedBatchLimit(int $limit): self
    {
        return new self(
            CandidateImportRefusalCode::UnconfirmedBatchLimit,
            ['limit' => $limit],
            'This workspace already holds '.$limit.' unconfirmed imports. Confirm or discard one before starting another.',
        );
    }
}
