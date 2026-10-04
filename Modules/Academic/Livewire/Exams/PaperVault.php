<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ReleaseExaminationPaperAction;
use Modules\Academic\Domain\Actions\SealExaminationPaperAction;
use Modules\Academic\Domain\Actions\VetExaminationPaperAction;
use Modules\Academic\Domain\DataObjects\ReleaseExaminationPaperData;
use Modules\Academic\Domain\DataObjects\SealExaminationPaperData;
use Modules\Academic\Domain\DataObjects\VetExaminationPaperData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\DataAccessLogEntry;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Exams\PaperVault` (Book E ACA-07 §3 ⭐⭐, `academic.exams.paper_manage`
 * ⚠⚠). One lifecycle screen hosting vet → seal → release, with the
 * access log for every release call (`data_access_log`). Per
 * `ReleaseExaminationPaperAction`'s own docblock: `release_at` is
 * checked server-side with no override path for anyone, including a
 * Super Admin — this screen has no override control to offer because
 * none exists in the Action it calls. Per-session encryption at rest and
 * visible watermarking are deliberately deferred (no `CORE-10` capability
 * exists yet for either) — downloading here only proves the gate; it
 * does not stream a watermarked file.
 */
#[Title('Secure paper vault')]
#[Layout('layouts.app')]
final class PaperVault extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $sessionId = null;

    /** @var array<int, string> */
    public array $releaseAt = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.paper_manage');
    }

    public function vet(int $paperId): void
    {
        $staffId = Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        try {
            app(VetExaminationPaperAction::class)->execute(new VetExaminationPaperData(
                paperId: $paperId,
                vettedByStaffId: $staffId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Paper vetted.'));
    }

    public function seal(int $paperId): void
    {
        $releaseAt = $this->releaseAt[$paperId] ?? '';

        if ($releaseAt === '') {
            $this->toast(__('A release date/time is required to seal a paper.'), 'danger');

            return;
        }

        try {
            app(SealExaminationPaperAction::class)->execute(new SealExaminationPaperData(
                paperId: $paperId,
                releaseAt: Carbon::parse($releaseAt),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Paper sealed — one-way, cannot return to draft.'));
    }

    public function release(int $paperId): void
    {
        try {
            app(ReleaseExaminationPaperAction::class)->execute(new ReleaseExaminationPaperData(
                paperId: $paperId,
                requestedByUserId: (int) Auth::id(),
                ip: request()->ip(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Paper released — access logged.'));
    }

    public function render(): View
    {
        $papers = $this->sessionId !== null
            ? ExaminationPaper::where('session_id', $this->sessionId)->with('subject', 'gradeLevel', 'setterStaff', 'vettedBy')->get()
            : collect();

        return view('academic::exams.paper-vault', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'papers' => $papers,
            'accessLog' => DataAccessLogEntry::where('school_id', $this->school->id)
                ->where('resource_type', 'examination_paper')
                ->whereIn('resource_id', $papers->pluck('id'))
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ]);
    }
}
