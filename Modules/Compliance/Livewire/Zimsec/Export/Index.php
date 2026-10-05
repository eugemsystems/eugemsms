<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Zimsec\Export;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\ExportZimsecRegistrationAction;
use Modules\Compliance\Domain\Actions\RecordZimsecSubmissionAction;
use Modules\Compliance\Domain\DataObjects\ExportZimsecRegistrationData;
use Modules\Compliance\Domain\DataObjects\RecordZimsecSubmissionData;
use Modules\Compliance\Domain\Exceptions\ZimsecExportBlockedException;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Zimsec\Export` (Book H3 CMP-01 §4 ⚠, `zimsec.export`).
 * Folds `RecordZimsecSubmissionAction` into this same screen —
 * BR-CMP-01's own §1 note is literal that submission only happens
 * "once the school has exported", so there is no reason to give it a
 * separate route from the export it depends on. ZIMSEC's own Online
 * Candidate Registration System is where a human actually uploads the
 * file this produces (design note, §1) — this screen prepares and
 * records, never submits anything itself.
 */
#[Title('ZIMSEC export')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $registrationId = 0;

    public bool $acknowledgeWarnings = false;

    public string $zimsecReference = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('zimsec.export');

        $this->registrationId = (int) (ZimsecRegistration::where('school_id', $school->id)->orderByDesc('id')->first()?->id);
    }

    public function export(): void
    {
        $this->authorizePermission('zimsec.export');

        try {
            app(ExportZimsecRegistrationAction::class)->execute(new ExportZimsecRegistrationData(
                registrationId: $this->registrationId,
                exportedByUserId: (int) auth()->id(),
                acknowledgeWarnings: $this->acknowledgeWarnings,
            ));
        } catch (ZimsecExportBlockedException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Registration exported — upload the file through ZIMSEC\'s Online Candidate Registration System.'));
    }

    public function recordSubmission(): void
    {
        $this->authorizePermission('zimsec.export');

        app(RecordZimsecSubmissionAction::class)->execute(new RecordZimsecSubmissionData(
            registrationId: $this->registrationId,
            submittedByUserId: (int) auth()->id(),
            zimsecReference: $this->zimsecReference !== '' ? $this->zimsecReference : null,
        ));

        $this->reset(['zimsecReference']);
        $this->toast(__('Submission recorded.'));
    }

    public function render(): View
    {
        return view('compliance::zimsec.export.index', [
            'registrations' => ZimsecRegistration::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'selected' => $this->registrationId !== 0 ? ZimsecRegistration::find($this->registrationId) : null,
        ]);
    }
}
