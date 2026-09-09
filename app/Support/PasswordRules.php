<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    public static function defaults(): Password
    {
        return Password::min(8)
            ->mixedCase()
            ->symbols();
    }
}
