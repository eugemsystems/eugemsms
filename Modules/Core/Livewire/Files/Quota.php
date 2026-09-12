<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Files;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Files\SetStorageQuotaAction;
use Modules\Core\Domain\DataObjects\Files\SetStorageQuotaData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Modules\Core\Models\StorageQuota;

/**
 * `Core\Files\Quota` (Book A CORE-10 BR-CORE-10-010,
 * `core.file.manage_quota`) — usage against the school's storage quota,
 * plus a breakdown of used bytes by category (helps an admin see what's
 * actually filling the quota before raising it).
 */
#[Title('Storage quota')]
#[Layout('layouts.app')]
final class Quota extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public bool $showEditModal = false;

    public string $quotaGb = '';

    public int $warnAtPercent = 85;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.file.manage_quota');
    }

    public function openEditModal(): void
    {
        $quota = $this->currentQuota();

        $this->quotaGb = $quota !== null ? (string) round($quota->quota_bytes / (1024 ** 3), 2) : '';
        $this->warnAtPercent = $quota->warn_at_percent ?? 85;
        $this->showEditModal = true;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'quotaGb' => ['required', 'numeric', 'min:0.01'],
            'warnAtPercent' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        app(SetStorageQuotaAction::class)->execute(new SetStorageQuotaData(
            schoolId: $this->school->id,
            quotaBytes: (int) round(((float) $this->quotaGb) * (1024 ** 3)),
            warnAtPercent: $this->warnAtPercent,
        ));

        $this->showEditModal = false;
        $this->toast(__('Storage quota updated.'));
    }

    private function currentQuota(): ?StorageQuota
    {
        return StorageQuota::where('school_id', $this->school->id)->first();
    }

    public function render(): View
    {
        $byCategory = File::query()
            ->where('school_id', $this->school->id)
            ->selectRaw('category, SUM(size_bytes) as total_bytes, COUNT(*) as total_count')
            ->groupBy('category')
            ->orderByDesc('total_bytes')
            ->get();

        return view('core::files.quota', [
            'quota' => $this->currentQuota(),
            'byCategory' => $byCategory,
        ]);
    }
}
