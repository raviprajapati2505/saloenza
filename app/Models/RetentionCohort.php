<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetentionCohort extends Model
{
    public const STATUSES = ['draft', 'ready', 'sending', 'completed', 'cancelled'];

    protected $fillable = [
        'saloon_id',
        'branch_id',
        'policy_id',
        'name',
        'status',
        'segment_filter_json',
        'template_channel',
        'template_code',
        'note',
        'scheduled_at',
        'created_by',
        'stats_json',
    ];

    protected function casts(): array
    {
        return [
            'segment_filter_json' => 'array',
            'stats_json' => 'array',
            'scheduled_at' => 'datetime',
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

    public function policy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'policy_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(RetentionCohortMember::class, 'cohort_id');
    }
}
