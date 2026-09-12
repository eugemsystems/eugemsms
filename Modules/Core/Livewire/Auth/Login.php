<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * `Auth\Login` (Book A CORE-05 §6). Deliberately a plain `<form
 * method="POST">` posting to Fortify's own `login.store` route, not a
 * `wire:submit` handler — the real throttle/lockout/status/2FA logic
 * lives server-side in `AuthenticateWebAction`, reached via Fortify's
 * login pipeline (`Modules\Core\Http\Fortify\AuthenticateViaAction`,
 * wired in `FortifyServiceProvider`). Converting the submit itself to
 * `wire:submit` would mean re-implementing that whole pipeline (session
 * regeneration, the 2FA-challenge redirect, rate limiting) a second
 * time in this component; this stays a thin, spec-named wrapper around
 * markup that already worked, so nothing about the actual login
 * mechanics changes.
 *
 * Uses the `#[Layout(...)]` attribute (like every other admin screen)
 * rather than embedding `<x-layouts::auth>` directly in the view — that
 * layout renders a complete `<!DOCTYPE html>` document with more than
 * one element under `<body>`, which trips Livewire's one-root-element
 * requirement for a component's own view when nested inside it.
 */
#[Title('Log in')]
#[Layout('layouts.auth.simple', ['illustration' => 'login'])]
final class Login extends Component
{
    public function render(): View
    {
        return view('core::auth.login');
    }
}
