<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Newsletters;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CreateNewsletterAction;
use Modules\Comms\Domain\DataObjects\CreateNewsletterData;
use Modules\Comms\Models\Newsletter;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Newsletters\Compose` (Book I COM-06 §4, `newsletters.manage`).
 * Saves a draft or schedules an issue. **Sending is not built**: the
 * backend has no newsletter sender (fan-out to an audience is not
 * something CORE-09's one-recipient dispatch does), so no issue ever
 * reaches `sent` from here and none is shown as such unless something
 * else sets it. The saved HTML is tag-allowlisted by the Action, and
 * this screen lists issues by title and status only — it never renders
 * stored HTML.
 */
#[Title('Newsletters')]
#[Layout('layouts.app')]
final class Compose extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $issueNumber = '';

    public string $title = '';

    public string $contentHtml = '';

    public string $scheduledFor = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('newsletters.manage');
    }

    public function save(): void
    {
        $this->authorizePermission('newsletters.manage');

        $this->validate([
            'issueNumber' => ['required', 'string', 'max:20', Rule::unique('newsletters', 'issue_number')->where('school_id', $this->school->id)],
            'title' => ['required', 'string', 'max:200'],
            'contentHtml' => ['required', 'string', 'max:200000'],
            'scheduledFor' => ['nullable', 'date', 'after:now'],
        ]);

        $newsletter = app(CreateNewsletterAction::class)->execute(new CreateNewsletterData(
            schoolId: $this->school->id,
            issueNumber: $this->issueNumber,
            title: $this->title,
            contentHtml: $this->contentHtml,
            scheduledFor: $this->scheduledFor !== '' ? Carbon::parse($this->scheduledFor) : null,
        ));

        $this->reset(['issueNumber', 'title', 'contentHtml', 'scheduledFor']);
        $this->toast($newsletter->status === 'scheduled' ? __('Newsletter scheduled.') : __('Newsletter saved as a draft.'));
    }

    public function render(): View
    {
        return view('comms::newsletters.compose', [
            'newsletters' => Newsletter::where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(['id', 'issue_number', 'title', 'status', 'scheduled_for', 'sent_at']),
        ]);
    }
}
