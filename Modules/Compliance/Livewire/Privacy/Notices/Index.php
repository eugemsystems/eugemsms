<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Notices;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\CreatePrivacyNoticeAction;
use Modules\Compliance\Domain\DataObjects\CreatePrivacyNoticeData;
use Modules\Compliance\Models\PrivacyNotice;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Notices` (Book H3 CMP-03 §4, `privacy.manage`).
 * `privacy_notices` itself is this module's own addition — the spec's
 * data model never defined it despite `consents.notice_version` and
 * this very screen needing a real source of truth (see the module's
 * migration docblock). `RecordConsentAction` resolves the version in
 * force itself; this screen only creates new versions, it never edits
 * one (BR-CMP-03-002/015).
 */
#[Title('Privacy notices')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $version = '';

    public string $title = '';

    public string $content = '';

    public string $effectiveFrom = '';

    public bool $requiresReconsent = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.manage');

        $this->effectiveFrom = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->validate([
            'version' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        app(CreatePrivacyNoticeAction::class)->execute(new CreatePrivacyNoticeData(
            schoolId: $this->school->id,
            version: $this->version,
            title: $this->title,
            content: $this->content,
            effectiveFrom: Carbon::parse($this->effectiveFrom)->toDateString(),
            createdByUserId: (int) auth()->id(),
            requiresReconsent: $this->requiresReconsent,
        ));

        $this->reset(['version', 'title', 'content', 'requiresReconsent']);
        $this->toast(__('Privacy notice version created.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.notices.index', [
            'notices' => PrivacyNotice::where('school_id', $this->school->id)->orderByDesc('effective_from')->get(),
        ]);
    }
}
