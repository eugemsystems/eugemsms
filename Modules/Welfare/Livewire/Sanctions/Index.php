<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Sanctions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Domain\Actions\ApproveSanctionAction;
use Modules\Welfare\Models\Sanction;

/**
 * `Sanctions\Index` (Book G BRD-07 §5, `behaviour.sanction.view` to
 * browse, `behaviour.sanction.approve` ⚠ to approve).
 */
#[Title('Sanctions')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.sanction.view');
    }

    public function approve(int $sanctionId): void
    {
        $this->authorizePermission('behaviour.sanction.approve');

        try {
            app(ApproveSanctionAction::class)->execute($sanctionId, (int) Auth::id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Sanction approved.'));
    }

    public function render(): View
    {
        return view('welfare::sanctions.index', [
            'sanctions' => Sanction::where('school_id', $this->school->id)
                ->with(['student:id,first_name,last_name', 'sanctionType:id,name,severity_level'])
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
