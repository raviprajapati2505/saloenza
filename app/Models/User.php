<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Role\RoleCodes;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'firstname',
        'lastname',
        'email',
        'phone',
        'photo',
        'role_id',
        'password',
        'saloon_id',
        'branch_id',
        'is_active',
        'commission_rate',
        'per_month_salary',
        'notes',
        'joined_at',
        'weekly_schedule',
        'onboarding_completed_at',
    ];

    protected $appends = [
        'photo_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarding_completed_at' => 'datetime',
            'joined_at' => 'date',
            'weekly_schedule' => 'array',
            'is_system_admin' => 'boolean',
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2',
            'per_month_salary' => 'decimal:2',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }

    public function affiliatePartner(): HasOne
    {
        return $this->hasOne(AffiliatePartner::class);
    }

    public function hasRoleCode(string $code): bool
    {
        $this->loadMissing('role');

        return $this->role?->code === $code;
    }

    public function isFranchiseOwner(): bool
    {
        return $this->hasRoleCode(RoleCodes::SALON_FRANCHISE_OWNER);
    }

    public function shouldOnboard(): bool
    {
        $this->loadMissing(['role', 'affiliatePartner']);

        if ($this->affiliatePartner !== null || $this->role?->scope === 'affiliate') {
            return false;
        }

        if ($this->is_system_admin || $this->role?->scope === 'platform') {
            return false;
        }

        if (! $this->isFranchiseOwner()) {
            return false;
        }

        return $this->onboarding_completed_at === null;
    }

    public function isAffiliatePartner(): bool
    {
        return $this->hasRoleCode(RoleCodes::AFFILIATE_PARTNER);
    }

    public function isBranchScopedActor(): bool
    {
        $this->loadMissing('role');

        return $this->role?->isBranchScoped() === true;
    }

    public function grantsAllPermissions(): bool
    {
        if ($this->is_system_admin) {
            return true;
        }

        return $this->hasRoleCode(RoleCodes::PLATFORM_SUPER_ADMIN);
    }

    public function isPlatformActor(): bool
    {
        if ($this->grantsAllPermissions()) {
            return true;
        }

        $this->loadMissing('role');

        return $this->role?->scope === 'platform';
    }

    public function passwordResetOtps(): HasMany
    {
        return $this->hasMany(PasswordResetOtp::class);
    }

    /**
     * @param Builder<User> $query
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('is_system_admin', false);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->photo) {
                return null;
            }

            return asset('storage/' . $this->photo);
        });
    }
}
