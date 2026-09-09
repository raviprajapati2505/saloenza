<?php

namespace App\Mail;

use App\Mail\Concerns\UsesTenantBranding;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Saloon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutstandingPaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels, UsesTenantBranding;

    public function __construct(
        public readonly Saloon $saloon,
        public readonly Appointment $appointment,
        public readonly Customer $customer,
        public readonly float $balanceDue,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment reminder from '.$this->saloon->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.outstanding-payment-reminder',
            with: [
                'customerName' => $this->customer->name ?: 'there',
                'salonName' => $this->saloon->name,
                'visitDate' => $this->appointment->starts_at?->format('d M Y') ?? 'your recent visit',
                'balanceDue' => number_format($this->balanceDue, 2),
                'invoiceNumber' => $this->appointment->invoice_number,
                ...$this->brandingViewData(),
            ],
        );
    }
}
