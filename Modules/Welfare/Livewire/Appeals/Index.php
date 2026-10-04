<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Appeals;

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
use Modules\Welfare\Domain\Actions\DecideAppealAction;
use Modules\Welfare\Domain\Actions\LodgeAppealAction;
use Modules\Welfare\Models\Appeal;
use Modules\Welfare\Models\Sanction;

/**
 * `Appeals\Index` (Book G BRD-07 §5, `behaviour.appeal.manage`). Lodge
 * and decide, one lifecycle screen. Lodging within the window suspends
 * the sanction per the school's own `behaviour.appeal_suspends_sanction`
 * setting — `LodgeAppealAction` itself reads that, not this screen.
 */
#[Title('Appeals')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $sanctionId = null;

    public string $grounds = '';

    public bool $lodgedByStudent = false;

    public ?int $decidingAppealId = null;

    public string $outcome = 'dismissed';

    public string $outcomeReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.appeal.manage');
    }

    public function lodge(): void
    {
        $this->validate([
            'sanctionId' => ['required', 'integer'],
            'grounds' => ['required', 'string'],
        ]);

        try {
            app(LodgeAppealAction::class)->execute((int) $this->sanctionId, null, $this->lodgedByStudent, $this->grounds);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['grounds', 'lodgedByStudent']);
        $this->toast(__('Appeal lodged.'));
    }

    public function decide(int $appealId): void
    {
        if (trim($this->outcomeReason) === '') {
            $this->toast(__('An outcome reason is required.'), 'danger');

            return;
        }

        app(DecideAppealAction::class)->execute($appealId, $this->outcome, $this->outcomeReason, (int) Auth::id());

        $this->reset(['decidingAppealId', 'outcomeReason']);
        $this->toast(__('Appeal decided.'));
    }

    public function render(): View
    {
        return view('welfare::appeals.index', [
            'sanctions' => Sanction::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('id')->limit(100)->get(),
            'appeals' => Appeal::where('school_id', $this->school->id)->with('sanction.student:id,first_name,last_name')->orderByDesc('id')->limit(100)->get(),
        ]);
    }
}
