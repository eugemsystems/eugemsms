<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Library;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ApproveAcquisitionRequestAction;
use Modules\Academic\Domain\Actions\CreateAcquisitionRequestAction;
use Modules\Academic\Domain\Actions\RejectAcquisitionRequestAction;
use Modules\Academic\Domain\DataObjects\ApproveAcquisitionRequestData;
use Modules\Academic\Domain\DataObjects\CreateAcquisitionRequestData;
use Modules\Academic\Domain\DataObjects\RejectAcquisitionRequestData;
use Modules\Academic\Livewire\Concerns\ChecksPermissions;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;

/**
 * `Academic\Library\Acquisitions` (Book K ACA-10 §5,
 * `library.acquisition.request`). Anyone with the permission can ask for a
 * title; approving needs `library.acquisition.approve` and hands the request
 * to FIN-08 as a purchase requisition (BR-ACA-10-010) — the library runs no
 * purchasing of its own.
 */
#[Title('Library acquisitions')]
#[Layout('layouts.app')]
final class Acquisitions extends Component
{
    use AuthorizesPermissions;
    use ChecksPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $requestedTitle = '';

    public int $copies = 1;

    public string $estimatedCost = '';

    public ?int $approvingId = null;

    public ?int $departmentId = null;

    public ?int $costCentreId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('library.acquisition.request');
    }

    public function submit(): void
    {
        $this->authorizePermission('library.acquisition.request');
        $this->resetErrorBag();
        $this->validate(['requestedTitle' => ['required', 'string', 'max:300'], 'copies' => ['required', 'integer', 'min:1', 'max:1000'], 'estimatedCost' => ['nullable', 'numeric', 'min:0']]);

        try {
            app(CreateAcquisitionRequestAction::class)->execute(new CreateAcquisitionRequestData(
                schoolId: $this->school->id, requestedTitle: $this->requestedTitle, requestedByUserId: (int) auth()->id(), copiesRequested: $this->copies,
                estimatedCostMinor: $this->estimatedCost === '' ? null : (int) round((float) $this->estimatedCost * 100),
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('requestedTitle', $exception->getMessage());

            return;
        }

        $this->reset('requestedTitle', 'estimatedCost');
        $this->copies = 1;
        $this->toast(__('Request submitted.'));
    }

    public function startApproval(int $requestId): void
    {
        $this->authorizePermission('library.acquisition.approve');
        $this->approvingId = AcquisitionRequest::query()->where('status', 'requested')->whereKey($requestId)->value('id');
    }

    public function approve(): void
    {
        $this->authorizePermission('library.acquisition.approve');
        $this->resetErrorBag();

        $request = $this->approvingId === null ? null : AcquisitionRequest::query()->find($this->approvingId);
        $term = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->first();

        if ($request === null || $term === null || $this->departmentId === null || $this->costCentreId === null) {
            $this->addError('departmentId', __('Choose a department and cost centre.'));

            return;
        }

        try {
            app(ApproveAcquisitionRequestAction::class)->execute(new ApproveAcquisitionRequestData(
                requestId: $request->id, approvedByUserId: (int) auth()->id(), academicYearId: $term->academic_year_id, termId: $term->id,
                departmentId: $this->departmentId, costCentreId: $this->costCentreId, currency: $this->school->base_currency,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('departmentId', $exception->getMessage());

            return;
        }

        $this->reset('approvingId', 'departmentId', 'costCentreId');
        $this->toast(__('Approved and sent to procurement as a purchase requisition.'));
    }

    public function reject(int $requestId): void
    {
        $this->authorizePermission('library.acquisition.approve');

        $request = AcquisitionRequest::query()->find($requestId);

        if ($request === null) {
            return;
        }

        try {
            app(RejectAcquisitionRequestAction::class)->execute(new RejectAcquisitionRequestData($request->id));
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Request rejected.'));
    }

    public function render(): View
    {
        $canApprove = $this->holds('library.acquisition.approve');

        return view('academic::library.acquisitions', [
            'requests' => AcquisitionRequest::query()->with('requestedBy')->orderByDesc('id')->limit(50)->get(),
            'canApprove' => $canApprove,
            'departments' => $canApprove ? Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'costCentres' => $canApprove ? CostCentre::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
