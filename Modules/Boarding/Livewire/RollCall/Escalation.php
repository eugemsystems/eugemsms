<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\RollCall;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateEscalationProfileAction;
use Modules\Boarding\Domain\Actions\CreateRollCallPointAction;
use Modules\Boarding\Domain\DataObjects\CreateEscalationProfileData;
use Modules\Boarding\Domain\DataObjects\CreateRollCallPointData;
use Modules\Boarding\Domain\DataObjects\EscalationStepInput;
use Modules\Boarding\Models\EscalationProfile;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `RollCall\Escalation` (Book F BRD-02 §6 ⚠, `boarding.rollcall.manage`).
 * Hosts both escalation profiles (with their step ladder, via
 * `CreateEscalationProfileAction` — takes the whole ladder at once,
 * matching `CreateGradingScaleAction`'s own reasoning) and roll call
 * points (`CreateRollCallPointAction`), folded into one screen since
 * the spec's own screen table lists no separate "points" screen and a
 * point always references a profile.
 */
#[Title('Escalation & roll call points')]
#[Layout('layouts.app')]
final class Escalation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $profileName = '';

    public bool $isDefault = false;

    /** @var array<int, array{delayMinutes: int, channels: string, messageTemplateKey: string, requiresAcknowledgement: bool, requiresActionRecord: bool}> */
    public array $steps = [
        ['delayMinutes' => 0, 'channels' => 'push', 'messageTemplateKey' => 'boarding.missing_learner_escalation', 'requiresAcknowledgement' => true, 'requiresActionRecord' => true],
        ['delayMinutes' => 10, 'channels' => 'push,sms', 'messageTemplateKey' => 'boarding.missing_learner_escalation', 'requiresAcknowledgement' => true, 'requiresActionRecord' => true],
        ['delayMinutes' => 20, 'channels' => 'push,sms,call_list', 'messageTemplateKey' => 'boarding.missing_learner_escalation', 'requiresAcknowledgement' => true, 'requiresActionRecord' => false],
        ['delayMinutes' => 30, 'channels' => 'sms', 'messageTemplateKey' => 'boarding.missing_learner_escalation', 'requiresAcknowledgement' => true, 'requiresActionRecord' => false],
        ['delayMinutes' => 45, 'channels' => 'push,sms,email', 'messageTemplateKey' => 'boarding.missing_learner_escalation', 'requiresAcknowledgement' => true, 'requiresActionRecord' => false],
    ];

    public string $pointCode = '';

    public string $pointName = '';

    public string $pointScheduledTime = '21:00';

    public ?int $pointHostelId = null;

    public ?int $pointEscalationProfileId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.rollcall.manage');
    }

    public function createProfile(): void
    {
        $this->validate(['profileName' => ['required', 'string', 'max:120']]);

        $stepInputs = [];

        foreach ($this->steps as $index => $step) {
            $stepInputs[] = new EscalationStepInput(
                stepNumber: $index + 1,
                delayMinutes: (int) $step['delayMinutes'],
                channels: array_filter(array_map('trim', explode(',', $step['channels']))),
                messageTemplateKey: $step['messageTemplateKey'],
                requiresAcknowledgement: (bool) $step['requiresAcknowledgement'],
                requiresActionRecord: (bool) $step['requiresActionRecord'],
            );
        }

        app(CreateEscalationProfileAction::class)->execute(new CreateEscalationProfileData(
            schoolId: $this->school->id,
            name: $this->profileName,
            steps: $stepInputs,
            isDefault: $this->isDefault,
        ));

        $this->reset(['profileName', 'isDefault']);
        $this->toast(__('Escalation profile created.'));
    }

    public function createPoint(): void
    {
        $this->validate([
            'pointCode' => ['required', 'string', 'max:30'],
            'pointName' => ['required', 'string', 'max:80'],
            'pointScheduledTime' => ['required'],
        ]);

        app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
            schoolId: $this->school->id,
            code: $this->pointCode,
            name: $this->pointName,
            scheduledTime: $this->pointScheduledTime,
            appliesOnDays: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            hostelId: $this->pointHostelId,
            escalationProfileId: $this->pointEscalationProfileId,
        ));

        $this->reset(['pointCode', 'pointName']);
        $this->toast(__('Roll call point created.'));
    }

    public function render(): View
    {
        return view('boarding::rollcall.escalation', [
            'profiles' => EscalationProfile::where('school_id', $this->school->id)->with('steps')->get(),
            'points' => RollCallPoint::where('school_id', $this->school->id)->with('hostel', 'escalationProfile')->get(),
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
