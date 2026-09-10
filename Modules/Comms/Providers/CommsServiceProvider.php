<?php

declare(strict_types=1);

namespace Modules\Comms\Providers;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Comms\Domain\DataObjects\AutomationEntityDefinition;
use Modules\Comms\Domain\DataObjects\AutomationEventDefinition;
use Modules\Comms\Domain\DataObjects\AutomationScanRecord;
use Modules\Comms\Domain\DataObjects\CalendarSourceDefinition;
use Modules\Comms\Domain\DataObjects\CalendarSourceRecord;
use Modules\Comms\Domain\DataObjects\WidgetDefinition;
use Modules\Comms\Domain\DataObjects\WidgetResolverResult;
use Modules\Comms\Domain\Registry\AutomationEntityRegistry;
use Modules\Comms\Domain\Registry\AutomationEventRegistry;
use Modules\Comms\Domain\Registry\CalendarSourceRegistry;
use Modules\Comms\Domain\Registry\DeepLinkRegistry;
use Modules\Comms\Domain\Registry\MeetingProviderDriverRegistry;
use Modules\Comms\Domain\Registry\WidgetRegistry;
use Modules\Comms\Domain\Support\FakeEmailGatewayDriver;
use Modules\Comms\Domain\Support\FakeMeetingProviderDriver;
use Modules\Comms\Domain\Support\FakePushGatewayDriver;
use Modules\Comms\Domain\Support\FakeSmsGatewayDriver;
use Modules\Comms\Domain\Support\FakeWhatsAppGatewayDriver;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\CalendarFeedToken;
use Modules\Comms\Models\Complaint;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Comms\Models\ComplaintUpdate;
use Modules\Comms\Models\ConsultationBooking;
use Modules\Comms\Models\ConsultationWindow;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Comms\Models\ExitInterview;
use Modules\Comms\Models\GatewayCostReconciliation;
use Modules\Comms\Models\MeetingAttendance;
use Modules\Comms\Models\MeetingProvider;
use Modules\Comms\Models\MessageConversation;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\MessageSegment;
use Modules\Comms\Models\Newsletter;
use Modules\Comms\Models\Notice;
use Modules\Comms\Models\NoticeRead;
use Modules\Comms\Models\RuleExecution;
use Modules\Comms\Models\ScanRun;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Comms\Models\SchoolWidgetSetting;
use Modules\Comms\Models\SenderId;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyResponse;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationChannelDriverRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CalculateSubledgerBalanceAction;
use Modules\Finance\Domain\DataObjects\CalculateSubledgerBalanceData;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Wallet\Domain\Events\WalletNegative;
use Modules\Wallet\Models\StudentWallet;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book I COM-01 — Messaging Gateways & Delivery, the first module in
 * this codebase to register a REAL `NotificationChannelDriver` (Book
 * A CORE-09's own contract, which that module's docblock explicitly
 * left for COM-01 to complete). Four drivers — SMS, WhatsApp, email,
 * push — each real-shaped but deterministic and simulate-able,
 * matching the "single fake driver, not a per-brand registry" choice
 * already made for `Modules\Fiscal`/`Modules\Finance`'s own gateway
 * integrations; see each driver's own class docblock.
 *
 * `FakeSmsGatewayDriver` and `FakeWhatsAppGatewayDriver` both work
 * around the SAME real constraint: Book A's `NotificationChannelDriver::send()`
 * carries no notification id and no notification key, so neither
 * driver can look anything up by either — see their own docblocks for
 * how each recovers what it needs from what IS available.
 *
 * `provider_rate_cards` is NOT registered in `TenantModelRegistry`:
 * `school_id` is nullable there (a null row is the system default
 * rate card), so the model doesn't use `BelongsToSchool` — same
 * exception already established for `zimsec_validation_rules` (Book
 * H3 CMP-01) and `messaging_gateway_webhooks` (nullable `school_id`
 * for the same reason a webhook may arrive before it can be matched
 * to a school, mirroring `Modules\Core\Models\ActivityLogEntry`).
 */
class CommsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Comms';

    protected string $nameLower = 'comms';

    public function boot(): void
    {
        parent::boot();

        $this->registerChannelDrivers();
        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerAutomationEntities();
        $this->registerAutomationEvents();
        $this->registerDashboardWidgets();
        $this->registerDeepLinks();
        $this->registerCalendarSources();
        $this->registerCom06NotificationKeys();
        $this->registerMeetingProviderDrivers();
        $this->registerCom07NotificationKeys();
        $this->registerCom08NotificationKeys();
    }

    /**
     * The slot `Modules\Finance\Providers\FinanceServiceProvider::registerPaymentGatewayDrivers()`
     * fills for `PaymentGatewayDriverRegistry` — this is COM-01's own
     * equivalent for Core's `NotificationChannelDriverRegistry`.
     */
    private function registerChannelDrivers(): void
    {
        NotificationChannelDriverRegistry::register($this->app->make(FakeSmsGatewayDriver::class));
        NotificationChannelDriverRegistry::register($this->app->make(FakeWhatsAppGatewayDriver::class));
        NotificationChannelDriverRegistry::register($this->app->make(FakeEmailGatewayDriver::class));
        NotificationChannelDriverRegistry::register($this->app->make(FakePushGatewayDriver::class));
    }

    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(MessageGateway::class, fn (School $school): MessageGateway => MessageGateway::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SenderId::class, fn (School $school): SenderId => SenderId::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(WhatsAppBusinessAccount::class, fn (School $school): WhatsAppBusinessAccount => WhatsAppBusinessAccount::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(WhatsAppTemplate::class, fn (School $school): WhatsAppTemplate => WhatsAppTemplate::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(MessageConversation::class, fn (School $school): MessageConversation => MessageConversation::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(MessageSegment::class, fn (School $school): MessageSegment => MessageSegment::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(GatewayCostReconciliation::class, fn (School $school): GatewayCostReconciliation => GatewayCostReconciliation::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AutomationRule::class, fn (School $school): AutomationRule => AutomationRule::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(RuleExecution::class, fn (School $school): RuleExecution => RuleExecution::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ScanRun::class, fn (School $school): ScanRun => ScanRun::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SchoolWidgetSetting::class, fn (School $school): SchoolWidgetSetting => SchoolWidgetSetting::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(CalendarEvent::class, fn (School $school): CalendarEvent => CalendarEvent::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Notice::class, fn (School $school): Notice => Notice::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(NoticeRead::class, fn (School $school): NoticeRead => NoticeRead::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Newsletter::class, fn (School $school): Newsletter => Newsletter::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(EventRegistration::class, fn (School $school): EventRegistration => EventRegistration::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(EventAttendee::class, fn (School $school): EventAttendee => EventAttendee::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(CalendarFeedToken::class, fn (School $school): CalendarFeedToken => CalendarFeedToken::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(MeetingProvider::class, fn (School $school): MeetingProvider => MeetingProvider::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ScheduledMeeting::class, fn (School $school): ScheduledMeeting => ScheduledMeeting::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ConsultationWindow::class, fn (School $school): ConsultationWindow => ConsultationWindow::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ConsultationBooking::class, fn (School $school): ConsultationBooking => ConsultationBooking::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(MeetingAttendance::class, fn (School $school): MeetingAttendance => MeetingAttendance::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Survey::class, fn (School $school): Survey => Survey::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SurveyResponse::class, fn (School $school): SurveyResponse => SurveyResponse::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ComplaintCategory::class, fn (School $school): ComplaintCategory => ComplaintCategory::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Complaint::class, fn (School $school): Complaint => Complaint::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ComplaintUpdate::class, fn (School $school): ComplaintUpdate => ComplaintUpdate::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ExitInterview::class, fn (School $school): ExitInterview => ExitInterview::factory()->create(['school_id' => $school->id]));
    }

    /**
     * Book I COM-02 §3 ⭐/BR-COM-02-003 — see `AutomationEntityRegistry`'s
     * own docblock for the documented scope boundary. `invoice.balance_minor`
     * and `invoice.days_overdue` are both real, stored columns
     * (`Modules\Finance\Models\Invoice`); `student.has_active_payment_plan`
     * — the third field the spec's own worked example names — is NOT
     * whitelisted here: no payment-plan concept exists anywhere in this
     * codebase yet (grepped, confirmed absent), so it would be a
     * fabricated field with nothing real behind it.
     */
    private function registerAutomationEntities(): void
    {
        AutomationEntityRegistry::register(new AutomationEntityDefinition(
            entityKey: 'invoice',
            allowedFields: ['invoice.balance_minor', 'invoice.days_overdue', 'invoice.status', 'invoice.currency'],
            scanner: function (int $schoolId): Collection {
                return Invoice::where('school_id', $schoolId)
                    ->where('balance_minor', '>', 0)
                    ->get()
                    ->map(function (Invoice $invoice): ?AutomationScanRecord {
                        $link = StudentGuardian::where('school_id', $invoice->school_id)
                            ->where('student_id', $invoice->student_id)
                            ->where('is_fee_responsible', true)
                            ->where('status', 'active')
                            ->with('guardian')
                            ->first();

                        if ($link === null || $link->guardian === null) {
                            return null;
                        }

                        $daysOverdue = max(0, (int) $invoice->due_date->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay(), false));

                        return new AutomationScanRecord(
                            schoolId: $invoice->school_id,
                            subjectType: 'invoice',
                            subjectId: $invoice->id,
                            fields: [
                                'invoice.balance_minor' => $invoice->balance_minor,
                                'invoice.days_overdue' => $daysOverdue,
                                'invoice.status' => $invoice->status,
                                'invoice.currency' => $invoice->currency,
                            ],
                            context: [
                                'invoice' => ['number' => $invoice->invoice_number, 'balance_minor' => $invoice->balance_minor, 'currency' => $invoice->currency],
                                'guardian' => ['name' => trim("{$link->guardian->first_name} {$link->guardian->last_name}")],
                            ],
                            recipientType: 'guardian',
                            recipientId: $link->guardian->id,
                            addresses: [
                                'sms' => (string) $link->guardian->primary_phone,
                                'whatsapp' => (string) $link->guardian->primary_phone,
                                'email' => (string) $link->guardian->email,
                            ],
                        );
                    })
                    ->filter()
                    ->values();
            },
        ));
    }

    /**
     * Book I COM-02 §4/BR-COM-02-001 — see `AutomationEventRegistry`'s
     * own docblock for the documented scope boundary. `WalletNegative`
     * (Book H3 FIN-14, already fired for real by `SyncOfflineWalletSaleAction`)
     * is the one real event wired in this pass.
     */
    private function registerAutomationEvents(): void
    {
        AutomationEventRegistry::register(new AutomationEventDefinition(
            eventName: 'WalletNegative',
            eventClass: WalletNegative::class,
            allowedFields: ['wallet.balance_minor'],
            extractor: function (object $event): AutomationScanRecord {
                /** @var WalletNegative $event */
                $wallet = $event->wallet;

                $link = StudentGuardian::where('school_id', $wallet->school_id)
                    ->where('student_id', $wallet->student_id)
                    ->where('is_primary_contact', true)
                    ->where('status', 'active')
                    ->with('guardian')
                    ->first();

                return new AutomationScanRecord(
                    schoolId: $wallet->school_id,
                    subjectType: 'student_wallet',
                    subjectId: $wallet->id,
                    fields: ['wallet.balance_minor' => $wallet->balance_minor],
                    context: ['wallet' => ['balance_minor' => $wallet->balance_minor]],
                    recipientType: 'guardian',
                    recipientId: $link?->guardian?->id,
                    addresses: $link?->guardian !== null
                        ? ['sms' => (string) $link->guardian->primary_phone, 'email' => (string) $link->guardian->email]
                        : [],
                );
            },
        ));
    }

    /**
     * Book I COM-03/04/05 §3 ⭐/BR-COM-03-001 — see `WidgetRegistry`'s
     * own docblock for the documented scope boundary. Every resolver
     * calls its owning module's real domain layer (`CalculateSubledgerBalanceAction`
     * for fee balance, `StudentWallet` for wallet balance — both
     * already-established sources of truth elsewhere in this
     * codebase), never a second computation of the same figure.
     */
    private function registerDashboardWidgets(): void
    {
        WidgetRegistry::register(new WidgetDefinition(
            key: 'fee_balance',
            moduleCode: 'FIN-03',
            persona: 'parent',
            title: 'Fee Balance',
            dataEndpoint: '/api/v1/portal/parent/dashboard',
            resolver: function (User $user, int $schoolId): ?WidgetResolverResult {
                $guardian = Guardian::where('school_id', $schoolId)->where('user_id', $user->id)->first();

                if ($guardian === null) {
                    return null;
                }

                $studentIds = StudentGuardian::where('school_id', $schoolId)->where('guardian_id', $guardian->id)->where('status', 'active')->pluck('student_id');

                if ($studentIds->isEmpty()) {
                    return null;
                }

                $school = School::findOrFail($schoolId);
                $totalMinor = 0;

                foreach ($studentIds as $studentId) {
                    $balance = app(CalculateSubledgerBalanceAction::class)->execute(new CalculateSubledgerBalanceData(
                        schoolId: $schoolId, subledgerType: 'student', subledgerId: $studentId,
                        currency: $school->base_currency, asAt: Carbon::now(),
                    ));
                    $totalMinor += $balance->minor;
                }

                return new WidgetResolverResult('fee_balance', 'Fee Balance', ['balance_minor' => $totalMinor, 'currency' => $school->base_currency]);
            },
        ));

        WidgetRegistry::register(new WidgetDefinition(
            key: 'wallet_balance_parent',
            moduleCode: 'FIN-14',
            persona: 'parent',
            title: 'Wallet Balance',
            dataEndpoint: '/api/v1/portal/parent/dashboard',
            requiresModule: 'FIN-14',
            resolver: function (User $user, int $schoolId): ?WidgetResolverResult {
                $guardian = Guardian::where('school_id', $schoolId)->where('user_id', $user->id)->first();

                if ($guardian === null) {
                    return null;
                }

                $studentIds = StudentGuardian::where('school_id', $schoolId)->where('guardian_id', $guardian->id)->where('status', 'active')->pluck('student_id');
                $totalMinor = (int) StudentWallet::whereIn('student_id', $studentIds)->sum('balance_minor');

                return new WidgetResolverResult('wallet_balance_parent', 'Wallet Balance', ['balance_minor' => $totalMinor]);
            },
        ));

        WidgetRegistry::register(new WidgetDefinition(
            key: 'unread_messages_parent',
            moduleCode: 'CORE-09',
            persona: 'parent',
            title: 'Unread Messages',
            dataEndpoint: '/api/v1/portal/parent/dashboard',
            resolver: function (User $user, int $schoolId): ?WidgetResolverResult {
                $guardian = Guardian::where('school_id', $schoolId)->where('user_id', $user->id)->first();

                if ($guardian === null) {
                    return null;
                }

                $count = Notification::where('recipient_type', 'guardian')->where('recipient_id', $guardian->id)->where('channel', 'in_app')->whereNull('read_at')->count();

                return $count > 0 ? new WidgetResolverResult('unread_messages_parent', 'Unread Messages', ['count' => $count]) : null;
            },
        ));

        WidgetRegistry::register(new WidgetDefinition(
            key: 'wallet_balance_learner',
            moduleCode: 'FIN-14',
            persona: 'learner',
            title: 'Wallet Balance',
            dataEndpoint: '/api/v1/portal/learner/dashboard',
            requiresModule: 'FIN-14',
            resolver: function (User $user, int $schoolId): ?WidgetResolverResult {
                $student = Student::where('school_id', $schoolId)->where('user_id', $user->id)->first();

                if ($student === null) {
                    return null;
                }

                $wallet = StudentWallet::where('school_id', $schoolId)->where('student_id', $student->id)->first();

                return $wallet !== null ? new WidgetResolverResult('wallet_balance_learner', 'Wallet Balance', ['balance_minor' => $wallet->balance_minor]) : null;
            },
        ));

        WidgetRegistry::register(new WidgetDefinition(
            key: 'unread_messages_staff',
            moduleCode: 'CORE-09',
            persona: 'staff',
            title: 'Unread Messages',
            dataEndpoint: '/api/v1/portal/staff/dashboard',
            resolver: function (User $user, int $schoolId): ?WidgetResolverResult {
                $staff = Staff::where('school_id', $schoolId)->where('user_id', $user->id)->first();

                if ($staff === null) {
                    return null;
                }

                $count = Notification::where('recipient_type', 'staff')->where('recipient_id', $staff->id)->where('channel', 'in_app')->whereNull('read_at')->count();

                return $count > 0 ? new WidgetResolverResult('unread_messages_staff', 'Unread Messages', ['count' => $count]) : null;
            },
        ));
    }

    /**
     * Book I COM-03 §6/BR-COM-03-011 — see `DeepLinkRegistry`'s own
     * docblock for the documented scope boundary.
     */
    private function registerDeepLinks(): void
    {
        DeepLinkRegistry::register('invoice', '/finance/invoices/{id}', '/finance/invoices');
        DeepLinkRegistry::register('student', '/students/{id}', '/students');
    }

    /**
     * Book I COM-06 §2 ⭐/BR-COM-06-001 — see `CalendarSourceRegistry`'s
     * own docblock for the documented scope boundary. `CORE-03`'s own
     * `Term` (`starts_on`/`ends_on`, already real and already Book A)
     * is the one real source registered in this pass.
     */
    private function registerCalendarSources(): void
    {
        CalendarSourceRegistry::register(new CalendarSourceDefinition(
            moduleCode: 'CORE-03',
            sourceType: 'term_dates',
            defaultAudienceScope: 'whole_school',
            defaultColour: '#4b5563',
            syncer: function (int $schoolId): Collection {
                return Term::where('school_id', $schoolId)
                    ->get()
                    ->map(fn (Term $term): CalendarSourceRecord => new CalendarSourceRecord(
                        sourceModuleId: $term->id,
                        academicYearId: $term->academic_year_id,
                        termId: $term->id,
                        title: "Term {$term->number}: {$term->name}",
                        startsAt: $term->starts_on->copy()->startOfDay(),
                        endsAt: $term->ends_on->copy()->endOfDay(),
                        isAllDay: true,
                    ));
            },
        ));
    }

    /**
     * Book I COM-06 §3/BR-COM-06-005/007/009 — the real notification
     * keys this module owns, mirroring `Modules\Boarding\Providers\BoardingServiceProvider`'s
     * own `NotificationKeyRegistry::register()` calls for
     * `boarding.missing_learner_escalation`.
     */
    private function registerCom06NotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'comms.calendar_event_cancelled',
            variables: ['event.title'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'guardian',
            isTransactional: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'comms.notice_escalation',
            variables: ['notice.title', 'unread_percent'],
            defaultChannels: ['email'],
            defaultAudience: 'staff',
            isUrgent: true,
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'comms.event_waitlist_promoted',
            variables: ['event.title'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'guardian',
            isTransactional: true,
        ));
    }

    /**
     * Book I COM-07 §2/§6 — see `MeetingProviderDriverRegistry`'s own
     * docblock. One fake driver serves every provider string this
     * pass supports, the same "single fake driver" choice already
     * made for COM-01's own four channel drivers.
     */
    private function registerMeetingProviderDrivers(): void
    {
        MeetingProviderDriverRegistry::register($this->app->make(FakeMeetingProviderDriver::class));
    }

    /**
     * Book I COM-07 §3/§9 — the real notification keys this module
     * owns, mirroring COM-06's own `registerCom06NotificationKeys()`.
     */
    private function registerCom07NotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'comms.meeting_cancelled',
            variables: ['meeting.starts_at'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'guardian',
            isTransactional: true,
        ));
    }

    /**
     * Book I COM-08 §3/§9 — the real notification keys this module
     * owns, mirroring COM-06/COM-07's own registration methods.
     */
    private function registerCom08NotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'comms.complaint_sla_approaching',
            variables: ['complaint.number'],
            defaultChannels: ['email'],
            defaultAudience: 'staff',
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'comms.complaint_sla_breached',
            variables: ['complaint.number'],
            defaultChannels: ['email'],
            defaultAudience: 'staff',
            isUrgent: true,
        ));
    }

    /**
     * Book I COM-01 §6. `sms_normalise_before_send` stays `true`
     * unconditionally in this pass — `FakeSmsGatewayDriver` always
     * normalises, matching what "(locked)" means for this setting.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['comms.whatsapp_quality_pause_threshold', 'string', 'red', 'WhatsApp quality rating that pauses non-critical outbound sends.'],
            ['comms.sms_normalise_before_send', 'bool', '1', 'Whether outbound SMS is normalised for GSM-7 before encoding (locked true).'],
            ['comms.gateway_health_check_minutes', 'int', '5', 'How often scheduled gateway health checks run.'],
            ['comms.cost_reconciliation_tolerance_percent', 'int', '3', 'Variance tolerance, as a percentage, before a cost reconciliation is flagged.'],
            ['automation.scan_priority_queue', 'string', 'automation-scan', 'Queue name scheduled-scan rule runs are dispatched on.'],
            ['automation.require_cost_estimate_review', 'bool', '1', 'Whether activation requires a reviewed cost estimate (locked true).'],
            ['automation.default_throttle_window_hours', 'int', '168', 'Default throttle window for a new scan-triggered rule.'],
            ['portal.app_pin_required', 'bool', '0', 'Whether an app-level PIN is required on portal devices.'],
            ['portal.app_pin_min_length', 'int', '4', 'Minimum length for a portal device app PIN.'],
            ['portal.offline_cache_max_days', 'int', '7', 'Maximum age, in days, of cached offline data before it is considered stale.'],
            ['portal.dashboard_widget_max_per_persona', 'int', '12', 'Maximum widgets a school may enable per persona dashboard.'],
            ['portal.learner_min_grade_ordinal', 'int', '5', 'Grade level ordinal at and above which a learner portal account may exist (BR-CORE-05-022).'],
            ['comms.notice_escalation_delay_minutes', 'int', '60', 'Minutes after an urgent notice publishes before its unread rate may trigger an escalation.'],
            ['comms.notice_escalation_unread_threshold_percent', 'int', '50', 'Unread percentage at or above which an urgent notice escalates to its poster.'],
            ['meetings.waiting_room_default_for_learners', 'bool', '1', 'Whether a learner-facing meeting starts with its waiting room enabled (locked true).'],
            ['attendance.online_minimum_attendance_percent', 'int', '70', 'Minimum percentage of an online lesson a participant must attend to be marked present, not present-but-flagged.'],
            ['meetings.recording_retention_days', 'int', '90', 'Days a meeting recording is retained before its URL is purged.'],
            ['complaints.sla_warning_hours_before', 'int', '12', 'Hours before a complaint\'s SLA due date that an "approaching" alert may fire.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'COM',
                'group_key' => 'messaging',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => match ($dataType) {
                    'bool' => 'toggle',
                    default => 'text',
                },
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }
}
