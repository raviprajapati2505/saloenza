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

class CustomerOccasionWishMail extends Mailable
{
    use Queueable, SerializesModels, UsesTenantBranding;

    public function __construct(
        public readonly Saloon $saloon,
        public readonly Customer $customer,
        public readonly string $occasion,
        public readonly string $offerText,
    ) {
    }

    public function envelope(): Envelope
    {
        $salonName = $this->saloon->name;

        $subject = $this->occasion === 'anniversary'
            ? 'Happy anniversary from '.$salonName
            : 'Happy birthday from '.$salonName;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $greeting = $this->occasion === 'anniversary'
            ? 'Happy anniversary'
            : 'Happy birthday';

        return new Content(
            view: 'emails.customer-occasion-wish',
            with: [
                'customerName' => $this->customer->name ?: 'there',
                'salonName' => $this->saloon->name,
                'greeting' => $greeting,
                'occasion' => $this->occasion,
                'offerText' => trim($this->offerText),
                ...$this->brandingViewData(),
            ],
        );
    }
}
