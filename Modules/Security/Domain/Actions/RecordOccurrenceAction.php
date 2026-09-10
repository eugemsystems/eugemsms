<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Modules\Security\Domain\DataObjects\RecordOccurrenceData;
use Modules\Security\Domain\Events\OccurrenceRequiresImmediateNotice;
use Modules\Security\Models\OccurrenceBookEntry;
use Throwable;

/**
 * ACT-RecordOccurrence (Book H2 OPS-06 §4 ⭐/BR-OPS-06-003/012/
 * AC-OPS-06-005). `entry_number` is a real gapless sequence from
 * `CORE-06`'s own `AllocateNumberAction` (`AllocatedNumber.sequence`,
 * the raw integer behind every formatted document number this
 * codebase issues) — the same mechanism, not a bespoke counter. The
 * row itself is append-only at the model level; a correction is
 * always a new entry naming the one it corrects, never an edit.
 */
final class RecordOccurrenceAction extends Action
{
    private const array IMMEDIATE_NOTICE_CATEGORIES = ['intrusion', 'fire', 'medical'];

    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordOccurrenceData $data): OccurrenceBookEntry
    {
        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'occurrence_book',
            allocatedByUserId: $data->recordedByUserId,
        ));

        return $this->transaction(function () use ($data, $number): OccurrenceBookEntry {
            $entry = OccurrenceBookEntry::create([
                'school_id' => $data->schoolId,
                'entry_number' => $number->sequence,
                'occurred_at' => $data->occurredAt,
                'recorded_at' => Carbon::now(),
                'shift' => $data->shift,
                'category' => $data->category,
                'description' => $data->description,
                'location' => $data->location,
                'persons_involved' => $data->personsInvolved,
                'action_taken' => $data->actionTaken,
                'cctv_reference' => $data->cctvReference,
                'photo_file_ids' => $data->photoFileIds,
                'recorded_by' => $data->recordedByUserId,
                'corrects_entry_id' => $data->correctsEntryId,
            ]);

            if (in_array($data->category, self::IMMEDIATE_NOTICE_CATEGORIES, true)) {
                event(new OccurrenceRequiresImmediateNotice($entry));
                $this->notifyHead($entry);
            }

            return $entry;
        });
    }

    private function notifyHead(OccurrenceBookEntry $entry): void
    {
        $headStaffId = $this->settings->get('security.head_staff_id', new ScopeChain(schoolId: $entry->school_id));

        if ($headStaffId === null) {
            return;
        }

        $staff = Staff::find((int) $headStaffId);

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $entry->school_id,
                notificationKey: 'security.occurrence_immediate_notice',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['category' => $entry->category, 'description' => $entry->description],
                recipientId: $staff->user_id,
                relatedType: 'occurrence_book',
                relatedId: $entry->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking — the entry is already durable.
        }
    }
}
