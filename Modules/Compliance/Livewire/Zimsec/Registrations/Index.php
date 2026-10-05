<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Zimsec\Registrations;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\CloseZimsecRegistrationAction;
use Modules\Compliance\Domain\Actions\CreateZimsecRegistrationAction;
use Modules\Compliance\Domain\Actions\DeriveZimsecCandidatesAction;
use Modules\Compliance\Domain\DataObjects\CreateZimsecRegistrationData;
use Modules\Compliance\Domain\Exceptions\ZimsecFeeShortfallException;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * `Compliance\Zimsec\Registrations` (Book H3 CMP-01 §4, `zimsec.manage`).
 * One row per exam level/series (BR-CMP-01-013's centre number lives
 * here too). Hosts the registration-level lifecycle actions that don't
 * need their own screen: deriving candidates from `ACA-07`'s confirmed
 * `examination_candidates` (`DeriveZimsecCandidatesAction`, idempotent
 * — re-running only adds new candidates) and closing a registration
 * (`CloseZimsecRegistrationAction`, refused with a named shortfall when
 * fees billed exceed collected, BR-CMP-01-007/AC-CMP-01-002). The
 * deadline countdown is computed here directly from
 * `registration_closes_on` rather than calling `CheckZimsecDeadlinesAction`
 * — that action's own job is firing scheduled alert events, not
 * rendering a number on a screen.
 */
#[Title('ZIMSEC registrations')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $academicYearId = 0;

    public string $examLevel = 'o_level';

    public string $examSeries = '';

    public string $centreNumber = '';

    public string $registrationOpensOn = '';

    public string $registrationClosesOn = '';

    public string $currency = 'USD';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('zimsec.manage');

        $this->academicYearId = (int) ($this->school->currentAcademicYear()?->id);
    }

    public function create(): void
    {
        $this->authorizePermission('zimsec.manage');

        $this->validate([
            'academicYearId' => ['required', 'integer'],
            'examLevel' => ['required', 'in:grade_7,o_level,a_level'],
            'examSeries' => ['required', 'string', 'max:20'],
            'centreNumber' => ['required', 'string', 'max:20'],
            'registrationClosesOn' => ['required', 'date'],
            'currency' => ['required', 'in:USD,ZWG'],
        ]);

        app(CreateZimsecRegistrationAction::class)->execute(new CreateZimsecRegistrationData(
            schoolId: $this->school->id,
            academicYearId: $this->academicYearId,
            examLevel: $this->examLevel,
            examSeries: $this->examSeries,
            centreNumber: $this->centreNumber,
            registrationClosesOn: Carbon::parse($this->registrationClosesOn),
            currency: $this->currency,
            registrationOpensOn: $this->registrationOpensOn !== '' ? Carbon::parse($this->registrationOpensOn) : null,
        ));

        $this->reset(['examSeries', 'centreNumber', 'registrationOpensOn', 'registrationClosesOn']);
        $this->toast(__('Registration created.'));
    }

    public function deriveCandidates(int $registrationId): void
    {
        $this->authorizePermission('zimsec.manage');

        $derived = app(DeriveZimsecCandidatesAction::class)->execute($registrationId);

        $this->toast(__(':count candidate(s) derived from confirmed ACA-07 entries.', ['count' => $derived->count()]));
    }

    public function close(int $registrationId): void
    {
        $this->authorizePermission('zimsec.manage');

        try {
            app(CloseZimsecRegistrationAction::class)->execute($registrationId);
        } catch (ZimsecFeeShortfallException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Registration closed.'));
    }

    public function render(): View
    {
        $registrations = ZimsecRegistration::where('school_id', $this->school->id)
            ->orderByDesc('registration_closes_on')
            ->get();

        return view('compliance::zimsec.registrations.index', [
            'registrations' => $registrations,
            'academicYears' => AcademicYear::where('school_id', $this->school->id)->orderByDesc('starts_on')->get(),
        ]);
    }
}
