<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Audit;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Audit\RecordDataAccessAction;
use Modules\Core\Domain\DataObjects\Audit\RecordDataAccessData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\School;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `Core\Audit\Export` (Book A CORE-08 §5/BR-CORE-08-010, `core.audit.export`)
 * — a CSV of the activity log for an external auditor. The export
 * itself is logged via `RecordDataAccessAction`, which raises a
 * security event on its own if the row count crosses
 * `audit.bulk_export_threshold` (AC-CORE-08-005) — this screen doesn't
 * duplicate that threshold check.
 */
#[Title('Audit export')]
#[Layout('layouts.app')]
final class Export extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.audit.export');

        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    /**
     * @return Builder<ActivityLogEntry>
     */
    private function filteredQuery(): Builder
    {
        return ActivityLogEntry::query()
            ->where('school_id', $this->school->id)
            ->whereDate('created_at', '>=', $this->fromDate)
            ->whereDate('created_at', '<=', $this->toDate)
            ->orderBy('created_at');
    }

    public function previewCount(): int
    {
        return $this->filteredQuery()->count();
    }

    public function export(): StreamedResponse
    {
        $this->validate([
            'fromDate' => ['required', 'date'],
            'toDate' => ['required', 'date', 'after_or_equal:fromDate'],
        ]);

        $entries = $this->filteredQuery()->get();

        app(RecordDataAccessAction::class)->execute(new RecordDataAccessData(
            schoolId: $this->school->id,
            userId: (int) Auth::id(),
            accessType: 'export',
            resourceType: 'activity_log',
            recordCount: $entries->count(),
            purpose: 'Audit export',
            ip: request()->ip(),
        ));

        $filename = "audit-export-{$this->fromDate}-to-{$this->toDate}.csv";

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the output stream for the audit export.');
            }

            fputcsv($handle, ['Date', 'Module', 'Event', 'Description', 'Subject type', 'Subject ID', 'Causer ID']);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->created_at->toDateTimeString(),
                    $entry->log_name,
                    $entry->event,
                    $entry->description,
                    $entry->subject_type,
                    $entry->subject_id,
                    $entry->causer_id,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        return view('core::audit.export', ['previewCount' => $this->previewCount()]);
    }
}
