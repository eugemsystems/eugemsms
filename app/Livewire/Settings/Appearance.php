<?php

namespace App\Livewire\Settings;

use App\Concerns\Toasts;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Appearance settings')]
class Appearance extends Component
{
    use Toasts;

    public string $theme = 'system';

    public function mount(): void
    {
        $this->theme = Auth::user()->theme;
    }

    public function updateTheme(string $theme): void
    {
        if (! in_array($theme, ['light', 'dark', 'system'], true)) {
            return;
        }

        $this->theme = $theme;

        Auth::user()->update(['theme' => $theme]);

        $this->dispatch('theme-updated', theme: $theme);
    }
}
