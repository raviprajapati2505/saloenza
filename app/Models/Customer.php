<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'notes',
        'is_active',
        'created_by',
        'birthday',
        'anniversary',
        'last_birthday_wish_on',
        'last_anniversary_wish_on',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'birthday' => 'date',
            'anniversary' => 'date',
            'last_birthday_wish_on' => 'date',
            'last_anniversary_wish_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saloons(): BelongsToMany
    {
        return $this->belongsToMany(Saloon::class, 'saloon_customers')
            ->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_customer_tag')
            ->withTimestamps();
    }

    public function scopeForSaloon(Builder $query, int $saloonId): Builder
    {
        return $query->whereHas('saloons', function (Builder $inner) use ($saloonId): void {
            $inner->where('saloons.id', $saloonId);
        });
    }

    public function belongsToSaloon(int $saloonId): bool
    {
        if ($this->relationLoaded('saloons')) {
            return $this->saloons->contains('id', $saloonId);
        }

        return $this->saloons()->where('saloons.id', $saloonId)->exists();
    }

    public function attachSaloon(int $saloonId): void
    {
        $this->saloons()->syncWithoutDetaching([$saloonId]);
    }
}
