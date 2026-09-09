<?php

namespace App\Models;

use App\Support\Subscription\SubscriptionModules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_interval',
        'trial_days',
        'max_branches',
        'max_staff',
        'modules',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'trial_days' => 'integer',
            'max_branches' => 'integer',
            'max_staff' => 'integer',
            'modules' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaloonSubscription::class);
    }

    /**
     * @return list<string>
     */
    public function moduleList(): array
    {
        $modules = $this->modules ?? [];

        return array_values(array_intersect($modules, SubscriptionModules::all()));
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->moduleList(), true);
    }

    public function isFreePlan(): bool
    {
        return in_array($this->slug, ['free', 'free-trial'], true) || (float) $this->price <= 0;
    }

    public function isPaidPlan(): bool
    {
        return ! $this->isFreePlan();
    }

    public function hasUnlimitedBranches(): bool
    {
        return $this->max_branches === null;
    }

    public function hasUnlimitedStaff(): bool
    {
        return $this->max_staff === null;
    }
}
