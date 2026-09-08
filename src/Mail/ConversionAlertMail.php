<?php

namespace ElectricTomCat\GoogleAdsConversions\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConversionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $type,
        public string $eventName,
        public string $clickId,
        public string $errorMessage,
        public int $retryCount,
        public string $customerId,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->type === 'rejected'
            ? "[Google Ads Conversions] Conversion Permanently Rejected: {$this->eventName}"
            : "[Google Ads Conversions] Conversion Upload Failed: {$this->eventName}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<pre>Google Ads Offline Conversion Alert\n\n"
                ."Type: {$this->type}\n"
                ."Event: {$this->eventName}\n"
                ."Customer ID: {$this->customerId}\n"
                ."Click ID: {$this->clickId}\n"
                ."Attempt: {$this->retryCount}\n"
                ."Error: {$this->errorMessage}\n"
                .'Timestamp: '.now()->toIso8601String().'</pre>'
        );
    }
}
