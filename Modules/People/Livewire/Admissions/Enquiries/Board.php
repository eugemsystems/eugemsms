<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Enquiries;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\AdvanceEnquiryStageAction;
use Modules\People\Domain\Actions\CreateEnquiryAction;
use Modules\People\Domain\Actions\LogEnquiryActivityAction;
use Modules\People\Domain\DataObjects\AdvanceEnquiryStageData;
use Modules\People\Domain\DataObjects\CreateEnquiryData;
use Modules\People\Domain\DataObjects\LogEnquiryActivityData;
use Modules\People\Models\Enquiry;
use Modules\People\Models\EnquiryActivity;

/**
 * `Admissions\Enquiries\Board` (Book C PPL-02 §5, `people.admissions.enquiry_manage`).
 * The enquiry pipeline by stage, with who owns each and when to follow up. An
 * enquiry only moves forward, or is marked lost with a reason.
 */
#[Title('Enquiry pipeline')]
#[Layout('layouts.app')]
final class Board extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $source = 'walk_in';

    public string $enquirerName = '';

    public string $phone = '';

    public string $email = '';

    public string $learnerName = '';

    public ?int $openId = null;

    public string $activityType = 'call';

    public string $summary = '';

    public string $followUp = '';

    public string $lostReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.enquiry_manage');
    }

    public function create(): void
    {
        $this->authorizePermission('people.admissions.enquiry_manage');
        $this->resetErrorBag();

        try {
            app(CreateEnquiryAction::class)->execute(new CreateEnquiryData(
                schoolId: $this->school->id, source: $this->source, enquirerName: $this->enquirerName, enquirerPhone: $this->phone === '' ? null : $this->phone,
                enquirerEmail: $this->email === '' ? null : $this->email, learnerName: $this->learnerName === '' ? null : $this->learnerName, assignedTo: (int) auth()->id(),
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('enquirerName', $exception->getMessage());

            return;
        }

        $this->reset('enquirerName', 'phone', 'email', 'learnerName');
        $this->toast(__('Enquiry added.'));
    }

    public function open(int $enquiryId): void
    {
        $this->openId = Enquiry::query()->whereKey($enquiryId)->value('id');
        $this->reset('summary', 'followUp', 'lostReason');
    }

    public function log(): void
    {
        $this->authorizePermission('people.admissions.enquiry_manage');
        $this->resetErrorBag();

        if ($this->openId === null) {
            return;
        }

        try {
            app(LogEnquiryActivityAction::class)->execute(new LogEnquiryActivityData($this->openId, $this->activityType, $this->summary, (int) auth()->id(), nextFollowUpOn: $this->followUp === '' ? null : Carbon::parse($this->followUp)));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('summary', $exception->getMessage());

            return;
        }

        $this->reset('summary', 'followUp');
        $this->toast(__('Logged.'));
    }

    public function advance(string $stage): void
    {
        $this->authorizePermission('people.admissions.enquiry_manage');
        $this->resetErrorBag();

        if ($this->openId === null) {
            return;
        }

        try {
            app(AdvanceEnquiryStageAction::class)->execute(new AdvanceEnquiryStageData($this->openId, $stage, $stage === 'lost' ? $this->lostReason : null));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('lostReason', $exception->getMessage());

            return;
        }

        $this->toast(__('Updated.'));
    }

    public function render(): View
    {
        $enquiries = Enquiry::query()->whereNotIn('stage', ['lost', 'converted'])->orderBy('next_follow_up_on')->limit(300)->get();
        $open = $this->openId === null ? null : Enquiry::query()->find($this->openId);

        return view('people::admissions.enquiries-board', [
            'columns' => collect(AdvanceEnquiryStageAction::STAGES)->mapWithKeys(fn (string $stage): array => [$stage => $enquiries->where('stage', $stage)->values()]),
            'open' => $open,
            'activities' => $open === null ? collect() : EnquiryActivity::query()->where('enquiry_id', $open->id)->orderByDesc('occurred_at')->limit(30)->get(),
            'sources' => CreateEnquiryAction::SOURCES,
            'activityTypes' => LogEnquiryActivityAction::TYPES,
            'lostReasons' => AdvanceEnquiryStageAction::LOST_REASONS,
        ]);
    }
}
