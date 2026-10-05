<?php

declare(strict_types=1);

namespace Modules\Security\Livewire\Keys;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Security\Domain\Actions\CheckOverdueKeysAction;
use Modules\Security\Domain\Actions\CreateKeyAndCardAction;
use Modules\Security\Domain\Actions\IssueKeyAction;
use Modules\Security\Domain\Actions\ReturnKeyAction;
use Modules\Security\Domain\DataObjects\CreateKeyAndCardData;
use Modules\Security\Domain\DataObjects\IssueKeyData;
use Modules\Security\Domain\Exceptions\MasterKeyRequiresAuthorityException;
use Modules\Security\Models\KeyAndCard;
use Modules\Security\Models\KeyIssue;

/**
 * `Keys\Index` (Book H2 OPS-06 §5/BR-OPS-06-006/007,
 * `security.key.manage`). Issued, overdue, masters outstanding. Uses
 * the gap-filling `CreateKeyAndCardAction` this pass adds — see
 * `.ai/rules/security.md` — since no Action in the shipped domain
 * layer ever created a `keys_and_cards` row before this pass.
 */
#[Title('Keys and cards')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $identifier = '';

    public string $itemType = 'key';

    public string $description = '';

    public ?string $opensLocation = null;

    public bool $isMaster = false;

    public ?int $issuingKeyId = null;

    public ?int $issuedToStaffId = null;

    public ?string $dueBackOn = null;

    public bool $higherAuthorityConfirmed = false;

    public int $overdueCount = 0;

    public bool $overdueChecked = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('security.key.manage');
    }

    public function createKey(): void
    {
        $this->validate([
            'identifier' => ['required', 'string'],
            'itemType' => ['required', 'string'],
            'description' => ['required', 'string'],
        ]);

        app(CreateKeyAndCardAction::class)->execute(new CreateKeyAndCardData(
            schoolId: $this->school->id,
            identifier: $this->identifier,
            itemType: $this->itemType,
            description: $this->description,
            opensLocation: $this->opensLocation,
            isMaster: $this->isMaster,
        ));

        $this->reset(['identifier', 'description', 'opensLocation', 'isMaster']);
        $this->toast(__('Key/card registered.'));
    }

    public function selectForIssue(int $keyId): void
    {
        $this->issuingKeyId = $keyId;
    }

    public function issue(): void
    {
        if ($this->issuingKeyId === null) {
            return;
        }

        $this->validate(['issuedToStaffId' => ['required', 'integer']]);

        try {
            app(IssueKeyAction::class)->execute(new IssueKeyData(
                keyId: $this->issuingKeyId,
                issuedByUserId: (int) auth()->id(),
                issuedToStaffId: (int) $this->issuedToStaffId,
                dueBackOn: $this->dueBackOn !== null && $this->dueBackOn !== '' ? Carbon::parse($this->dueBackOn) : null,
                higherAuthorityConfirmed: $this->higherAuthorityConfirmed,
            ));
        } catch (MasterKeyRequiresAuthorityException|ValidationException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['issuingKeyId', 'issuedToStaffId', 'dueBackOn', 'higherAuthorityConfirmed']);
        $this->toast(__('Key issued.'));
    }

    public function returnKey(int $keyIssueId): void
    {
        app(ReturnKeyAction::class)->execute($keyIssueId, (int) auth()->id());
        $this->toast(__('Key returned.'));
    }

    public function checkOverdue(): void
    {
        $this->overdueCount = app(CheckOverdueKeysAction::class)->execute($this->school->id)->count();
        $this->overdueChecked = true;
    }

    public function render(): View
    {
        return view('security::keys.index', [
            'keys' => KeyAndCard::where('school_id', $this->school->id)->orderBy('identifier')->get(),
            'issues' => KeyIssue::with('key', 'issuedToStaff')->where('school_id', $this->school->id)->whereIn('status', ['issued', 'overdue'])->orderByDesc('issued_at')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('last_name')->get(),
        ]);
    }
}
