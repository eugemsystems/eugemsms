<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Audit;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Audit\RunIntegrityChecksAction;
use Modules\Core\Domain\DataObjects\Audit\RunIntegrityChecksData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\FinancialAuditLogEntry;
use Modules\Core\Models\School;

/**
 * `Core\Audit\FinancialStream` (Book A CORE-08 §5, `core.audit.view_financial`)
 * — the append-only, hash-chained financial audit log, with a
 * verify-chain button running the registered `audit_chain` integrity
 * check on demand rather than waiting for its nightly schedule
 * (BR-CORE-08-008; the actual nightly trigger is a later wave, same as
 * every other CORE background job in Book A).
 */
#[Title('Financial audit stream')]
#[Layout('layouts.app')]
final class FinancialStream extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.audit.view_financial');
    }

    public function verifyChain(): void
    {
        $runs = app(RunIntegrityChecksAction::class)->execute(new RunIntegrityChecksData(
            schoolId: $this->school->id,
            checkTypes: ['audit_chain'],
        ));

        $run = $runs[0] ?? null;

        if ($run === null) {
            $this->toast(__('The chain-verification check is not available.'), 'danger');

            return;
        }

        $this->toast(
            $run->passed()
                ? __('Chain verified — no breaks found across :count entries.', ['count' => $run->records_checked])
                : __(':count break(s) found in the audit chain — see the security events queue.', ['count' => $run->failures_found]),
            $run->passed() ? 'success' : 'danger',
        );
    }

    public function render(): View
    {
        $query = FinancialAuditLogEntry::query()->where('school_id', $this->school->id)->with('causer');

        return view('core::audit.financial-stream', [
            'entries' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'sequence' => ['label' => __('#'), 'sortable' => true],
            'event_type' => ['label' => __('Event'), 'sortable' => true, 'searchable' => true],
            'amount_minor' => ['label' => __('Amount'), 'sortable' => true],
            'causer' => ['label' => __('By')],
            'occurred_at' => ['label' => __('When'), 'sortable' => true],
        ];
    }
}
