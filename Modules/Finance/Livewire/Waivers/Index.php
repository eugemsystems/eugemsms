<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Waivers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApproveFeeWaiverAction;
use Modules\Finance\Domain\Actions\RejectFeeWaiverAction;
use Modules\Finance\Domain\Actions\RequestFeeWaiverAction;
use Modules\Finance\Domain\DataObjects\ApproveFeeWaiverData;
use Modules\Finance\Domain\DataObjects\RejectFeeWaiverData;
use Modules\Finance\Domain\DataObjects\RequestFeeWaiverData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FeeWaiver;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Student;

/**
 * `Finance\Waivers\Index` (Book B FIN-03 §5/BR-FIN-03-012/013,
 * `finance.waiver.request`/`.approve`, `finance.write_off.request`/
 * `.approve`). One screen for both — they share the same request→
 * approve lifecycle and differ only in which reason codes/GL accounts
 * apply, matching `fee_waivers.type`'s own single-table design.
 */
#[Title('Waivers & write-offs')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public bool $showRequestModal = false;

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    public ?int $invoiceId = null;

    public string $type = 'waiver';

    public string $amount = '';

    public string $currency = 'USD';

    public string $reasonCode = 'hardship';

    public string $reason = '';

    public ?int $approvingWaiverId = null;

    public ?int $contraAccountId = null;

    public ?int $debtorAccountId = null;

    /**
     * No single permission gates this screen the way every other one
     * does — a user holding only `finance.waiver.approve` (deliberately
     * never the same person as the requester, BR-FIN-03-012's own
     * two-person gate) still needs to open this screen to approve
     * something, even though they can never request one themselves.
     * Baseline access is "holds at least one of the four" rather than
     * any single permission; each action (`request()`/`approve()`)
     * still checks its own specific permission before doing anything.
     */
    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);

        $holdsAny = $this->userHolds('finance.waiver.request')
            || $this->userHolds('finance.waiver.approve')
            || $this->userHolds('finance.write_off.request')
            || $this->userHolds('finance.write_off.approve');

        abort_unless($holdsAny, 403);
    }

    private function canRequestType(string $type): bool
    {
        return $this->userHolds($type === 'write_off' ? 'finance.write_off.request' : 'finance.waiver.request');
    }

    private function canApproveType(string $type): bool
    {
        return $this->userHolds($type === 'write_off' ? 'finance.write_off.approve' : 'finance.waiver.approve');
    }

    private function userHolds(string $permissionName): bool
    {
        $user = Auth::user();

        return $user !== null
            && app(PermissionScopeResolver::class)->has($user, $permissionName, PermissionScope::Own, $this->school->id);
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentResults(): Collection
    {
        if (mb_strlen($this->studentSearch) < 2) {
            return new Collection;
        }

        return Student::query()
            ->where(fn ($q) => $q->where('admission_number', 'like', "%{$this->studentSearch}%")
                ->orWhere('first_name', 'like', "%{$this->studentSearch}%")
                ->orWhere('last_name', 'like', "%{$this->studentSearch}%"))
            ->limit(10)
            ->get();
    }

    public function selectStudent(int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $this->selectedStudentId = $student->id;
        $this->selectedStudentLabel = "{$student->admission_number} — {$student->fullName()}";
        $this->studentSearch = '';
        $this->invoiceId = null;
    }

    public function openRequestModal(): void
    {
        $this->reset(['selectedStudentId', 'selectedStudentLabel', 'invoiceId', 'amount', 'reason']);
        $this->type = 'waiver';
        $this->currency = 'USD';
        $this->reasonCode = 'hardship';
        $this->resetErrorBag();
        $this->showRequestModal = true;
    }

    public function request(): void
    {
        if (! $this->canRequestType($this->type)) {
            $this->addError('reason', __('You do not have permission to request this type.'));

            return;
        }

        $this->validate([
            'type' => ['required', 'in:waiver,write_off'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'currency' => ['required', 'size:3'],
            'reasonCode' => ['required', 'in:hardship,orphan,staff_child,uncollectable,deceased,goodwill'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if ($this->selectedStudentId === null) {
            $this->addError('reason', __('Select a learner first.'));

            return;
        }

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->addError('reason', __('No active academic term is set for this school.'));

            return;
        }

        app(RequestFeeWaiverAction::class)->execute(new RequestFeeWaiverData(
            schoolId: $this->school->id,
            termId: $termId,
            studentId: $this->selectedStudentId,
            type: $this->type,
            amountMinor: (int) round((float) $this->amount * 100),
            currency: $this->currency,
            reasonCode: $this->reasonCode,
            reason: $this->reason,
            requestedByUserId: (int) Auth::id(),
            invoiceId: $this->invoiceId,
        ));

        $this->showRequestModal = false;
        $this->toast(__('Request submitted for approval.'));
    }

    public function openApproveModal(int $waiverId): void
    {
        $this->approvingWaiverId = $waiverId;
        $this->contraAccountId = null;
        $this->debtorAccountId = null;
        $this->resetErrorBag();
    }

    public function approve(): void
    {
        $waiver = FeeWaiver::findOrFail($this->approvingWaiverId);

        if (! $this->canApproveType($waiver->type)) {
            $this->addError('contraAccountId', __('You do not have permission to approve this type.'));

            return;
        }

        $this->validate([
            'contraAccountId' => ['required', 'integer'],
            'debtorAccountId' => ['required', 'integer'],
        ]);

        try {
            app(ApproveFeeWaiverAction::class)->execute(new ApproveFeeWaiverData(
                feeWaiverId: $waiver->id,
                approvedByUserId: (int) Auth::id(),
                contraAccountId: (int) $this->contraAccountId,
                debtorAccountId: (int) $this->debtorAccountId,
            ));
        } catch (DomainException $e) {
            $this->addError('contraAccountId', $e->getMessage());

            return;
        }

        $this->approvingWaiverId = null;
        $this->toast(__('Posted.'));
    }

    public function reject(int $waiverId): void
    {
        $waiver = FeeWaiver::findOrFail($waiverId);

        if (! $this->canApproveType($waiver->type)) {
            $this->toast(__('You do not have permission to reject this type.'), 'danger');

            return;
        }

        app(RejectFeeWaiverAction::class)->execute(new RejectFeeWaiverData($waiverId, (int) Auth::id()));
        $this->toast(__('Rejected.'));
    }

    public function render(): View
    {
        $query = FeeWaiver::query()->with('student')->orderByDesc('id');

        return view('finance::waivers.index', [
            'waivers' => $this->paginateDataTable($query, $this->tableColumns()),
            'invoices' => $this->selectedStudentId !== null
                ? Invoice::where('student_id', $this->selectedStudentId)->where('balance_minor', '>', 0)->get()
                : collect(),
            'accounts' => Account::where('is_postable', true)->orderBy('code')->get(),
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
                'options' => ['waiver' => __('Waiver'), 'write_off' => __('Write-off')],
            ],
            'amount_minor' => ['label' => __('Amount'), 'sortable' => true],
            'reason_code' => ['label' => __('Reason')],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['pending' => __('Pending'), 'posted' => __('Posted'), 'rejected' => __('Rejected')],
            ],
        ];
    }
}
