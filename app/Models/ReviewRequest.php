<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewRequest extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_SUPPRESSED = 'suppressed';

    protected $fillable = [
        'saloon_id',
        'branch_id',
        'customer_id',
        'appointment_id',
        'status',
        'channel',
        'google_link',
        'google_link_eligible',
        'scheduled_for',
        'sent_at',
        'suppress_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'google_link_eligible' => 'boolean',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(ServiceRating::class);
    }
}
