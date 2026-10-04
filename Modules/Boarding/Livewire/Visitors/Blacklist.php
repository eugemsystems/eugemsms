<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Visitors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\BlacklistVisitorAction;
use Modules\Boarding\Domain\DataObjects\BlacklistVisitorData;
use Modules\Boarding\Models\Visitor;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Visitors\Blacklist` (Book F BRD-03 §6 ⚠, `boarding.visitor.blacklist`).
 */
#[Title('Visitor blacklist')]
#[Layout('layouts.app')]
final class Blacklist extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $visitorSearch = '';

    public ?int $visitorId = null;

    public string $reason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.visitor.blacklist');
    }

    public function blacklist(): void
    {
        if ($this->visitorId === null || trim($this->reason) === '') {
            $this->toast(__('Pick a visitor and give a reason.'), 'danger');

            return;
        }

        app(BlacklistVisitorAction::class)->execute(new BlacklistVisitorData(
            visitorId: $this->visitorId,
            reason: $this->reason,
            blacklistedByUserId: (int) Auth::id(),
        ));

        $this->reset(['visitorSearch', 'visitorId', 'reason']);
        $this->toast(__('Visitor blacklisted — refused at sign-in from now on.'));
    }

    public function render(): View
    {
        $searchResults = $this->visitorSearch !== ''
            ? Visitor::where('school_id', $this->school->id)->where('full_name', 'like', "%{$this->visitorSearch}%")->limit(10)->get()
            : collect();

        return view('boarding::visitors.blacklist', [
            'blacklisted' => Visitor::where('school_id', $this->school->id)->where('is_blacklisted', true)->get(),
            'searchResults' => $searchResults,
        ]);
    }
}
