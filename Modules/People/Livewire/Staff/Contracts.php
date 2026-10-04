<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateStaffContractAction;
use Modules\People\Domain\Actions\RenewStaffContractAction;
use Modules\People\Domain\Actions\TerminateStaffContractAction;
use Modules\People\Domain\DataObjects\CreateStaffContractData;
use Modules\People\Domain\DataObjects\RenewStaffContractData;
use Modules\People\Domain\DataObjects\TerminateStaffContractData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffContract;

/**
 * `People\Staff\Contracts` (Book C PPL-04 §5/BR-PPL-04-002,
 * `people.staff.contract_manage`). One active contract at a time —
 * renewal and termination both act on that single active row, so this
 * screen never offers more than one of create/renew/terminate at once.
 */
#[Title('Staff contracts')]
#[Layout('layouts.app')]
final class Contracts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Staff $staff;

    public string $contractType = 'permanent';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $noticePeriodDays = '30';

    public string $basicSalaryAmount = '';

    public string $salaryCurrency = 'USD';

    public string $terminatedOn = '';

    public string $terminationReason = '';

    public function mount(School $school, Staff $staff): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.contract_manage');

        abort_unless($staff->school_id === $school->id, 404);

        $this->staff = $staff;
        $this->startsOn = now()->toDateString();
        $this->terminatedOn = now()->toDateString();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function sharedValidation(): array
    {
        return [
            'contractType' => ['required', 'in:permanent,fixed_term,relief,part_time,attachment,volunteer'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['nullable', 'date', 'after:startsOn'],
            'noticePeriodDays' => ['required', 'integer', 'min:0'],
            'basicSalaryAmount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    public function create(): void
    {
        $this->validate($this->sharedValidation());

        try {
            app(CreateStaffContractAction::class)->execute(new CreateStaffContractData(
                schoolId: $this->school->id,
                staffId: $this->staff->id,
                contractType: $this->contractType,
                startsOn: Carbon::parse($this->startsOn),
                createdByUserId: (int) Auth::id(),
                endsOn: $this->endsOn !== '' ? Carbon::parse($this->endsOn) : null,
                noticePeriodDays: (int) $this->noticePeriodDays,
                basicSalaryMinor: $this->basicSalaryAmount !== '' ? (int) round((float) $this->basicSalaryAmount * 100) : null,
                salaryCurrency: $this->basicSalaryAmount !== '' ? $this->salaryCurrency : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Contract created.'));
    }

    public function renew(int $contractId): void
    {
        $this->validate($this->sharedValidation());

        try {
            app(RenewStaffContractAction::class)->execute(new RenewStaffContractData(
                contractId: $contractId,
                contractType: $this->contractType,
                startsOn: Carbon::parse($this->startsOn),
                renewedByUserId: (int) Auth::id(),
                endsOn: $this->endsOn !== '' ? Carbon::parse($this->endsOn) : null,
                noticePeriodDays: (int) $this->noticePeriodDays,
                basicSalaryMinor: $this->basicSalaryAmount !== '' ? (int) round((float) $this->basicSalaryAmount * 100) : null,
                salaryCurrency: $this->basicSalaryAmount !== '' ? $this->salaryCurrency : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Contract renewed.'));
    }

    public function terminate(int $contractId): void
    {
        $this->validate(['terminatedOn' => ['required', 'date'], 'terminationReason' => ['required', 'string', 'max:120']]);

        try {
            app(TerminateStaffContractAction::class)->execute(new TerminateStaffContractData(
                contractId: $contractId,
                terminatedOn: Carbon::parse($this->terminatedOn),
                terminationReason: $this->terminationReason,
                terminatedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->terminationReason = '';
        $this->toast(__('Contract terminated.'));
    }

    public function render(): View
    {
        return view('people::staff.contracts', [
            'contracts' => StaffContract::where('staff_id', $this->staff->id)->orderByDesc('id')->get(),
        ]);
    }
}
