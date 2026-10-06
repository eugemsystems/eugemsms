<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\DataObjects\Scheduling\ScheduledTaskDefinitionData;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Registry\LearnerClearanceRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\PermissionRegistry;
use Modules\Core\Domain\Registry\ScheduledTaskHandlerRegistry;
use Modules\Core\Domain\Registry\ScheduledTaskRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Console\Commands\CheckPaymentPlanBreachesCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceAllCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceCurrencyCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceDebtorsCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceFeesCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceLedgerCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceSchoolSetupCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceTillCommand;
use Modules\Finance\Console\Commands\Seeders\SeedFinanceUsersCommand;
use Modules\Finance\Console\Commands\SendFeeRemindersCommand;
use Modules\Finance\Console\Tasks\PollPendingPaymentIntentsTask;
use Modules\Finance\Domain\Contracts\CurrencyConverter;
use Modules\Finance\Domain\Contracts\DiscountResolver;
use Modules\Finance\Domain\Listeners\EndAwardsOnLearnerWithdrawnListener;
use Modules\Finance\Domain\Listeners\RaiseMidTermSubjectChangeBillingListener;
use Modules\Finance\Domain\Support\AwardDiscountResolver;
use Modules\Finance\Domain\Support\CloseChecks\AllTillSessionsClosedCheck;
use Modules\Finance\Domain\Support\CloseChecks\NoDraftInvoicesCheck;
use Modules\Finance\Domain\Support\CloseChecks\SuspenseBalanceCheck;
use Modules\Finance\Domain\Support\CloseChecks\TrialBalanceBalancesCheck;
use Modules\Finance\Domain\Support\FakePaymentGatewayDriver;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Domain\Support\RateResolvingCurrencyConverter;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\AwardDiscountCommitment;
use Modules\Finance\Models\AwardUtilisation;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\BankStatement;
use Modules\Finance\Models\BankStatementLine;
use Modules\Finance\Models\BillingRun;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\DebtorChaseNote;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateSource;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\FeeStructureItem;
use Modules\Finance\Models\FeeWaiver;
use Modules\Finance\Models\FxRevaluation;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;
use Modules\Finance\Models\PaymentPlan;
use Modules\Finance\Models\PostingRule;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptAllocation;
use Modules\Finance\Models\ReceiptTender;
use Modules\Finance\Models\ReconciliationRun;
use Modules\Finance\Models\ReminderSchedule;
use Modules\Finance\Models\ReminderSent;
use Modules\Finance\Models\ReportGateOverride;
use Modules\Finance\Models\SchemeBudgetEnvelope;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\Finance\Models\SchoolCurrency;
use Modules\Finance\Models\SuspenseItem;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;
use Modules\People\Domain\Events\LearnerWithdrawn;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FinanceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Finance';

    protected string $nameLower = 'finance';

    protected array $commands = [
        SendFeeRemindersCommand::class,
        CheckPaymentPlanBreachesCommand::class,
        SeedFinanceSchoolSetupCommand::class,
        SeedFinanceUsersCommand::class,
        SeedFinanceLedgerCommand::class,
        SeedFinanceCurrencyCommand::class,
        SeedFinanceFeesCommand::class,
        SeedFinanceDebtorsCommand::class,
        SeedFinanceTillCommand::class,
        SeedFinanceAllCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(CurrencyConverter::class, RateResolvingCurrencyConverter::class);
        $this->app->bind(DiscountResolver::class, AwardDiscountResolver::class);

        $this->app->singleton(PaymentGatewayDriverRegistry::class, function (): PaymentGatewayDriverRegistry {
            $registry = new PaymentGatewayDriverRegistry;
            $registry->register(new FakePaymentGatewayDriver);

            return $registry;
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerEventListeners();
        $this->registerCloseChecklistItems();
        $this->registerPermissions();
        $this->registerLivewireRoutes();
        $this->registerScheduledTasks();
        LearnerClearanceRegistry::register('fees', function (int $schoolId, int $studentId): array {
            $owed = (int) Invoice::query()->where('student_id', $studentId)->where('balance_minor', '>', 0)->sum('balance_minor');

            if ($owed === 0 || PaymentPlan::query()->where('student_id', $studentId)->where('status', 'active')->exists()) {
                return [];
            }

            return ['Fees outstanding with no active payment arrangement ('.number_format($owed / 100, 2).')'];
        });
        $this->registerNotificationKeys();
    }

    /**
     * Book B FIN-03 §4/BR-FIN-03-015/017. Finance's first two entries
     * on Book A CORE-12's scheduled-task registry — see
     * `CoreServiceProvider::registerScheduledTasks()`'s own docblock
     * for why `routes/console.php` derives the actual cron schedule
     * from this registry rather than a second hand-kept list.
     */
    private function registerScheduledTasks(): void
    {
        ScheduledTaskHandlerRegistry::register(
            key: 'finance.poll_pending_intents',
            moduleCode: 'FIN-05',
            name: 'Poll Pending Payment Intents',
            cron: '*/5 * * * *',
            handler: PollPendingPaymentIntentsTask::class,
            description: 'Polls gateways for intents whose webhook never arrived.',
            alertIfNotRunWithinMinutes: 60,
        );

        ScheduledTaskRegistry::register(new ScheduledTaskDefinitionData(
            key: 'finance.send_fee_reminders',
            moduleCode: 'FIN-03',
            name: 'Send Fee Reminders',
            command: 'serp:send-fee-reminders',
            scheduleExpression: '0 7 * * *',
            description: 'Sends every reminder-ladder rung due to fire, across every school.',
            alertIfNotRunWithinMinutes: 1560,
        ));

        ScheduledTaskRegistry::register(new ScheduledTaskDefinitionData(
            key: 'finance.check_payment_plan_breaches',
            moduleCode: 'FIN-03',
            name: 'Check Payment Plan Breaches',
            command: 'serp:check-payment-plan-breaches',
            scheduleExpression: '0 2 * * *',
            description: 'Marks an active payment plan breached once an instalment is overdue past the grace period.',
            alertIfNotRunWithinMinutes: 1560,
        ));
    }

    /**
     * Book B FIN-03 §4/BR-FIN-03-015, registered the same way Book D
     * ACA-04 registers its own first real notification-bus caller — see
     * `AcademicServiceProvider::registerNotificationKeys()`'s own
     * docblock for the full wiring checklist this follows.
     */
    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'finance.fee_reminder',
            variables: ['guardian.name', 'invoice.number', 'invoice.balance', 'invoice.currency', 'invoice.due_date'],
            defaultChannels: ['sms', 'email'],
            defaultAudience: 'fee_responsible',
            isUrgent: false,
            isTransactional: false,
        ));
    }

    /**
     * Mirrors `CoreServiceProvider::boot()`'s own `Livewire::addLocation()`
     * call — required the moment a module has its own class literally
     * named `Index` (`Journals\Index`, `CostCentres\Index`,
     * `PostingRules\Index` here): Livewire's implicit-binding-substitution
     * step re-derives a canonical component name from the class before
     * the controller runs, strips the trailing `.index` segment, and
     * needs a registered class location to resolve that shortened name
     * back to a real class. See that method's docblock for the full
     * "Unable to find component" failure this prevents.
     */
    private function registerLivewireRoutes(): void
    {
        Livewire::addLocation(classNamespace: 'Modules\Finance\Livewire');

        Route::middleware('web')->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/ledger.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/currency.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/billing.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/debtors.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/till.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/gateways.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/discounts.php');
        });
    }

    /**
     * Book B FIN-01 §10. Registered via the same
     * `PermissionRegistry::register($moduleCode, [...])` mechanism
     * `CoreServiceProvider` uses — `SyncPermissionCatalogueAction` lower-
     * cases the module code, so `'FINANCE'` here produces names like
     * `finance.account.view`, matching this book's own spec exactly.
     * FIN-01, FIN-02, FIN-03, FIN-04, FIN-05, and FIN-06's permissions
     * are registered so far.
     */
    private function registerPermissions(): void
    {
        PermissionRegistry::register('FINANCE', [
            'discount_scheme.view' => ['description' => 'View discount, bursary and scholarship schemes.'],
            'discount_scheme.manage' => ['description' => 'Define schemes, tier bands and budget envelopes.'],
            'scholarship.apply' => ['description' => 'Submit a scholarship application on a learner’s behalf.'],
            'scholarship.review' => ['description' => 'Review applications, including means data and supporting documents.'],
            'scholarship.decide' => ['description' => 'Record the committee’s decision on an application.', 'dangerous' => true],
            'award.view' => ['description' => 'View awards by scheme, learner and status.'],
            'award.grant' => ['description' => 'Grant a discount, bursary or scholarship award.', 'dangerous' => true],
            'award.revoke' => ['description' => 'Revoke an award from a given date.', 'dangerous' => true],
            'award.review' => ['description' => 'Run renewal-point condition reviews on awards.'],
            'report.discounts' => ['description' => 'View the cost-of-generosity report.'],
            'account.view' => ['description' => 'View the chart of accounts and account ledgers.'],
            'account.manage' => ['description' => 'Create, edit, and deactivate accounts.'],
            'cost_centre.view' => ['description' => 'View cost centres.'],
            'cost_centre.manage' => ['description' => 'Create and edit cost centres.'],
            'journal.view' => ['description' => 'View journals and journal lines.'],
            'journal.create_manual' => ['description' => 'Create a manual journal entry (draft, pending approval).'],
            'journal.approve' => ['description' => 'Approve a draft manual journal, posting it.'],
            'journal.reverse' => ['description' => 'Reverse a posted journal.', 'dangerous' => true],
            'journal.reverse_cross_period' => ['description' => 'Reverse a journal into a different, already-closed period.', 'dangerous' => true],
            'posting_rule.view' => ['description' => 'View posting rules.'],
            'posting_rule.manage' => ['description' => 'Edit which accounts a financial event posts to.', 'dangerous' => true],
            'report.trial_balance' => ['description' => 'View the trial balance report.'],
            'integrity.view' => ['description' => 'View cached-vs-source balance verification and trigger a rebuild.'],
            'opening_balance.import' => ['description' => 'Import opening balances as posted journals.', 'dangerous' => true],
            'currency.manage' => ['description' => 'Register which currencies a school transacts in and set the base currency.', 'dangerous' => true],
            'rate.view' => ['description' => 'View exchange rate history, the impact simulator, and the conversion audit log.'],
            'rate.capture' => ['description' => 'Capture a new exchange rate.'],
            'rate.approve' => ['description' => 'Approve or reject a pending exchange rate.', 'dangerous' => true],
            'fx.revalue' => ['description' => 'Run or reverse a period-end FX revaluation.', 'dangerous' => true],
            'fee_component.view' => ['description' => 'View the fee component catalogue.'],
            'fee_component.manage' => ['description' => 'Create and edit fee components.'],
            'fee_structure.view' => ['description' => 'View fee structures, versions, and the fee simulator.'],
            'fee_structure.manage' => ['description' => 'Create and revise fee structures.'],
            'fee_structure.approve' => ['description' => 'Activate a fee structure, superseding whichever version was active.', 'dangerous' => true],
            'billing.view' => ['description' => 'View billing run history.'],
            'billing.run' => ['description' => 'Compute a billing run and view its preview.'],
            'billing.approve' => ['description' => 'Approve a computed billing run.', 'dangerous' => true],
            'billing.commit' => ['description' => 'Commit an approved billing run, raising invoices and posting journals.', 'dangerous' => true],
            'ad_hoc.create' => ['description' => 'Raise an ad hoc charge for a learner or a class.'],
            'ad_hoc.approve' => ['description' => 'Approve an ad hoc charge above the approval threshold.', 'dangerous' => true],
            'fee.view' => ['description' => 'View a learner\'s fee assignment, lines, and resolution trace.'],
            'invoice.view' => ['description' => 'View invoices and invoice lines.'],
            'invoice.issue' => ['description' => 'Reissue an invoice after voiding one.', 'dangerous' => true],
            'invoice.void' => ['description' => 'Void an issued invoice.', 'dangerous' => true],
            'credit_note.create' => ['description' => 'Raise a credit note.'],
            'credit_note.approve' => ['description' => 'Approve a credit note above the approval threshold.', 'dangerous' => true],
            'statement.generate' => ['description' => 'Generate a learner or guardian statement for any date range.'],
            'report.debtors' => ['description' => 'View the aged debtors report.'],
            'debtor.manage' => ['description' => 'Use the debtor workbench and record chase notes.'],
            'reminder.manage' => ['description' => 'Manage the fee reminder ladder.'],
            'payment_plan.create' => ['description' => 'Propose a payment plan.'],
            'payment_plan.approve' => ['description' => 'Approve, cancel, or record instalment payments on a payment plan.', 'dangerous' => true],
            'waiver.request' => ['description' => 'Request a fee waiver.'],
            'waiver.approve' => ['description' => 'Approve or reject a requested fee waiver.', 'dangerous' => true],
            'write_off.request' => ['description' => 'Request a fee write-off.'],
            'write_off.approve' => ['description' => 'Approve or reject a requested fee write-off.', 'dangerous' => true],
            'refund.request' => ['description' => 'Request a refund against a learner\'s credit balance.'],
            'refund.approve' => ['description' => 'Approve and post a requested refund.', 'dangerous' => true],
            'liability.manage' => ['description' => 'Set up who pays what share of a learner\'s fees.'],
            'report_gate.override' => ['description' => 'Override the report-card release balance gate for one learner.', 'dangerous' => true],
            'till.operate' => ['description' => 'Open a till, capture receipts, and perform the blind cash-up.'],
            'till.supervise' => ['description' => 'Sign off a till variance beyond tolerance.', 'dangerous' => true],
            'till.view' => ['description' => 'View till session history and daily banking.'],
            'receipt.create' => ['description' => 'Capture a receipt at an open till.'],
            'receipt.view' => ['description' => 'View receipts.'],
            'receipt.void' => ['description' => 'Void a receipt.', 'dangerous' => true],
            'receipt.reallocate' => ['description' => 'Reallocate a receipt across a learner\'s invoices.', 'dangerous' => true],
            'suspense.view' => ['description' => 'View the suspense workbench.'],
            'suspense.manage' => ['description' => 'Match and resolve suspense items.'],
            'report.collections' => ['description' => 'View the collections dashboard.'],
            'gateway.view' => ['description' => 'View payment gateways, intents, and the webhook log.'],
            'gateway.manage' => ['description' => 'Register and edit payment gateways, including credentials.', 'dangerous' => true],
            'gateway.force_settle' => ['description' => 'Force-settle a payment intent without gateway confirmation.', 'dangerous' => true],
            'bank.view' => ['description' => 'View bank accounts and statements.'],
            'bank.manage' => ['description' => 'Create and edit bank accounts.'],
            'bank.reconcile' => ['description' => 'Import bank statements and match statement lines.'],
            'reconciliation.view' => ['description' => 'View and run the four-way reconciliation.'],
            'reconciliation.resolve' => ['description' => 'Resolve a reconciliation exception.', 'dangerous' => true],
        ]);
    }

    /**
     * Book H3 FIN-12 §4 — this module's own entries on the real,
     * previously-empty `Modules\Core\Domain\Registry\CloseChecklistRegistry`
     * (Book A CORE-03's own engine). See each check's own docblock.
     */
    private function registerCloseChecklistItems(): void
    {
        CloseChecklistRegistry::register(new TrialBalanceBalancesCheck);
        CloseChecklistRegistry::register(new SuspenseBalanceCheck);
        CloseChecklistRegistry::register(new AllTillSessionsClosedCheck);
        CloseChecklistRegistry::register(new NoDraftInvoicesCheck);
    }

    /**
     * Book B FIN-02 §4/BR-FIN-02-005. Closes the billing-integration gap
     * `BillMidTermSubjectChangeAction`'s own docblock used to name — see
     * `RaiseMidTermSubjectChangeBillingListener`.
     */
    private function registerEventListeners(): void
    {
        Event::listen(SubjectEnrolmentAdded::class, [RaiseMidTermSubjectChangeBillingListener::class, 'handleAdded']);
        Event::listen(SubjectEnrolmentDropped::class, [RaiseMidTermSubjectChangeBillingListener::class, 'handleDropped']);
        Event::listen(LearnerWithdrawn::class, EndAwardsOnLearnerWithdrawnListener::class);
    }

    /**
     * Book B FIN-01 §11. Registered here the same way Core registers its
     * own settings from `CoreServiceProvider::registerSettingDefinitions()`
     * — see that method's docblock for why a sync migration re-syncing
     * `SettingDefinitionRegistry` after every provider's boot() is safe.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['finance.max_future_dating_days', 'int', '0', 'Days beyond today a journal\'s effective_at may be dated.'],
            ['finance.require_approval_for_manual_journal', 'bool', '1', 'Manual journals always require approval by a different user (locked on all tiers).'],
            ['finance.manual_journal_approval_threshold_minor', 'int', '0', 'Manual journal amount above which approval is required. 0 means all manual journals need approval.'],
            ['finance.balance_cache_rebuild_hour', 'int', '2', 'Hour of day the nightly balance cache rebuild runs.'],
            ['finance.allow_negative_bank_balance', 'bool', '1', 'Whether a bank account may carry a negative (overdraft) balance.'],
            ['finance.rounding_tolerance_minor', 'int', '5', 'Base-currency rounding residual above which a journal is refused rather than auto-rounded.'],
            ['finance.billing_variance_alert_percent', 'int', '25', 'Variance from a learner\'s previous term total, in percent, above which a billing run flags an exception.'],
            ['finance.ad_hoc_approval_threshold_minor', 'int', '5000', 'Ad hoc charge amount above which an approving user is required.'],
            ['finance.default_proration_basis', 'string', 'day', 'Default proration basis for a new fee structure item.'],
            ['finance.allow_zero_value_invoices', 'bool', '0', 'Whether a zero-value fee assignment may still be invoiced.'],
            ['finance.one_off_check_scope', 'string', 'school', 'Scope of the one-off charging-history check: school (all years) or academic_year.'],
            ['finance.auto_bill_on_enrolment', 'bool', '1', 'Whether a new enrolment triggers immediate pro-rated billing.'],
            ['finance.auto_recalculate_on_subject_change', 'bool', '1', 'Whether a subject add/drop triggers immediate fee recalculation.'],
            ['finance.invoice_due_days_after_issue', 'int', '14', 'Days after issue an invoice falls due.'],
            ['finance.aging_buckets', 'string', '30,60,90,120', 'Aged-debtor bucket boundaries in days, comma-separated.'],
            ['finance.credit_note_approval_threshold_minor', 'int', '0', 'Credit note amount above which an approving user is required. 0 means all credit notes need approval.'],
            ['finance.write_off_approval_threshold_minor', 'int', '0', 'Write-off amount above which an approving user is required.'],
            ['finance.payment_plan_grace_days', 'int', '5', 'Days a missed instalment is tolerated before a payment plan is marked breached.'],
            ['finance.report_gate_enabled', 'bool', '0', 'Whether report card release checks a learner\'s outstanding balance.'],
            ['finance.report_gate_threshold_minor', 'int', '0', 'Balance above which report card release is withheld, when the gate is enabled.'],
            ['finance.report_gate_threshold_currency', 'string', 'USD', 'Currency the report gate threshold is denominated in.'],
            ['finance.statement_show_all_currencies', 'bool', '1', 'Whether a statement shows every currency a learner has activity in, not just one.'],
            ['finance.till_variance_tolerance_minor', 'int', '100', 'Till cash-up variance, per currency, above which a written reason and supervisor sign-off are required.'],
            ['finance.default_allocation_strategy', 'string', 'auto_oldest', 'Default receipt allocation strategy: auto_oldest, auto_priority, or manual.'],
            ['finance.allow_manual_allocation', 'bool', '1', 'Whether a cashier may manually order which invoices a receipt settles.'],
            ['finance.suspense_escalation_days', 'int', '3', 'Age in days after which an unresolved suspense item escalates to the bursar.'],
            ['finance.require_payer_phone', 'bool', '0', 'Whether a payer phone number is mandatory on every receipt.'],
            ['finance.receipt_sms_enabled', 'bool', '1', 'Whether a receipt confirmation SMS/WhatsApp dispatches on posting.'],
            ['finance.cheque_clearing_days', 'int', '5', 'Days a cheque tender is expected to take to clear.'],
            ['finance.max_cash_receipt_minor', 'int', '0', 'Maximum cash tender amount per receipt. 0 means no limit.'],
            ['finance.payment_intent_ttl_minutes', 'int', '30', 'Minutes after which an unsettled payment intent expires.'],
            ['finance.auto_match_confidence_threshold', 'int', '85', 'Bank statement auto-match confidence, 0-100, below which a human must confirm.'],
            ['finance.gateway_health_check_minutes', 'int', '5', 'Minutes between gateway health checks.'],
            ['finance.reconciliation_run_hour', 'int', '4', 'Hour of day the daily four-way reconciliation runs.'],
            ['finance.block_period_close_on_reconciliation_exceptions', 'bool', '1', 'Whether an unresolved reconciliation exception blocks financial period close.'],
            ['finance.gateway_fee_borne_by', 'string', 'school', 'Who bears the gateway fee: school or payer.'],
            ['finance.min_online_payment_minor', 'int', '100', 'Minimum amount accepted for an online gateway payment.'],
            ['finance.award_approval_threshold_minor', 'int', '0', 'Fixed-amount award value above which CORE-07 approval is required. 0 means all fixed-amount awards need approval (Book K FIN-07 BR-FIN-07-008).'],
            ['finance.staff_child_discount_notice_days', 'int', '30', 'Days a staff-child discount survives past the staff member\'s exited_on date (Book K FIN-07 BR-FIN-07-004).'],
            ['finance.condition_review_trigger', 'string', 'on_results_publication', 'When a conditional award\'s condition is (re)checked (Book K FIN-07 BR-FIN-07-011).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'FIN',
                'group_key' => 'finance',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => $dataType === 'bool' ? 'toggle' : 'text',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A Part 1.11's tenancy isolation test generator — every
     * `BelongsToSchool` model this module owns registers here.
     * `AccountBalance`/`SubledgerBalance` are deliberately absent: cache
     * tables written only by the rebuild service, same reasoning as
     * Book A's CORE-08/12 infrastructure tables.
     */
    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(
            Account::class,
            fn (School $school): Account => Account::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(
            CostCentre::class,
            fn (School $school): CostCentre => CostCentre::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(Journal::class, function (School $school): Journal {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return Journal::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(JournalLine::class, function (School $school): JournalLine {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $journal = Journal::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
            ]);
            $account = Account::factory()->for($school)->create();

            return JournalLine::factory()->create([
                'school_id' => $school->id,
                'journal_id' => $journal->id,
                'account_id' => $account->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(
            PostingRule::class,
            fn (School $school): PostingRule => PostingRule::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(
            SchoolCurrency::class,
            fn (School $school): SchoolCurrency => SchoolCurrency::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(ExchangeRate::class, function (School $school): ExchangeRate {
            $source = ExchangeRateSource::factory()->create(['school_id' => null]);

            return ExchangeRate::factory()->for($school)->create(['source_id' => $source->id]);
        });

        TenantModelRegistry::register(FxRevaluation::class, function (School $school): FxRevaluation {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return FxRevaluation::factory()->for($school)->create(['term_id' => $term->id]);
        });

        TenantModelRegistry::register(FeeComponent::class, function (School $school): FeeComponent {
            $income = Account::factory()->for($school)->income()->create();
            $debtor = Account::factory()->for($school)->controlAccount('student')->create();

            return FeeComponent::factory()->for($school)->create([
                'income_account_id' => $income->id,
                'debtor_account_id' => $debtor->id,
            ]);
        });

        TenantModelRegistry::register(FeeStructure::class, function (School $school): FeeStructure {
            $year = AcademicYear::factory()->for($school)->create();

            return FeeStructure::factory()->for($school)->create(['academic_year_id' => $year->id]);
        });

        // FeeStructureRule is deliberately absent: it carries no
        // `school_id` of its own (Book B FIN-02 §2's literal schema —
        // it's always reached through its owning FeeStructure), so it
        // has no tenancy boundary for the isolation generator to test.

        TenantModelRegistry::register(FeeStructureItem::class, function (School $school): FeeStructureItem {
            $year = AcademicYear::factory()->for($school)->create();
            $structure = FeeStructure::factory()->for($school)->create(['academic_year_id' => $year->id]);
            $component = FeeComponent::factory()->for($school)->create();

            return FeeStructureItem::factory()->for($school)->create([
                'structure_id' => $structure->id,
                'component_id' => $component->id,
            ]);
        });

        TenantModelRegistry::register(LearnerFeeAssignment::class, function (School $school): LearnerFeeAssignment {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();
            $structure = FeeStructure::factory()->for($school)->create(['academic_year_id' => $year->id]);

            return LearnerFeeAssignment::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'structure_id' => $structure->id,
            ]);
        });

        TenantModelRegistry::register(LearnerFeeLine::class, function (School $school): LearnerFeeLine {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();
            $structure = FeeStructure::factory()->for($school)->create(['academic_year_id' => $year->id]);
            $assignment = LearnerFeeAssignment::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'structure_id' => $structure->id,
            ]);
            $component = FeeComponent::factory()->for($school)->create();

            return LearnerFeeLine::factory()->create([
                'school_id' => $school->id,
                'assignment_id' => $assignment->id,
                'component_id' => $component->id,
            ]);
        });

        TenantModelRegistry::register(AdHocCharge::class, function (School $school): AdHocCharge {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();
            $component = FeeComponent::factory()->for($school)->create();

            return AdHocCharge::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'component_id' => $component->id,
            ]);
        });

        TenantModelRegistry::register(BillingRun::class, function (School $school): BillingRun {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return BillingRun::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(Invoice::class, function (School $school): Invoice {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();

            return Invoice::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(InvoiceLine::class, function (School $school): InvoiceLine {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();
            $invoice = Invoice::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
            ]);
            $component = FeeComponent::factory()->for($school)->create();

            return InvoiceLine::factory()->create([
                'school_id' => $school->id,
                'invoice_id' => $invoice->id,
                'component_id' => $component->id,
            ]);
        });

        TenantModelRegistry::register(CreditNote::class, function (School $school): CreditNote {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();

            return CreditNote::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
            ]);
        });

        // CreditNoteLine is deliberately absent — same reasoning as
        // FeeStructureRule: no `school_id` of its own, always reached
        // through its owning CreditNote.

        TenantModelRegistry::register(FeeWaiver::class, function (School $school): FeeWaiver {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return FeeWaiver::factory()->create([
                'school_id' => $school->id,
                'term_id' => $term->id,
                'student_id' => Student::factory()->for($school)->create()->id,
            ]);
        });

        TenantModelRegistry::register(
            PaymentPlan::class,
            fn (School $school): PaymentPlan => PaymentPlan::factory()->create([
                'school_id' => $school->id,
                'student_id' => Student::factory()->for($school)->create()->id,
                'party_id' => Guardian::factory()->for($school)->create()->id,
            ]),
        );

        // PaymentPlanInstalment is deliberately absent — same reasoning
        // as FeeStructureRule/CreditNoteLine: no `school_id` of its own,
        // always reached through its owning PaymentPlan.

        TenantModelRegistry::register(
            ReminderSchedule::class,
            fn (School $school): ReminderSchedule => ReminderSchedule::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(ReminderSent::class, function (School $school): ReminderSent {
            $schedule = ReminderSchedule::factory()->for($school)->create();
            $invoice = Invoice::factory()->for($school)->create();

            return ReminderSent::factory()->create([
                'school_id' => $school->id,
                'schedule_id' => $schedule->id,
                'invoice_id' => $invoice->id,
            ]);
        });

        TenantModelRegistry::register(
            DebtorChaseNote::class,
            fn (School $school): DebtorChaseNote => DebtorChaseNote::factory()->create([
                'school_id' => $school->id,
                'student_id' => Student::factory()->for($school)->create()->id,
            ]),
        );

        TenantModelRegistry::register(ReportGateOverride::class, function (School $school): ReportGateOverride {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return ReportGateOverride::factory()->create([
                'school_id' => $school->id,
                'student_id' => Student::factory()->for($school)->create()->id,
                'term_id' => $term->id,
            ]);
        });

        TenantModelRegistry::register(Till::class, function (School $school): Till {
            $cashAccount = Account::factory()->for($school)->create();

            return Till::factory()->for($school)->create(['cash_account_id' => $cashAccount->id]);
        });

        TenantModelRegistry::register(TillSession::class, function (School $school): TillSession {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $cashAccount = Account::factory()->for($school)->create();
            $till = Till::factory()->for($school)->create(['cash_account_id' => $cashAccount->id]);

            return TillSession::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'till_id' => $till->id,
            ]);
        });

        TenantModelRegistry::register(Receipt::class, function (School $school): Receipt {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();

            return Receipt::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(ReceiptTender::class, function (School $school): ReceiptTender {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();
            $receipt = Receipt::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
            ]);

            return ReceiptTender::factory()->create(['school_id' => $school->id, 'receipt_id' => $receipt->id]);
        });

        TenantModelRegistry::register(ReceiptAllocation::class, function (School $school): ReceiptAllocation {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $student = Student::factory()->for($school)->create();
            $receipt = Receipt::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
            ]);

            return ReceiptAllocation::factory()->create(['school_id' => $school->id, 'receipt_id' => $receipt->id]);
        });

        TenantModelRegistry::register(SuspenseItem::class, function (School $school): SuspenseItem {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $receipt = Receipt::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'student_id' => null,
                'is_suspense' => true,
            ]);

            return SuspenseItem::factory()->create(['school_id' => $school->id, 'receipt_id' => $receipt->id]);
        });

        TenantModelRegistry::register(PaymentGateway::class, function (School $school): PaymentGateway {
            $settlement = Account::factory()->for($school)->create();
            $fee = Account::factory()->for($school)->create();

            return PaymentGateway::factory()->for($school)->create([
                'settlement_account_id' => $settlement->id,
                'fee_account_id' => $fee->id,
            ]);
        });

        TenantModelRegistry::register(PaymentIntent::class, function (School $school): PaymentIntent {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $settlement = Account::factory()->for($school)->create();
            $fee = Account::factory()->for($school)->create();
            $gateway = PaymentGateway::factory()->for($school)->create([
                'settlement_account_id' => $settlement->id,
                'fee_account_id' => $fee->id,
            ]);
            $student = Student::factory()->for($school)->create();

            return PaymentIntent::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'gateway_id' => $gateway->id,
                'student_id' => $student->id,
            ]);
        });

        TenantModelRegistry::register(BankAccount::class, function (School $school): BankAccount {
            $glAccount = Account::factory()->for($school)->create();

            return BankAccount::factory()->for($school)->create(['gl_account_id' => $glAccount->id]);
        });

        TenantModelRegistry::register(BankStatement::class, function (School $school): BankStatement {
            $glAccount = Account::factory()->for($school)->create();
            $bankAccount = BankAccount::factory()->for($school)->create(['gl_account_id' => $glAccount->id]);

            return BankStatement::factory()->create(['school_id' => $school->id, 'bank_account_id' => $bankAccount->id]);
        });

        TenantModelRegistry::register(BankStatementLine::class, function (School $school): BankStatementLine {
            $glAccount = Account::factory()->for($school)->create();
            $bankAccount = BankAccount::factory()->for($school)->create(['gl_account_id' => $glAccount->id]);
            $statement = BankStatement::factory()->create(['school_id' => $school->id, 'bank_account_id' => $bankAccount->id]);

            return BankStatementLine::factory()->create(['school_id' => $school->id, 'statement_id' => $statement->id]);
        });

        TenantModelRegistry::register(
            ReconciliationRun::class,
            fn (School $school): ReconciliationRun => ReconciliationRun::factory()->for($school)->create(),
        );

        // GatewayWebhook is deliberately absent — it carries no
        // `BelongsToSchool` scope (`school_id` is nullable, and a
        // webhook may legitimately arrive before the school/gateway
        // can even be resolved), so it has no tenancy boundary for the
        // isolation generator to test — same reasoning as
        // `FeeStructureRule`/`CreditNoteLine`.

        TenantModelRegistry::register(
            DiscountScheme::class,
            fn (School $school): DiscountScheme => DiscountScheme::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(
            SchemeBudgetEnvelope::class,
            fn (School $school): SchemeBudgetEnvelope => SchemeBudgetEnvelope::factory()->create([
                'school_id' => $school->id,
                'scheme_id' => DiscountScheme::factory()->for($school)->create()->id,
            ]),
        );

        TenantModelRegistry::register(
            ScholarshipApplication::class,
            fn (School $school): ScholarshipApplication => ScholarshipApplication::factory()->create([
                'school_id' => $school->id,
                'scheme_id' => DiscountScheme::factory()->for($school)->schemeType('application_based')->create()->id,
                'student_id' => Student::factory()->for($school)->create()->id,
            ]),
        );

        TenantModelRegistry::register(
            DiscountAward::class,
            fn (School $school): DiscountAward => DiscountAward::factory()->create([
                'school_id' => $school->id,
                'scheme_id' => DiscountScheme::factory()->for($school)->create()->id,
                'student_id' => Student::factory()->for($school)->create()->id,
            ]),
        );

        TenantModelRegistry::register(AwardUtilisation::class, function (School $school): AwardUtilisation {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $scheme = DiscountScheme::factory()->for($school)->create();
            $student = Student::factory()->for($school)->create();
            $award = DiscountAward::factory()->create(['school_id' => $school->id, 'scheme_id' => $scheme->id, 'student_id' => $student->id]);
            $component = FeeComponent::factory()->for($school)->create();
            $journal = Journal::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id]);
            $assignment = LearnerFeeAssignment::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'student_id' => $student->id]);
            $feeLine = LearnerFeeLine::factory()->create(['school_id' => $school->id, 'assignment_id' => $assignment->id, 'component_id' => $component->id]);

            return AwardUtilisation::factory()->create([
                'school_id' => $school->id,
                'award_id' => $award->id,
                'term_id' => $term->id,
                'component_id' => $component->id,
                'fee_line_id' => $feeLine->id,
                'journal_id' => $journal->id,
            ]);
        });

        TenantModelRegistry::register(AwardDiscountCommitment::class, function (School $school): AwardDiscountCommitment {
            $scheme = DiscountScheme::factory()->for($school)->create();
            $student = Student::factory()->for($school)->create();
            $award = DiscountAward::factory()->create(['school_id' => $school->id, 'scheme_id' => $scheme->id, 'student_id' => $student->id]);
            $component = FeeComponent::factory()->for($school)->create();
            $assignment = LearnerFeeAssignment::factory()->create(['school_id' => $school->id, 'student_id' => $student->id]);
            $feeLine = LearnerFeeLine::factory()->create(['school_id' => $school->id, 'assignment_id' => $assignment->id, 'component_id' => $component->id]);

            return AwardDiscountCommitment::factory()->create([
                'school_id' => $school->id,
                'fee_line_id' => $feeLine->id,
                'award_id' => $award->id,
                'scheme_id' => $scheme->id,
            ]);
        });
    }
}
