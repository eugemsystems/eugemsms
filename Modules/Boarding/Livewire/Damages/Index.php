<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Damages;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\ApproveHostelDamageChargeAction;
use Modules\Boarding\Domain\Actions\DisputeHostelDamageChargeAction;
use Modules\Boarding\Domain\Actions\ReportHostelDamageAction;
use Modules\Boarding\Domain\DataObjects\ApproveHostelDamageChargeData;
use Modules\Boarding\Domain\DataObjects\DisputeHostelDamageChargeData;
use Modules\Boarding\Domain\DataObjects\ReportHostelDamageData;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelDamage;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * `Damages\Index` (Book F BRD-01 §5, `boarding.damage.view` to
 * browse, `boarding.damage.manage` to report, `boarding.damage.approve_charge`
 * ⚠ to approve/dispute). One lifecycle screen hosting report → approve
 * → dispute — damage is never charged silently (BR-BRD-01-012):
 * `ApproveHostelDamageChargeAction`'s own largest-remainder split is
 * what AC-BRD-01-005 tests, not anything computed in this screen.
 */
#[Title('Hostel damages')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $hostelId = null;

    public string $damageType = 'furniture';

    public string $description = '';

    public string $liability = 'shared_room';

    /** @var array<int, int> */
    public array $liableStudentIds = [];

    public ?int $estimatedCostMinor = null;

    public ?int $approveDamageId = null;

    public ?int $feeComponentId = null;

    public ?int $actualCostMinor = null;

    public ?int $disputeDamageId = null;

    public string $disputeReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('boarding.damage.view');
    }

    public function report(): void
    {
        $this->authorizePermission('boarding.damage.manage');

        if ($this->hostelId === null || trim($this->description) === '') {
            $this->toast(__('Hostel and description are required.'), 'danger');

            return;
        }

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(ReportHostelDamageAction::class)->execute(new ReportHostelDamageData(
            schoolId: $this->school->id,
            termId: $term->id,
            hostelId: $this->hostelId,
            damageType: $this->damageType,
            description: $this->description,
            liability: $this->liability,
            currency: $this->school->base_currency,
            reportedByUserId: (int) Auth::id(),
            reportedAt: Carbon::now(),
            liableStudentIds: $this->liableStudentIds !== [] ? $this->liableStudentIds : null,
            estimatedCostMinor: $this->estimatedCostMinor,
        ));

        $this->reset(['description', 'liableStudentIds', 'estimatedCostMinor']);
        $this->toast(__('Damage reported — pending approval before any charge is raised.'));
    }

    public function approve(int $damageId): void
    {
        $this->authorizePermission('boarding.damage.approve_charge');

        if ($this->feeComponentId === null || $this->actualCostMinor === null) {
            $this->toast(__('A fee component and actual cost are required to approve.'), 'danger');

            return;
        }

        try {
            app(ApproveHostelDamageChargeAction::class)->execute(new ApproveHostelDamageChargeData(
                damageId: $damageId,
                feeComponentId: $this->feeComponentId,
                actualCostMinor: $this->actualCostMinor,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['approveDamageId', 'feeComponentId', 'actualCostMinor']);
        $this->toast(__('Charge approved and raised through FIN-02.'));
    }

    public function dispute(int $damageId): void
    {
        $this->authorizePermission('boarding.damage.approve_charge');

        if (trim($this->disputeReason) === '') {
            $this->toast(__('A dispute reason is required.'), 'danger');

            return;
        }

        try {
            app(DisputeHostelDamageChargeAction::class)->execute(new DisputeHostelDamageChargeData(
                damageId: $damageId,
                disputeReason: $this->disputeReason,
                disputedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['disputeDamageId', 'disputeReason']);
        $this->toast(__('Charge disputed — billing halted until resolved.'));
    }

    public function render(): View
    {
        return view('boarding::damages.index', [
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
            'damages' => HostelDamage::where('school_id', $this->school->id)->with('hostel')->orderByDesc('reported_at')->limit(50)->get(),
            'feeComponents' => FeeComponent::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name']),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
        ]);
    }
}
