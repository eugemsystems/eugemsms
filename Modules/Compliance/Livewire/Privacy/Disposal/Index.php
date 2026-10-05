<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Disposal;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\DisposeQueuedRecordAction;
use Modules\Compliance\Domain\Actions\EnqueueDueDisposalsAction;
use Modules\Compliance\Domain\Actions\ReviewDisposalQueueItemAction;
use Modules\Compliance\Domain\DataObjects\ReviewDisposalQueueItemData;
use Modules\Compliance\Models\DisposalQueueItem;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Disposal` (Book H3 CMP-03 §4 ⚠⚠, `privacy.dispose`).
 *
 * ⭐ SAFETY BOUNDARY — read before touching this screen or its view.
 * `retention_schedules.record_class` includes `safeguarding_record` in
 * the spec's own enum, and this queue could in principle hold a row
 * pointing at one. This screen and its view show ONLY queue metadata —
 * `record_type`, `record_id` (a bare pointer, never resolved to the
 * underlying model), `schedule->record_class`, `eligible_on`,
 * `review_status`, `deferred_until`/`deferral_reason`, `disposed_at`,
 * `disposal_method` — and never queries, loads, or renders the actual
 * record's own content (category, risk level, description, notes,
 * anything else). The Welfare module's own sanctioned action for
 * reading a safeguarding case's content (Book G) is never called from
 * here, and this screen never resolves `record_type`/`record_id` to
 * an Eloquent model at all.
 * `EnqueueDueDisposalsAction` itself only wires ONE concrete resolver
 * today (`application_unsuccessful`, see its own docblock) — no
 * safeguarding row can reach this queue yet in practice, but the
 * display discipline above holds regardless, for whenever a resolver
 * for `safeguarding_record` is wired.
 */
#[Title('Disposal queue')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $reviewingItemId = null;

    public string $decision = 'approved';

    public string $deferredUntil = '';

    public string $deferralReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.dispose');
    }

    public function enqueueDue(): void
    {
        $this->authorizePermission('privacy.dispose');

        $queued = app(EnqueueDueDisposalsAction::class)->execute($this->school->id);

        $this->toast(__(':count record(s) queued for review.', ['count' => $queued->count()]));
    }

    public function startReview(int $itemId): void
    {
        $this->reviewingItemId = $itemId;
        $this->decision = 'approved';
        $this->deferredUntil = '';
        $this->deferralReason = '';
    }

    public function review(): void
    {
        $this->authorizePermission('privacy.dispose');

        $this->validate([
            'decision' => ['required', 'in:approved,deferred'],
            'deferredUntil' => ['required_if:decision,deferred', 'nullable', 'date'],
            'deferralReason' => ['required_if:decision,deferred', 'nullable', 'string', 'max:255'],
        ]);

        app(ReviewDisposalQueueItemAction::class)->execute(new ReviewDisposalQueueItemData(
            itemId: (int) $this->reviewingItemId,
            reviewedByUserId: (int) auth()->id(),
            decision: $this->decision,
            deferredUntil: $this->deferredUntil !== '' ? $this->deferredUntil : null,
            deferralReason: $this->deferralReason !== '' ? $this->deferralReason : null,
        ));

        $this->reviewingItemId = null;
        $this->toast(__('Review recorded.'));
    }

    public function dispose(int $itemId): void
    {
        $this->authorizePermission('privacy.dispose');

        try {
            app(DisposeQueuedRecordAction::class)->execute($itemId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Record disposed.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.disposal.index', [
            'items' => DisposalQueueItem::where('school_id', $this->school->id)
                ->with('schedule')
                ->orderBy('eligible_on')
                ->get(),
        ]);
    }
}
