<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Notices;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\Notice;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Notices\Index` (Book I COM-06 §4, `notices.view`). Read
 * receipts exist for `important`/`urgent` notices only
 * (BR-COM-06-004) — a `normal` notice shows no read count at all
 * rather than a misleading zero. Read counts are the number of
 * receipts recorded; the audience denominator is not shown because the
 * backend itself only approximates it for narrow scopes.
 */
#[Title('Notice board')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $statusFilter = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('notices.view');
    }

    public function render(): View
    {
        $notices = Notice::withCount('reads')
            ->where('school_id', $this->school->id)
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('is_pinned')
            ->orderByDesc('publish_at')
            ->limit(100)
            ->get();

        return view('comms::notices.index', [
            'notices' => $notices,
            'canPost' => app(PermissionScopeResolver::class)->has(auth()->user(), 'notices.post', PermissionScope::Own),
        ]);
    }
}
