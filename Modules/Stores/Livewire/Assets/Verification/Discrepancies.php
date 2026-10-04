<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Verification;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\WriteOffNotFoundAssetAction;
use Modules\Stores\Models\AssetVerification;

/**
 * `Assets\Verification\Discrepancies` (Book H1 FIN-10 §5 ⭐,
 * `assets.verify`, AC-FIN-10-005). The only write-off path for a
 * not-found asset requires a different user from whoever recorded the
 * failed scan — `WriteOffNotFoundAssetAction` itself refuses
 * otherwise (BR-FIN-10-012).
 */
#[Title('Verification discrepancies')]
#[Layout('layouts.app')]
final class Discrepancies extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> verificationId => reason */
    public array $reasons = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('assets.verify');
    }

    public function writeOff(int $verificationId): void
    {
        $reason = $this->reasons[$verificationId] ?? '';

        try {
            app(WriteOffNotFoundAssetAction::class)->execute($verificationId, (int) auth()->id(), $reason);
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->toast(__('Asset written off.'));
    }

    public function render(): View
    {
        return view('stores::assets.verification.discrepancies', [
            'discrepancies' => AssetVerification::with('asset')
                ->where('school_id', $this->school->id)
                ->whereIn('status', ['not_found', 'discrepancy'])
                ->get(),
        ]);
    }
}
