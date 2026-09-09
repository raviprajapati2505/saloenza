<?php

namespace App\Mail;

use App\Mail\Concerns\UsesTenantBranding;
use App\Models\Appointment;
use App\Models\Saloon;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffAssignmentMail extends Mailable
{
    use Queueable, SerializesModels, UsesTenantBranding;

    public function __construct(
        public readonly Saloon $saloon,
        public readonly Appointment $appointment,
        public readonly User $staff,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New appointment assigned — '.$this->saloon->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-assignment',
            with: [
                'staffName' => $this->staff->name ?: 'there',
                'salonName' => $this->saloon->name,
                'startsAt' => $this->appointment->starts_at?->format('d M Y, h:i A') ?? 'soon',
                'customerName' => $this->appointment->customer?->name ?? 'Walk-in customer',
                ...$this->brandingViewData(),
            ],
        );
    }
}
