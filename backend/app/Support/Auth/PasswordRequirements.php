<?php

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

final class PasswordRequirements
{
    public static function rule(): Password
    {
        return Password::min(8)->letters()->mixedCase()->numbers();
    }
}
