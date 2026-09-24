<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'saloon_id',
        'branch_id',
        'user_id',
        'type',
        'status',
        'from_date',
        'to_date',
        'reason',
        'approver_id',
        'decided_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'decided_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Approved leave overlapping a calendar day (booking block).
     */
    public function scopeApprovedOverlapping(Builder $query, int $userId, string $date): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('status', self::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date);
    }
}
