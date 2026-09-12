<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Audit;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Audit\ReviewSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\ReviewSecurityEventData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SecurityEvent;

/**
 * `Core\Audit\SecurityEvents` (Book A CORE-08 §5, `core.audit.view_security`)
 * — triage queue. Reviewing a specific event (`core.audit.review_security_event`)
 * is a narrower, more sensitive action than merely viewing the queue,
 * so it's authorised separately rather than folding into the screen's
 * own baseline permission.
 */
#[Title('Security events')]
#[Layout('layouts.app')]
final class SecurityEvents extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public ?int $reviewingEventId = null;

    public string $reviewNotes = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.audit.view_security');
    }

    public function openReviewModal(int $eventId): void
    {
        $this->authorizePermission('core.audit.review_security_event');

        $this->reviewingEventId = $eventId;
        $this->reviewNotes = '';
    }

    public function review(): void
    {
        $this->authorizePermission('core.audit.review_security_event');

        if ($this->reviewingEventId === null) {
            return;
        }

        app(ReviewSecurityEventAction::class)->execute(new ReviewSecurityEventData(
            securityEventId: $this->reviewingEventId,
            reviewedByUserId: (int) Auth::id(),
            notes: $this->reviewNotes !== '' ? $this->reviewNotes : null,
        ));

        $this->reviewingEventId = null;
        $this->toast(__('Security event marked reviewed.'));
    }

    public function render(): View
    {
        $query = SecurityEvent::query()->where('school_id', $this->school->id)->with(['user', 'reviewedBy']);

        return view('core::audit.security-events', [
            'events' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'event_type' => ['label' => __('Event'), 'sortable' => true, 'searchable' => true],
            'severity' => [
                'label' => __('Severity'), 'sortable' => true, 'filter' => 'select',
                'options' => ['info' => __('Info'), 'warning' => __('Warning'), 'critical' => __('Critical')],
            ],
            'description' => ['label' => __('Description'), 'searchable' => true],
            'is_reviewed' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['0' => __('Unreviewed'), '1' => __('Reviewed')],
            ],
            'occurred_at' => ['label' => __('When'), 'sortable' => true],
        ];
    }
}
