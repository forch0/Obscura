<?php

namespace App\Mail;

use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessCodeShared extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $rawCode,
        public Workspace $workspace,
        public WorkspaceAccessCode $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been given access to an Obscura workspace',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-code-shared',
        );
    }
}
