<?php

namespace App\Support\Expense;

final class ExpenseCategory
{
    public const RENT = 'rent';

    public const UTILITIES = 'utilities';

    public const SALARIES = 'salaries';

    public const MARKETING = 'marketing';

    public const SUPPLIES = 'supplies';

    public const MAINTENANCE = 'maintenance';

    public const SOFTWARE = 'software';

    public const TAXES = 'taxes';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::RENT,
            self::UTILITIES,
            self::SALARIES,
            self::MARKETING,
            self::SUPPLIES,
            self::MAINTENANCE,
            self::SOFTWARE,
            self::TAXES,
            self::OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::RENT => 'Rent & Lease',
            self::UTILITIES => 'Utilities',
            self::SALARIES => 'Salaries & Wages',
            self::MARKETING => 'Marketing',
            self::SUPPLIES => 'Salon Supplies',
            self::MAINTENANCE => 'Repairs & Maintenance',
            self::SOFTWARE => 'Software & Subscriptions',
            self::TAXES => 'Taxes & Licences',
            self::OTHER => 'Other',
        ];
    }

    public static function label(string $category): string
    {
        return self::labels()[$category] ?? ucfirst($category);
    }

    /**
     * Salary rows are recorded manually, so payroll estimated from staff records
     * must be excluded to avoid counting the same cost twice.
     */
    public static function overlapsPayroll(string $category): bool
    {
        return $category === self::SALARIES;
    }
}
