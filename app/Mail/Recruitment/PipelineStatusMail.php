<?php

namespace App\Mail\Recruitment;

use App\Data\StatusEmailContext;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PipelineStatusMail extends RecruitmentMail
{
    use Queueable, SerializesModels;

    public function __construct(public readonly StatusEmailContext $context) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->context->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.recruitment.pipeline-status',
            with: [
                'body' => $this->context->body,
            ],
        );
    }
}
