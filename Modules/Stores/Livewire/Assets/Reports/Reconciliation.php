<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ReconcileAssetRegisterAction;
use Modules\Stores\Models\AssetCategory;

/**
 * `Assets\Reports\Reconciliation` (Book H1 FIN-10 §5, `assets.report.view`,
 * AC-FIN-10-008). The register never becomes a second source of
 * truth — this screen only ever compares it against the GL's own
 * `account_balances` cache and names the difference; it writes
 * nothing.
 */
#[Title('Asset reconciliation')]
#[Layout('layouts.app')]
final class Reconciliation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('assets.report.view');
    }

    public function render(): View
    {
        $divergences = app(ReconcileAssetRegisterAction::class)->execute($this->school->id);
        $categories = AssetCategory::whereIn('id', $divergences->pluck('categoryId'))->get()->keyBy('id');

        return view('stores::assets.reports.reconciliation', [
            'divergences' => $divergences,
            'categories' => $categories,
        ]);
    }
}
