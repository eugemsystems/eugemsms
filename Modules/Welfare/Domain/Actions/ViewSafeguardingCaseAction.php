<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\ImpersonationSession;
use Modules\People\Models\Staff;
use Modules\Welfare\Domain\DataObjects\RecordSafeguardingAuditEntryData;
use Modules\Welfare\Models\SafeguardingCase;
use Throwable;

/**
 * ACT-ViewSafeguardingCase (Book G BRD-08 §2 ⭐⭐/BR-BRD-08-001/002/003/
 * 004/005/AC-BRD-08-001/002/003/004/005/006). Mirrors the spec's own
 * `SafeguardingCasePolicy::view()` almost verbatim. The hard vendor/
 * impersonation exclusion is the FIRST check, no exceptions — no
 * amount of role or grant overrides it. Every branch — blocked, lead,
 * grant, break-glass, denied — writes to the separate hardened
 * `safeguarding_audit` stream, never `data_access_log`. Break-glass
 * grants access AND is loud: the lead and head are alerted immediately,
 * on every use.
 */
final class ViewSafeguardingCaseAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly RecordSafeguardingAuditEntryAction $recordAudit,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(User $user, SafeguardingCase $case, ?ImpersonationSession $activeSession = null, ?string $ip = null, ?string $userAgent = null): SafeguardingCase
    {
        if ($user->user_type === UserType::Vendor || ($activeSession !== null && $activeSession->isActive())) {
            $this->audit($case, 'vendor_blocked', $user, 'blocked_vendor_access', $ip, $userAgent);
            $this->alertStaffSetting($case, 'safeguarding.lead_staff_id', 'safeguarding.vendor_access_attempt');

            throw new InsufficientScopeException(
                "User #{$user->id} has no access to safeguarding case #{$case->id} — vendor/impersonating sessions are hard-excluded.",
            );
        }

        if ($this->isSafeguardingLead($user, $case->school_id)) {
            $this->audit($case, 'case_read', $user, 'lead', $ip, $userAgent);

            return $case;
        }

        $grant = $case->activeGrantFor($user);

        if ($grant !== null) {
            $this->audit($case, 'case_read', $user, "grant:{$grant->ulid}", $ip, $userAgent);

            return $case;
        }

        if ($user->hasPermissionTo('safeguarding.emergency_access')) {
            $this->audit($case, 'break_glass', $user, 'break_glass', $ip, $userAgent);
            $this->alertStaffSetting($case, 'safeguarding.lead_staff_id', 'safeguarding.break_glass_used');
            $this->alertStaffSetting($case, 'safeguarding.deputy_lead_staff_id', 'safeguarding.break_glass_used');

            return $case;
        }

        $this->audit($case, 'access_denied', $user, 'denied', $ip, $userAgent);

        throw new InsufficientScopeException("User #{$user->id} has no access to safeguarding case #{$case->id}.");
    }

    private function isSafeguardingLead(User $user, int $schoolId): bool
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        $leadStaffId = $this->settings->get('safeguarding.lead_staff_id', $scope);

        if ($leadStaffId === null) {
            return false;
        }

        return Staff::find((int) $leadStaffId)?->user_id === $user->id;
    }

    private function audit(SafeguardingCase $case, string $eventType, User $user, string $accessBasis, ?string $ip, ?string $userAgent): void
    {
        $this->recordAudit->execute(new RecordSafeguardingAuditEntryData(
            schoolId: $case->school_id,
            eventType: $eventType,
            userId: $user->id,
            userRoleAtTime: $user->user_type->value,
            payload: ['case_id' => $case->id, 'access_basis' => $accessBasis],
            caseId: $case->id,
            accessBasis: $accessBasis,
            ip: $ip,
            userAgent: $userAgent,
        ));
    }

    private function alertStaffSetting(SafeguardingCase $case, string $settingKey, string $notificationKey): void
    {
        $scope = new ScopeChain(schoolId: $case->school_id);
        $staffId = $this->settings->get($settingKey, $scope);

        if ($staffId === null) {
            return;
        }

        $staff = Staff::find((int) $staffId);

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $case->school_id,
                notificationKey: $notificationKey,
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['case_reference' => $case->case_reference],
                recipientId: $staff->user_id,
                relatedType: 'safeguarding_case',
                relatedId: $case->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // BR-BRD-08-004 — the alert is unconditional in intent, but
            // a dispatch failure never blocks or hides the access
            // itself, which is already durable in safeguarding_audit.
        }
    }
}
