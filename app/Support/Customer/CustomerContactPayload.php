<?php

namespace App\Support\Customer;

use App\Models\Customer;
use App\Models\User;

final class CustomerContactPayload
{
    /**
     * @return array{
     *     id: int|null,
     *     name: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     can_view_contact: bool,
     *     has_phone: bool,
     *     has_email: bool
     * }
     */
    public static function identity(?Customer $customer, ?User $viewer): array
    {
        if ($customer === null) {
            return [
                'id' => null,
                'name' => null,
                'phone' => null,
                'email' => null,
                'can_view_contact' => false,
                'has_phone' => false,
                'has_email' => false,
            ];
        }

        $canView = CustomerContactAccess::canView($viewer, $customer);

        return [
            'id' => (int) $customer->id,
            'name' => $customer->name,
            'phone' => $canView && filled($customer->phone) ? (string) $customer->phone : null,
            'email' => $canView && filled($customer->email) ? (string) $customer->email : null,
            'can_view_contact' => $canView,
            'has_phone' => filled($customer->phone),
            'has_email' => filled($customer->email),
        ];
    }
}
