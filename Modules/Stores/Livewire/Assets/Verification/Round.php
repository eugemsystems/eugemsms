<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Verification;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CreateVerificationRoundAction;
use Modules\Stores\Domain\Actions\RecordAssetVerificationScanAction;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\AssetVerification;

/**
 * `Assets\Verification\Round` (Book H1 FIN-10 §5 ⭐, `assets.verify`).
 * `CreateVerificationRoundAction` creates one row per active asset up
 * front — an asset never scanned stays visibly `pending`, never
 * silently absent (BR-FIN-10-011). A scan in an unexpected location
 * records a discrepancy on the verification row only; it never
 * corrects the asset's own `location` field.
 */
#[Title('Asset verification')]
#[Layout('layouts.app')]
final class Round extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $verificationRound = '';

    public ?int $categoryId = null;

    /** @var array<int, string> verificationId => actual location */
    public array $actualLocations = [];

    /** @var array<int, string> verificationId => condition */
    public array $conditions = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('assets.verify');
        $this->verificationRound = now()->format('Y').'-Q'.(string) (int) ceil(now()->month / 3);
    }

    public function createRound(): void
    {
        $this->validate(['verificationRound' => ['required', 'string', 'max:40']]);

        $count = app(CreateVerificationRoundAction::class)->execute($this->school->id, $this->verificationRound, $this->categoryId);
        $this->toast(__(':n assets queued for this round.', ['n' => $count]));
    }

    public function scan(int $verificationId, bool $found): void
    {
        app(RecordAssetVerificationScanAction::class)->execute(
            $verificationId,
            $found,
            $this->actualLocations[$verificationId] ?? null,
            $this->conditions[$verificationId] ?? null,
            'manual',
            (int) auth()->id(),
        );

        $this->toast($found ? __('Scan recorded.') : __('Marked not found — flagged for investigation.'));
    }

    public function render(): View
    {
        return view('stores::assets.verification.round', [
            'categories' => AssetCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
            'pending' => AssetVerification::with('asset')
                ->where('school_id', $this->school->id)
                ->where('verification_round', $this->verificationRound)
                ->where('status', 'pending')
                ->get(),
        ]);
    }
}
