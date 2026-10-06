<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Alumni\Directory;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\OfferAlumniPortalAccountAction;
use Modules\People\Domain\Actions\OptOutAlumniContactAction;
use Modules\People\Domain\Actions\RecordCareerUpdateAction;
use Modules\People\Domain\Actions\VerifyCareerUpdateAction;
use Modules\People\Domain\DataObjects\OfferAlumniPortalAccountData;
use Modules\People\Domain\DataObjects\OptOutAlumniContactData;
use Modules\People\Domain\DataObjects\RecordCareerUpdateData;
use Modules\People\Domain\DataObjects\VerifyCareerUpdateData;
use Modules\People\Models\AlumniCareerUpdate;
use Modules\People\Models\Alumnus;
use Modules\People\Models\Donation;
use Modules\People\Models\Pledge;
use Modules\People\Models\Student;

/**
 * `Alumni\Directory\Show` (Book K PPL-06 §5, `alumni.view`). The frozen
 * academic summary (read once at graduation and never recomputed —
 * BR-PPL-06-002), self-reported career history marked verified or not
 * (BR-PPL-06-004) and giving history. Opting an alumnus out of contact is
 * recorded here at their request; opting them BACK IN is deliberately not
 * offered — only the alumnus can do that (BR-PPL-06-011). The alumnus id is
 * re-resolved through the school scope on every call.
 */
#[Title('Alumnus')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $alumnusId;

    public string $updateType = 'employment';

    public string $title = '';

    public string $institution = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $isCurrent = false;

    public string $optOutReason = '';

    public string $portalEmail = '';

    public function mount(School $school, int $alumnus): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('alumni.view');

        $this->alumnusId = Alumnus::query()->findOrFail($alumnus)->id;
    }

    public function addUpdate(): void
    {
        $this->authorizePermission('alumni.career.verify');
        $this->resetErrorBag();

        $this->validate(['title' => ['required', 'string', 'max:200'], 'institution' => ['nullable', 'string', 'max:200'], 'startsOn' => ['nullable', 'date'], 'endsOn' => ['nullable', 'date']]);

        $alumnus = Alumnus::query()->findOrFail($this->alumnusId);

        try {
            app(RecordCareerUpdateAction::class)->execute(new RecordCareerUpdateData(
                alumnusId: $alumnus->id, updateType: $this->updateType, title: $this->title,
                institutionOrEmployer: $this->institution === '' ? null : $this->institution,
                startsOn: $this->startsOn === '' ? null : Carbon::parse($this->startsOn),
                endsOn: $this->endsOn === '' ? null : Carbon::parse($this->endsOn), isCurrent: $this->isCurrent,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->reset('title', 'institution', 'startsOn', 'endsOn', 'isCurrent');
        $this->toast(__('Recorded as unverified.'));
    }

    public function verify(int $updateId): void
    {
        $this->authorizePermission('alumni.career.verify');

        $update = AlumniCareerUpdate::query()->where('alumnus_id', $this->alumnusId)->findOrFail($updateId);

        app(VerifyCareerUpdateAction::class)->execute(new VerifyCareerUpdateData($update->id));

        $this->toast(__('Confirmed.'));
    }

    public function optOut(): void
    {
        $this->authorizePermission('alumni.contact.manage');

        $alumnus = Alumnus::query()->findOrFail($this->alumnusId);

        app(OptOutAlumniContactAction::class)->execute(new OptOutAlumniContactData($alumnus->id, $this->optOutReason === '' ? null : $this->optOutReason));

        $this->optOutReason = '';
        $this->toast(__('Recorded. No further outreach will be sent until they opt back in themselves.'));
    }

    public function offerPortal(): void
    {
        $this->authorizePermission('alumni.contact.manage');
        $this->resetErrorBag();

        $this->validate(['portalEmail' => ['required', 'email', 'max:150']]);

        $alumnus = Alumnus::query()->findOrFail($this->alumnusId);

        try {
            app(OfferAlumniPortalAccountAction::class)->execute(new OfferAlumniPortalAccountData($alumnus->id, $this->portalEmail));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('portalEmail', $exception->getMessage());

            return;
        }

        $this->portalEmail = '';
        $this->toast(__('Portal account created.'));
    }

    public function render(): View
    {
        $alumnus = Alumnus::query()->with(['finalGradeLevel', 'finalHouse'])->findOrFail($this->alumnusId);
        $snapshot = $alumnus->academic_summary_snapshot;
        $pledges = Pledge::query()->where('alumnus_id', $alumnus->id)->orderByDesc('id')->get();
        $user = auth()->user();
        $resolver = app(PermissionScopeResolver::class);

        return view('people::alumni.show', [
            'alumnus' => $alumnus,
            'student' => Student::query()->find($alumnus->student_id),
            'snapshot' => $snapshot,
            'termNames' => Term::query()->whereIn('id', array_column($snapshot['terms'] ?? [], 'term_id'))->pluck('name', 'id'),
            'updates' => AlumniCareerUpdate::query()->where('alumnus_id', $alumnus->id)->orderByDesc('submitted_at')->get(),
            'pledges' => $pledges,
            'donations' => Donation::query()->whereIn('pledge_id', $pledges->pluck('id'))->orderByDesc('received_at')->get(),
            'canVerify' => $user !== null && $resolver->has($user, 'alumni.career.verify', PermissionScope::Own),
            'canManageContact' => $user !== null && $resolver->has($user, 'alumni.contact.manage', PermissionScope::Own),
        ]);
    }
}
