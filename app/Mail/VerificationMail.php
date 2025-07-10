<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $merchant;
    public $code;
    public $hasUppercase;

   /**
	 * Create a new message instance.
	 *
	 * @return void
	 */
    public function __construct($merchant, $code, $hasUppercase = false)
    {
        $this->merchant = $merchant;
        $this->code = $code;
        $this->hasUppercase = $hasUppercase;
    }

   /**
	 * Get the message envelope.
	 *
	 * @return \Illuminate\Mail\Mailables\Envelope
	 */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verification Mail',
        );
    }

    

     /**
	 * Get the message content definition.
	 *
	 * @return \Illuminate\Mail\Mailables\Content
	 */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function build()
    {
        return $this->subject('Email Verification')
                    ->view('emails.verification')
                    ->with([
                        'code' => $this->code,
                        'hasUppercase' => $this->hasUppercase,
                    ]);
    }
}
