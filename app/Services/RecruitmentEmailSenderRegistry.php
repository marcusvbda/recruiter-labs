<?php

namespace App\Services;

use App\Contracts\RecruitmentEmailSender;
use App\Enums\EmailProvider;
use InvalidArgumentException;

class RecruitmentEmailSenderRegistry
{
    /** @var array<string, RecruitmentEmailSender> */
    private array $senders = [];

    /** @param iterable<RecruitmentEmailSender> $senders */
    public function __construct(iterable $senders)
    {
        foreach ($senders as $sender) {
            $this->senders[$sender->provider()->value] = $sender;
        }
    }

    public function sender(EmailProvider $provider): RecruitmentEmailSender
    {
        return $this->senders[$provider->value]
            ?? throw new InvalidArgumentException("No recruitment email sender is registered for [{$provider->value}].");
    }
}
