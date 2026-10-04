<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Exeats;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\RequestExeatAction;
use Modules\Boarding\Domain\DataObjects\RequestExeatData;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * `Exeats\Index` (Book F BRD-03 §6 ⭐, `boarding.exeat.view` to
 * browse, `boarding.exeat.request` to request). List + create — the
 * staff-facing stand-in for the unbuilt guardian portal/app request
 * form, recorded with `request_source = phone_recorded` and the
 * recording staff member's own identity, matching BR-BRD-03-001's own
 * exception for staff-initiated guardian-required types.
 */
#[Title('Exeat requests')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $studentId = null;

    public ?int $exeatTypeId = null;

    public string $reason = '';

    public string $departsAt = '';

    public string $returnsBy = '';

    public string $destinationAddress = '';

    public string $destinationProvince = '';

    public string $contactPhone = '';

    public string $collectionMethod = 'guardian_collect';

    public ?int $collectingGuardianId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.exeat.view');
        $this->departsAt = now()->format('Y-m-d\TH:i');
        $this->returnsBy = now()->addDay()->format('Y-m-d\TH:i');
    }

    public function create(): void
    {
        $this->authorizePermission('boarding.exeat.request');

        $this->validate([
            'studentId' => ['required', 'integer'],
            'exeatTypeId' => ['required', 'integer'],
            'reason' => ['required', 'string'],
            'departsAt' => ['required', 'date'],
            'returnsBy' => ['required', 'date', 'after:departsAt'],
            'destinationAddress' => ['required', 'string'],
            'destinationProvince' => ['required', 'string'],
            'contactPhone' => ['required', 'string'],
        ]);

        $year = $this->school->currentAcademicYear();
        $term = $year?->currentTerm();

        if ($year === null || $term === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(RequestExeatAction::class)->execute(new RequestExeatData(
                schoolId: $this->school->id,
                academicYearId: $year->id,
                termId: $term->id,
                studentId: (int) $this->studentId,
                exeatTypeId: (int) $this->exeatTypeId,
                reason: $this->reason,
                departsAt: Carbon::parse($this->departsAt),
                returnsBy: Carbon::parse($this->returnsBy),
                destinationAddress: $this->destinationAddress,
                destinationProvince: $this->destinationProvince,
                contactPhone: $this->contactPhone,
                collectionMethod: $this->collectionMethod,
                requestSource: 'phone_recorded',
                requestedByUserId: (int) Auth::id(),
                collectingGuardianId: $this->collectingGuardianId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['studentId', 'exeatTypeId', 'reason', 'destinationAddress', 'destinationProvince', 'contactPhone', 'collectingGuardianId']);
        $this->toast(__('Exeat requested — pending approval.'));
    }

    public function render(): View
    {
        $guardians = $this->studentId !== null
            ? StudentGuardian::where('student_id', $this->studentId)->where('status', 'active')->with('guardian')->get()
            : collect();

        return view('boarding::exeats.index', [
            'exeats' => Exeat::where('school_id', $this->school->id)->with('student', 'exeatType')->orderByDesc('id')->limit(50)->get(),
            'exeatTypes' => ExeatType::where('school_id', $this->school->id)->where('is_active', true)->get(),
            'students' => Student::where('school_id', $this->school->id)->whereIn('residency', ['BOARDER', 'WEEKLY_BOARDER'])->orderBy('first_name')->limit(300)->get(),
            'guardians' => $guardians,
        ]);
    }
}
