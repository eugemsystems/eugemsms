<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use Illuminate\Support\Facades\Event;
use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Contracts\CurrencyConverter;
use Modules\Finance\Domain\Listeners\RaiseMidTermSubjectChangeBillingListener;
use Modules\Finance\Domain\Support\CloseChecks\AllTillSessionsClosedCheck;
use Modules\Finance\Domain\Support\CloseChecks\NoDraftInvoicesCheck;
use Modules\Finance\Domain\Support\CloseChecks\SuspenseBalanceCheck;
use Modules\Finance\Domain\Support\CloseChecks\TrialBalanceBalancesCheck;
use Modules\Finance\Domain\Support\FakePaymentGatewayDriver;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Domain\Support\RateResolvingCurrencyConverter;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\BankStatement;
use Modules\Finance\Models\BankStatementLine;
use Modules\Finance\Models\BillingRun;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateSource;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\FeeStructureItem;
use Modules\Finance\Models\FxRevaluation;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;
use Modules\Finance\Models\PostingRule;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptAllocation;
use Modules\Finance\Models\ReceiptTender;
use Modules\Finance\Models\ReconciliationRun;
use Modules\Finance\Models\SchoolCurrency;
use Modules\Finance\Models\SuspenseItem;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;
use Modules\People\Models\Student;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FinanceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Finance';

    protected string $nameLower = 'finance';

    public function register(): void
    {
        parent::register();

        $this->app->bind(CurrencyConverter::class, RateResolvingCurrencyConverter::class);

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
    }
}
