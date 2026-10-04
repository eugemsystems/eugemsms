<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Gate;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\RecordDepartureAction;
use Modules\Boarding\Domain\Actions\RecordReturnAction;
use Modules\Boarding\Domain\DataObjects\CollectionClaim;
use Modules\Boarding\Domain\DataObjects\RecordDepartureData;
use Modules\Boarding\Domain\DataObjects\RecordReturnData;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Gate\Terminal` (Book F BRD-03 §3/§6 ⭐⭐, `boarding.gate.operate`).
 * "The single most important twenty lines in this book" — the
 * collection authority check runs on *every* departure with no fast
 * path and no trusted-parent bypass (BR-BRD-03-011). The result
 * renders as one unambiguous word, large and colour-coded, per the
 * spec's own instruction: "a gatekeeper reading a dense screen at
 * dusk with a queue of cars behind is the failure mode this design
 * guards against." Every path through the check — release or
 * refusal — writes an append-only `collection_attempts` row before
 * this screen ever sees the result; there is no way to retry past a
 * refusal from here, because `RecordDepartureAction` itself has no
 * override parameter.
 */
#[Title('Gate terminal')]
#[Layout('layouts.app')]
final class Terminal extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $verificationCode = '';

    public ?Exeat $foundExeat = null;

    public string $claimName = '';

    public ?int $claimGuardianId = null;

    public string $claimIdNo = '';

    public string $claimRelationship = '';

    public bool $identityVerified = false;

    public ?string $lastResult = null;

    public ?string $lastReason = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.gate.operate');
    }

    public function lookup(): void
    {
        $this->foundExeat = Exeat::where('school_id', $this->school->id)
            ->where('verification_code', $this->verificationCode)
            ->first();

        if ($this->foundExeat === null) {
            $this->toast(__('No exeat found for that code.'), 'danger');
        }
    }

    public function checkDeparture(): void
    {
        if ($this->foundExeat === null) {
            return;
        }

        $attempt = app(RecordDepartureAction::class)->execute(new RecordDepartureData(
            exeatId: $this->foundExeat->id,
            claim: new CollectionClaim(
                name: $this->claimName,
                guardianId: $this->claimGuardianId,
                idNo: $this->claimIdNo !== '' ? $this->claimIdNo : null,
                claimedRelationship: $this->claimRelationship !== '' ? $this->claimRelationship : null,
                identityVerified: $this->identityVerified,
            ),
            gateStaffUserId: (int) Auth::id(),
        ));

        $this->lastResult = $attempt->outcome === 'released' ? 'RELEASE' : 'DO NOT RELEASE';
        $this->lastReason = $attempt->refusal_reason;

        $this->foundExeat->refresh();
        $this->reset(['claimName', 'claimGuardianId', 'claimIdNo', 'claimRelationship', 'identityVerified']);
    }

    public function recordReturn(): void
    {
        if ($this->foundExeat === null) {
            return;
        }

        app(RecordReturnAction::class)->execute(new RecordReturnData(
            exeatId: $this->foundExeat->id,
            recordedByUserId: (int) Auth::id(),
        ));

        $this->foundExeat->refresh();
        $this->toast(__('Return recorded.'));
    }

    public function resetLookup(): void
    {
        $this->reset(['verificationCode', 'foundExeat', 'lastResult', 'lastReason']);
    }

    public function render(): View
    {
        return view('boarding::gate.terminal', [
            'recentAttempts' => $this->foundExeat !== null
                ? CollectionAttempt::where('exeat_id', $this->foundExeat->id)->orderByDesc('occurred_at')->get()
                : collect(),
        ]);
    }
}
