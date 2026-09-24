<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionCohortMember extends Model
{
    public const MESSAGE_STATUSES = ['pending', 'sent', 'skipped', 'failed', 'opted_out', 'manual'];

    protected $fillable = [
        'cohort_id',
        'customer_id',
        'lapse_status_at_add',
        'priority_score',
        'message_status',
        'sent_at',
        'response_appointment_id',
        'skip_reason',
    ];

    protected function casts(): array
    {
        return [
            'priority_score' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(RetentionCohort::class, 'cohort_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function responseAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'response_appointment_id');
    }
}
