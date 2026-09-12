<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Backups;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Backups\ApplyBackupRetentionAction;
use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\Actions\Backups\RunRestoreTestAction;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\DataObjects\Backups\RunRestoreTestData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Models\Backup;
use Modules\Core\Models\School;

/**
 * `Core\Backups\Index` (Book A CORE-13 §5, `core.backup.view`) — every
 * backup across every scope. System-wide with no `{school}` of its own
 * — same tenant-wide authorisation gap as CORE-12's `Scheduling\Tasks`
 * (see that class's docblock): `core.backup.view`/`manage` are
 * registered in the catalogue but not yet enforced here, pending the
 * "any school" resolver that gap has been waiting on since CORE-05.
 */
#[Title('Backups')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithDataTable;
    use Toasts;

    public bool $showCreateModal = false;

    public string $type = 'database';

    public ?int $schoolId = null;

    public function openCreateModal(): void
    {
        $this->reset(['type', 'schoolId']);
        $this->showCreateModal = true;
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->validate([
            'type' => ['required', 'in:database,files,full,school_export'],
            'schoolId' => ['required_if:type,school_export', 'nullable', 'integer'],
        ]);

        $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(
            type: $this->type,
            triggeredBy: 'manual',
            schoolId: $this->type === 'school_export' ? $this->schoolId : null,
            createdByUserId: (int) Auth::id(),
        ));

        $this->showCreateModal = false;

        $this->toast(
            $backup->status === 'completed' ? __('Backup completed.') : __('Backup failed — see its detail page.'),
            $backup->status === 'completed' ? 'success' : 'danger',
        );
    }

    public function runRestoreTest(int $backupId): void
    {
        try {
            $test = app(RunRestoreTestAction::class)->execute(new RunRestoreTestData($backupId));
            $this->toast($test->passed() ? __('Restore test passed.') : __('Restore test failed.'), $test->passed() ? 'success' : 'danger');
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function runRetention(): void
    {
        $expired = app(ApplyBackupRetentionAction::class)->execute();

        $this->toast(__(':count backup(s) expired under the retention policy.', ['count' => count($expired)]));
    }

    public function render(): View
    {
        $query = Backup::query();

        return view('core::backups.index', [
            'backups' => $this->paginateDataTable($query, $this->tableColumns()),
            'schools' => School::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'type' => [
                'label' => __('Type'), 'sortable' => true, 'filter' => 'select',
                'options' => ['database' => __('Database'), 'files' => __('Files'), 'full' => __('Full'), 'school_export' => __('School export')],
            ],
            'scope' => ['label' => __('Scope'), 'sortable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['running' => __('Running'), 'completed' => __('Completed'), 'failed' => __('Failed'), 'verified' => __('Verified'), 'expired' => __('Expired')],
            ],
            'size_bytes' => ['label' => __('Size'), 'sortable' => true],
            'triggered_by' => ['label' => __('Triggered by')],
            'started_at' => ['label' => __('Started'), 'sortable' => true],
        ];
    }
}
