<?php

namespace App\Data;

use App\Enums\EmailCredentialStatus;

class EmailCredentialTestResultData
{
    public function __construct(
        public readonly bool $success,
        public readonly EmailCredentialStatus $status,
        public readonly string $messageKey,
    ) {}

    /** @return array{success: bool, status: string, message_key: string} */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status->value,
            'message_key' => $this->messageKey,
        ];
    }
}
