<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Replaces Flux's `Flux::toast()` server-side dispatch: a Livewire
 * component pushes a browser event that the toast renderer in
 * resources/js/app.js picks up and displays as a Bootstrap toast.
 */
trait Toasts
{
    protected function toast(string $text, string $variant = 'success'): void
    {
        $this->dispatch('toast', text: $text, variant: $variant);
    }
}
