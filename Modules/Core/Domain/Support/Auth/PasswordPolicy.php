<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use Modules\Core\Domain\Contracts\Auth\BreachedPasswordChecker;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * Book A CORE-05 BR-CORE-05-004/§10. Reads the configured password
 * rules from `SettingResolver` (min length, mixed case, number, symbol)
 * and rejects anything on the breached-password list.
 */
final readonly class PasswordPolicy
{
    public function __construct(
        private SettingResolver $settings,
        private BreachedPasswordChecker $breachedPasswords,
    ) {}

    /**
     * @return array<int, string> validation failure messages; empty means the password passes
     */
    public function validate(string $password, ?ScopeChain $scope = null): array
    {
        $errors = [];

        $minLength = (int) $this->settings->get('auth.password_min_length', $scope);
        if (mb_strlen($password) < $minLength) {
            $errors[] = "The password must be at least {$minLength} characters.";
        }

        if ($this->settings->get('auth.password_requires_mixed_case', $scope)
            && ! (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password))) {
            $errors[] = 'The password must contain both upper and lower case letters.';
        }

        if ($this->settings->get('auth.password_requires_number', $scope) && ! preg_match('/\d/', $password)) {
            $errors[] = 'The password must contain at least one number.';
        }

        if ($this->settings->get('auth.password_requires_symbol', $scope) && ! preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'The password must contain at least one symbol.';
        }

        if ($this->breachedPasswords->isBreached($password)) {
            $errors[] = 'This password has appeared in a known data breach. Please choose a different one.';
        }

        return $errors;
    }
}
