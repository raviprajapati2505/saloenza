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

class AppointmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels, UsesTenantBranding;

    public function __construct(
        public readonly Saloon $saloon,
        public readonly Appointment $appointment,
        public readonly Customer $customer,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Appointment confirmed — '.$this->saloon->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-confirmation',
            with: [
                'customerName' => $this->customer->name ?: 'there',
                'salonName' => $this->saloon->name,
                'startsAt' => $this->appointment->starts_at?->format('d M Y, h:i A') ?? 'soon',
                'serviceName' => $this->appointment->service?->name ?? 'your service',
                ...$this->brandingViewData(),
            ],
        );
    }
}
