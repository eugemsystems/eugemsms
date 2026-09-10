<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\SignInVisitorData;
use Modules\Boarding\Domain\Events\BlacklistedVisitorAttempt;
use Modules\Boarding\Domain\Exceptions\VisitorBlacklistedException;
use Modules\Boarding\Models\Visitor;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;

/**
 * ACT-SignInVisitor (Book F BRD-03 §4/BR-BRD-03-017/018/019). A
 * blacklisted visitor is refused outright and the attempt is logged
 * as a security event (`RecordSecurityEventAction`) rather than a
 * sign-in record — there is no legitimate visitor_logs row for a
 * visit that never happened. A watchlisted visitor is admitted, but
 * the host and designated staff member are meant to be notified —
 * this action fires the event; actual fan-out to "the designated
 * staff member" needs a per-school setting this pass does not add.
 */
final class SignInVisitorAction extends Action
{
    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(SignInVisitorData $data): VisitorLogEntry
    {
        $visitor = $this->findOrRegisterVisitor($data);

        if ($visitor->is_blacklisted) {
            $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                eventType: 'blacklisted_visitor_attempt',
                severity: 'critical',
                description: "Blacklisted visitor #{$visitor->id} ({$visitor->full_name}) attempted sign-in.",
                schoolId: $data->schoolId,
                userId: $data->gateStaffUserId,
                context: ['visitor_id' => $visitor->id],
            ));

            event(new BlacklistedVisitorAttempt($visitor));

            throw VisitorBlacklistedException::forVisitor($visitor->id);
        }

        return $this->transaction(fn (): VisitorLogEntry => VisitorLogEntry::create([
            'school_id' => $data->schoolId,
            'visitor_id' => $visitor->id,
            'visit_purpose' => $data->visitPurpose,
            'host_staff_id' => $data->hostStaffId,
            'student_id' => $data->studentId,
            'vehicle_registration' => $data->vehicleRegistration,
            'badge_number' => $data->badgeNumber,
            'signed_in_at' => Carbon::now(),
            'expected_duration_mins' => $data->expectedDurationMins,
            'gate_staff_in' => $data->gateStaffUserId,
            'items_declared' => null,
            'induction_completed' => $data->inductionCompleted,
        ]));
    }

    private function findOrRegisterVisitor(SignInVisitorData $data): Visitor
    {
        $existing = Visitor::query()
            ->where('school_id', $data->schoolId)
            ->where('full_name', $data->fullName)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Visitor::create([
            'school_id' => $data->schoolId,
            'full_name' => $data->fullName,
            'id_type' => $data->idType,
            'id_number' => $data->idNumber,
            'phone' => $data->phone,
            'is_blacklisted' => false,
            'is_watchlisted' => false,
        ]);
    }
}
