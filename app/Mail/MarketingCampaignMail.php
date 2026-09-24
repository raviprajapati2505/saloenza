<?php

namespace App\Mail;

use App\Mail\Concerns\UsesTenantBranding;
use App\Models\Customer;
use App\Models\Saloon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MarketingCampaignMail extends Mailable
{
    use Queueable, SerializesModels, UsesTenantBranding;

    public function __construct(
        public readonly Saloon $saloon,
        public readonly Customer $customer,
        public readonly string $campaignSubject,
        public readonly string $bodyText,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaignSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing-campaign',
            with: [
                'customerName' => $this->customer->name ?: 'there',
                'salonName' => $this->saloon->name,
                'bodyText' => $this->bodyText,
                ...$this->brandingViewData(),
            ],
        );
    }
}
