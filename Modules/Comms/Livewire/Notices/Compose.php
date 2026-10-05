<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Notices;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\PostNoticeAction;
use Modules\Comms\Domain\DataObjects\PostNoticeData;
use Modules\Comms\Livewire\Concerns\ResolvesAudienceScopes;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Notices\Compose` (Book I COM-06 §4, `notices.post` ⚠). A
 * notice with a future publish time is `scheduled`, otherwise
 * `published` at once (the Action decides). Attachments are not
 * offered: the file-vault picker belongs to CORE-10's own screens and
 * is not wired here.
 */
#[Title('Post notice')]
#[Layout('layouts.app')]
final class Compose extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesAudienceScopes;
    use Toasts;

    public string $title = '';

    public string $body = '';

    public string $priority = 'normal';

    public string $audienceScope = 'whole_school';

    public ?int $audienceScopeId = null;

    public bool $isPinned = false;

    public string $publishAt = '';

    public string $expiresAt = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('notices.post');
    }

    public function post(): void
    {
        $this->authorizePermission('notices.post');

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'in:normal,important,urgent'],
            'audienceScope' => ['required', 'in:'.implode(',', array_keys($this->audienceScopeOptions()))],
            'publishAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after:'.($this->publishAt !== '' ? 'publishAt' : 'now')],
        ]);

        $notice = app(PostNoticeAction::class)->execute(new PostNoticeData(
            schoolId: $this->school->id,
            title: $this->title,
            body: $this->body,
            audienceScope: $this->audienceScope,
            postedByUserId: (int) auth()->id(),
            priority: $this->priority,
            audienceScopeId: $this->resolveAudienceScopeId($this->audienceScope, $this->audienceScopeId, $this->school->id),
            isPinned: $this->isPinned,
            publishAt: $this->publishAt !== '' ? Carbon::parse($this->publishAt) : null,
            expiresAt: $this->expiresAt !== '' ? Carbon::parse($this->expiresAt) : null,
        ));

        $this->reset(['title', 'body', 'priority', 'audienceScope', 'audienceScopeId', 'isPinned', 'publishAt', 'expiresAt']);
        $this->toast($notice->status === 'scheduled' ? __('Notice scheduled.') : __('Notice published.'));
    }

    public function render(): View
    {
        return view('comms::notices.compose', [
            'scopeOptions' => $this->audienceScopeOptions(),
            'scopeTargets' => $this->audienceScopeTargets($this->audienceScope, $this->school->id),
        ]);
    }
}
