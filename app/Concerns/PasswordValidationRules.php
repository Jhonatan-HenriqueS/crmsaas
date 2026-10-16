<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(bool $registration = false): array
    {
        $password = $registration ? Password::min(8)->uncompromised() : Password::default();

        return ['required', 'string', $password->max(128), ...($registration ? [] : ['confirmed'])];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['bail', 'required', 'string', 'current_password'];
    }
}
