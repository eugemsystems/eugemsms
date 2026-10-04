<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Gate;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Gate\Attempts` (Book F BRD-03 §6, `boarding.gate.view`).
 * Append-only (BR-BRD-03-012 ⭐) — no delete or edit control exists
 * anywhere on this screen, matching `CollectionAttempt`'s own database
 * grant. Refusals are highlighted because a pattern of a restricted
 * adult repeatedly presenting at the gate is itself the signal a
 * safeguarding lead needs.
 */
#[Title('Collection attempts')]
#[Layout('layouts.app')]
final class Attempts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.gate.view');
    }

    public function render(): View
    {
        return view('boarding::gate.attempts', [
            'attempts' => CollectionAttempt::where('school_id', $this->school->id)->with('student')->orderByDesc('occurred_at')->limit(100)->get(),
        ]);
    }
}
