<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Consents;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\RecordConsentAction;
use Modules\Compliance\Domain\Actions\WithdrawConsentAction;
use Modules\Compliance\Domain\DataObjects\RecordConsentData;
use Modules\Compliance\Domain\DataObjects\WithdrawConsentData;
use Modules\Compliance\Domain\Exceptions\ConsentNotWithdrawableException;
use Modules\Compliance\Models\Consent;
use Modules\Compliance\Models\ConsentType;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Consents` (Book H3 CMP-03 §4 — spec names
 * `privacy.view` for this screen's own register read; recording and
 * withdrawing a consent are real writes this pass gates behind
 * `privacy.manage` instead, the same two-tier split this codebase
 * already uses elsewhere between viewing a register and mutating it).
 * Append-only (BR-CMP-03-001) — withdrawal sets state on the SAME row,
 * never a new row or a deletion; the model's own guard enforces this,
 * this screen just never offers anything else.
 */
#[Title('Consent register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $consentTypeId = 0;

    public string $subjectType = 'student';

    public string $subjectId = '';

    public string $grantedByType = 'guardian';

    public string $grantedById = '';

    public bool $granted = true;

    public string $method = 'portal';

    public ?int $withdrawingConsentId = null;

    public string $withdrawalReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.view');
    }

    public function record(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->validate([
            'consentTypeId' => ['required', 'integer', 'min:1'],
            'subjectType' => ['required', 'in:student,guardian,staff'],
            'subjectId' => ['required', 'integer', 'min:1'],
            'grantedByType' => ['required', 'in:guardian,self'],
            'grantedById' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:portal,paper_form,verbal_witnessed'],
        ]);

        app(RecordConsentAction::class)->execute(new RecordConsentData(
            schoolId: $this->school->id,
            consentTypeId: $this->consentTypeId,
            subjectType: $this->subjectType,
            subjectId: (int) $this->subjectId,
            grantedByType: $this->grantedByType,
            grantedById: (int) $this->grantedById,
            granted: $this->granted,
            method: $this->method,
        ));

        $this->reset(['subjectId', 'grantedById']);
        $this->toast(__('Consent recorded.'));
    }

    public function startWithdraw(int $consentId): void
    {
        $this->withdrawingConsentId = $consentId;
        $this->withdrawalReason = '';
    }

    public function withdraw(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->validate(['withdrawalReason' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            app(WithdrawConsentAction::class)->execute(new WithdrawConsentData(
                consentId: (int) $this->withdrawingConsentId,
                withdrawnByUserId: (int) auth()->id(),
                withdrawalReason: $this->withdrawalReason,
            ));
        } catch (ConsentNotWithdrawableException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->withdrawingConsentId = null;
        $this->reset(['withdrawalReason']);
        $this->toast(__('Consent withdrawn — effective immediately across every module.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.consents.index', [
            'consents' => Consent::where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'consentTypes' => ConsentType::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
