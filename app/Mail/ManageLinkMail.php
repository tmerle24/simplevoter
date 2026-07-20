<?php

namespace App\Mail;

use App\Models\Poll;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManageLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Poll $poll)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Verwaltungs-Link für "'.$this->poll->question.'"',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.manage-link',
            with: [
                'question' => $this->poll->question,
                'manageUrl' => url('/p/'.$this->poll->manage_token.'/edit'),
            ],
        );
    }
}
