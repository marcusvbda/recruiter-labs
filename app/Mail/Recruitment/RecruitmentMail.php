<?php

namespace App\Mail\Recruitment;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

abstract class RecruitmentMail extends Mailable
{
    abstract public function envelope(): Envelope;

    abstract public function content(): Content;
}
