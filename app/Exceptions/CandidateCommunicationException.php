<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class CandidateCommunicationException extends RuntimeException implements ShouldntReport
{
    public static function crossTenantContext(): self
    {
        return new self('Candidate communication records must belong to one workspace.');
    }

    public static function candidateHasNoValidEmail(): self
    {
        return new self('This candidate does not have a valid email address.');
    }

    public static function candidateIsDoNotContact(): self
    {
        return new self('This candidate is marked do not contact.');
    }

    public static function providerUnavailable(): self
    {
        return new self('This workspace does not have a usable default email provider.');
    }

    public static function alreadyAuthorized(): self
    {
        return new self('An authorized communication snapshot cannot be changed.');
    }

    public static function draftCannotBeEdited(): self
    {
        return new self('Only an unauthorised draft can be edited.');
    }
}
