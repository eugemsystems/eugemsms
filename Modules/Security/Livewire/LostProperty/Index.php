<?php

declare(strict_types=1);

namespace Modules\Security\Livewire\LostProperty;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Security\Domain\Actions\ClaimLostPropertyAction;
use Modules\Security\Domain\Actions\ReportLostPropertyAction;
use Modules\Security\Domain\DataObjects\ReportLostPropertyData;
use Modules\Security\Models\LostProperty;

/**
 * `LostProperty\Index` (Book H2 OPS-06 §5, `security.manage`).
 */
#[Title('Lost property')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $foundOn = '';

    public string $description = '';

    public ?string $foundLocation = null;

    public ?int $claimingItemId = null;

    public ?int $claimedByStudentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('security.manage');
        $this->foundOn = Carbon::now()->toDateString();
    }

    public function report(): void
    {
        $this->validate([
            'foundOn' => ['required', 'date'],
            'description' => ['required', 'string'],
        ]);

        app(ReportLostPropertyAction::class)->execute(new ReportLostPropertyData(
            schoolId: $this->school->id,
            foundOn: Carbon::parse($this->foundOn),
            description: $this->description,
            foundLocation: $this->foundLocation,
            foundByUserId: (int) auth()->id(),
        ));

        $this->reset(['description', 'foundLocation']);
        $this->toast(__('Lost property reported.'));
    }

    public function selectForClaim(int $itemId): void
    {
        $this->claimingItemId = $itemId;
    }

    public function claim(): void
    {
        if ($this->claimingItemId === null) {
            return;
        }

        $this->validate(['claimedByStudentId' => ['required', 'integer']]);

        app(ClaimLostPropertyAction::class)->execute($this->claimingItemId, (int) $this->claimedByStudentId);

        $this->reset(['claimingItemId', 'claimedByStudentId']);
        $this->toast(__('Item claimed.'));
    }

    public function render(): View
    {
        return view('security::lost-property.index', [
            'items' => LostProperty::where('school_id', $this->school->id)->orderByDesc('found_on')->limit(50)->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('last_name')->limit(200)->get(),
        ]);
    }
}
