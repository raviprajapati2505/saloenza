<?php

namespace App\Models;

use App\Support\Role\RoleCodes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_AFFILIATE = 'affiliate';

    public const SCOPE_SALON = 'salon';

    public const SCOPE_BRANCH = 'branch';

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'is_system',
        'scope',
        'hierarchy_level',
        'saloon_id',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'hierarchy_level' => 'integer',
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }

    public function isPlatformScoped(): bool
    {
        return $this->scope === self::SCOPE_PLATFORM;
    }

    public function isBranchScoped(): bool
    {
        return $this->scope === self::SCOPE_BRANCH;
    }

    public function isAffiliateScoped(): bool
    {
        return $this->scope === self::SCOPE_AFFILIATE;
    }

    public function isFranchiseOwner(): bool
    {
        return $this->code === RoleCodes::SALON_FRANCHISE_OWNER;
    }
}
