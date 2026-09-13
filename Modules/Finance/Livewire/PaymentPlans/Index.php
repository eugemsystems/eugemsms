<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\PaymentPlans;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApprovePaymentPlanAction;
use Modules\Finance\Domain\Actions\CancelPaymentPlanAction;
use Modules\Finance\Domain\Actions\CreatePaymentPlanAction;
use Modules\Finance\Domain\Actions\RecordPaymentPlanInstalmentPaymentAction;
use Modules\Finance\Domain\DataObjects\ApprovePaymentPlanData;
use Modules\Finance\Domain\DataObjects\CancelPaymentPlanData;
use Modules\Finance\Domain\DataObjects\CreatePaymentPlanData;
use Modules\Finance\Domain\DataObjects\RecordPaymentPlanInstalmentPaymentData;
use Modules\Finance\Models\PaymentPlan;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * `Finance\PaymentPlans\Index` (Book B FIN-03 §5/BR-FIN-03-016/017,
 * `finance.payment_plan.create`/`.approve`).
 */
#[Title('Payment plans')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    public ?int $guardianId = null;

    public string $totalAmount = '';

    public string $currency = 'USD';

    public int $instalmentCount = 3;

    public string $firstDueDate = '';

    public ?int $expandedPlanId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.payment_plan.create');
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
    }

    public function openCreateModal(): void
    {
        $this->reset(['selectedStudentId', 'selectedStudentLabel', 'guardianId', 'totalAmount', 'instalmentCount', 'firstDueDate']);
        $this->currency = 'USD';
        $this->instalmentCount = 3;
        $this->firstDueDate = now()->addMonth()->toDateString();
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $this->validate([
            'guardianId' => ['required', 'integer'],
            'totalAmount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'currency' => ['required', 'size:3'],
            'instalmentCount' => ['required', 'integer', 'min:2', 'max:12'],
            'firstDueDate' => ['required', 'date'],
        ]);

        if ($this->selectedStudentId === null) {
            $this->addError('guardianId', __('Select a learner first.'));

            return;
        }

        app(CreatePaymentPlanAction::class)->execute(new CreatePaymentPlanData(
            schoolId: $this->school->id,
            studentId: $this->selectedStudentId,
            partyType: 'guardian',
            partyId: (int) $this->guardianId,
            totalMinor: (int) round((float) $this->totalAmount * 100),
            currency: $this->currency,
            instalmentCount: $this->instalmentCount,
            firstDueDate: Carbon::parse($this->firstDueDate),
            createdByUserId: (int) Auth::id(),
        ));

        $this->showCreateModal = false;
        $this->toast(__('Payment plan proposed.'));
    }

    public function approve(int $planId): void
    {
        $this->authorizePermission('finance.payment_plan.approve');

        try {
            app(ApprovePaymentPlanAction::class)->execute(new ApprovePaymentPlanData($planId, (int) Auth::id()));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Payment plan approved.'));
    }

    public function cancel(int $planId): void
    {
        $this->authorizePermission('finance.payment_plan.approve');

        try {
            app(CancelPaymentPlanAction::class)->execute(new CancelPaymentPlanData($planId));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Payment plan cancelled.'));
    }

    public function toggleExpand(int $planId): void
    {
        $this->expandedPlanId = $this->expandedPlanId === $planId ? null : $planId;
    }

    public function recordInstalmentPayment(int $instalmentId, string $amount): void
    {
        $this->authorizePermission('finance.payment_plan.approve');

        app(RecordPaymentPlanInstalmentPaymentAction::class)->execute(new RecordPaymentPlanInstalmentPaymentData(
            instalmentId: $instalmentId,
            paidMinor: (int) round((float) $amount * 100),
        ));

        $this->toast(__('Instalment payment recorded.'));
    }

    public function render(): View
    {
        $query = PaymentPlan::query()->with('student', 'instalments')->orderByDesc('id');

        return view('finance::payment-plans.index', [
            'plans' => $this->paginateDataTable($query, $this->tableColumns()),
            'guardians' => $this->selectedStudentId !== null ? Guardian::orderBy('first_name')->get() : collect(),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'total_minor' => ['label' => __('Total'), 'sortable' => true],
            'instalment_count' => ['label' => __('Instalments')],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['proposed' => __('Proposed'), 'active' => __('Active'), 'completed' => __('Completed'), 'breached' => __('Breached'), 'cancelled' => __('Cancelled')],
            ],
        ];
    }
}
