<?php

namespace App\Mail\Recruitment;

use App\Data\CandidateCommunicationEmailContext;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CandidateCommunicationMail extends RecruitmentMail
{
    public function __construct(public readonly CandidateCommunicationEmailContext $context) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->context->subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.recruitment.candidate-communication', with: ['body' => $this->context->body]);
    }
}
