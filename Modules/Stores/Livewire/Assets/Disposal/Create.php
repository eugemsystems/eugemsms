<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Disposal;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Domain\Actions\DisposeAssetAction;
use Modules\Stores\Domain\DataObjects\DisposeAssetData;
use Modules\Stores\Domain\Exceptions\DisposalRequiresApprovalException;
use Modules\Stores\Models\FixedAsset;

/**
 * `Assets\Disposal\Create` (Book H1 FIN-10 §5, `assets.dispose` ⚠,
 * AC-FIN-10-006). Gain or loss is computed and shown before
 * submission — `DisposeAssetAction` recomputes it independently on
 * save, this preview never substitutes for that.
 */
#[Title('Dispose asset')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $assetId = null;

    public string $disposalDate;

    public string $disposalMethod = 'sale';

    public string $proceedsMinor = '0';

    public ?int $proceedsAccountId = null;

    public string $reason = '';

    public ?string $buyer = null;

    public bool $confirmApproval = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('assets.dispose');
        $this->disposalDate = now()->toDateString();
    }

    public function gainLossPreviewMinor(): ?int
    {
        $asset = $this->assetId !== null ? FixedAsset::find($this->assetId) : null;

        if ($asset === null) {
            return null;
        }

        return (int) $this->proceedsMinor - $asset->net_book_value_minor;
    }

    public function dispose(): void
    {
        $this->validate([
            'assetId' => ['required', 'integer'],
            'disposalDate' => ['required', 'date'],
            'proceedsMinor' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(DisposeAssetAction::class)->execute(new DisposeAssetData(
                assetId: (int) $this->assetId,
                academicYearId: $yearId,
                termId: $termId,
                disposalDate: Carbon::parse($this->disposalDate),
                disposalMethod: $this->disposalMethod,
                proceedsMinor: (int) $this->proceedsMinor,
                reason: $this->reason,
                performedByUserId: (int) auth()->id(),
                proceedsAccountId: $this->proceedsAccountId,
                buyer: $this->buyer,
                approvedByUserId: $this->confirmApproval ? (int) auth()->id() : null,
            ));
        } catch (DisposalRequiresApprovalException $e) {
            $this->toast($e->getMessage().' '.__('Tick confirm-approval and resubmit if you are the approver.'), 'danger');

            return;
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->reset(['assetId', 'proceedsMinor', 'reason', 'buyer', 'confirmApproval']);
        $this->toast(__('Asset disposed.'));
    }

    public function render(): View
    {
        return view('stores::assets.disposal.create', [
            'assets' => FixedAsset::where('school_id', $this->school->id)->where('status', '!=', 'disposed')->orderBy('name')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
            'gainLossPreviewMinor' => $this->gainLossPreviewMinor(),
        ]);
    }
}
