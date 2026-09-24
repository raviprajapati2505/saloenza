<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistOffer extends Model
{
    protected $fillable = [
        'saloon_id',
        'waitlist_entry_id',
        'source_appointment_id',
        'slot_starts_at',
        'slot_ends_at',
        'staff_id',
        'service_id',
        'branch_id',
        'status',
        'channel',
        'offered_at',
        'expires_at',
        'response_appointment_id',
    ];

    protected function casts(): array
    {
        return [
            'slot_starts_at' => 'datetime',
            'slot_ends_at' => 'datetime',
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(WaitlistEntry::class, 'waitlist_entry_id');
    }

    public function sourceAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'source_appointment_id');
    }

    public function responseAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'response_appointment_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }
}
