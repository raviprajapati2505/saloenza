<?php

namespace App\Mail;

use App\Mail\Concerns\UsesTenantBranding;
use App\Models\Saloon;
use App\Models\SaloonSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels, UsesTenantBranding;

    public function __construct(
        public readonly User $user,
        public readonly Saloon $saloon,
        public readonly SaloonSubscription $subscription,
        public readonly SubscriptionPlan $plan,
        public readonly int $daysRemaining,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Renew {$this->plan->name} - {$this->daysRemaining} days left",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-renewal-reminder',
            with: [
                'userName' => trim((string) ($this->user->name ?? 'there')),
                'salonName' => $this->saloon->name ?: 'your salon',
                'planName' => $this->plan->name ?: 'your plan',
                'daysRemaining' => $this->daysRemaining,
                'dueDate' => $this->subscription->renewalDueAt()?->toFormattedDateString() ?? 'soon',
                'billingUrl' => rtrim((string) config('app.url'), '/') . '/billing',
                ...$this->brandingViewData(),
            ],
        );
    }
}
