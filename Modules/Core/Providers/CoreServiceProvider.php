<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Console\Commands\InstallCommand;
use Modules\Core\Console\Commands\InstallStatusCommand;
use Modules\Core\Console\Commands\InstallVerifyCommand;
use Modules\Core\Console\Commands\SeedZimbabweCommand;
use Modules\Core\Console\Commands\UpgradeCommand;
use Modules\Core\Domain\Contracts\Auth\BreachedPasswordChecker;
use Modules\Core\Domain\Contracts\Auth\OtpDeliveryChannel;
use Modules\Core\Domain\Contracts\Documents\DocumentRenderer;
use Modules\Core\Domain\Contracts\Files\VirusScanner;
use Modules\Core\Domain\Contracts\Install\BackupProvider;
use Modules\Core\Domain\Contracts\Install\LicenceClient;
use Modules\Core\Domain\Contracts\Schools\AuditLogger;
use Modules\Core\Domain\Contracts\Schools\SchoolLifecycleGuard;
use Modules\Core\Domain\Contracts\Sessions\SnapshotPayloadProvider;
use Modules\Core\Domain\Contracts\Sessions\TermBalanceProvider;
use Modules\Core\Domain\Contracts\Settings\TenantTierProvider;
use Modules\Core\Domain\DataObjects\Files\FileCategoryDefinition;
use Modules\Core\Domain\DataObjects\Scheduling\ScheduledTaskDefinitionData;
use Modules\Core\Domain\Registry\FileCategoryRegistry;
use Modules\Core\Domain\Registry\HealthCheckRegistry;
use Modules\Core\Domain\Registry\IntegrityCheckRegistry;
use Modules\Core\Domain\Registry\RolloverHandlerRegistry;
use Modules\Core\Domain\Registry\ScheduledTaskRegistry;
use Modules\Core\Domain\Registry\SeedPackRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Domain\Support\Audit\FinancialAuditChainCheck;
use Modules\Core\Domain\Support\Audit\NumberingGapCheck;
use Modules\Core\Domain\Support\Audit\PendingIntegrityCheck;
use Modules\Core\Domain\Support\Audit\SnapshotChainCheck;
use Modules\Core\Domain\Support\Auth\NullBreachedPasswordChecker;
use Modules\Core\Domain\Support\Auth\NullOtpDeliveryChannel;
use Modules\Core\Domain\Support\Backups\BackupFreshnessHealthCheck;
use Modules\Core\Domain\Support\Backups\PreUpgradeBackupProvider;
use Modules\Core\Domain\Support\Backups\RestoreVerificationHealthCheck;
use Modules\Core\Domain\Support\Documents\DefaultTemplateFilters;
use Modules\Core\Domain\Support\Documents\HtmlDocumentRenderer;
use Modules\Core\Domain\Support\Files\NullVirusScanner;
use Modules\Core\Domain\Support\Files\SignedFileUrlGenerator;
use Modules\Core\Domain\Support\Install\CalendarSeedPack;
use Modules\Core\Domain\Support\Install\HttpLicenceClient;
use Modules\Core\Domain\Support\Install\PendingSeedPack;
use Modules\Core\Domain\Support\Install\RoleSeedPack;
use Modules\Core\Domain\Support\Scheduling\DatabaseConnectionHealthCheck;
use Modules\Core\Domain\Support\Scheduling\FailedJobsHealthCheck;
use Modules\Core\Domain\Support\Scheduling\NotificationFailureRateHealthCheck;
use Modules\Core\Domain\Support\Scheduling\OldestQueuedJobHealthCheck;
use Modules\Core\Domain\Support\Scheduling\PendingHealthCheck;
use Modules\Core\Domain\Support\Scheduling\QueueDepthHealthCheck;
use Modules\Core\Domain\Support\Scheduling\RedisConnectionHealthCheck;
use Modules\Core\Domain\Support\Scheduling\SchedulerLastRunHealthCheck;
use Modules\Core\Domain\Support\Scheduling\StorageFreeSpaceHealthCheck;
use Modules\Core\Domain\Support\SchoolContextManager;
use Modules\Core\Domain\Support\Schools\NullAuditLogger;
use Modules\Core\Domain\Support\Schools\NullSchoolLifecycleGuard;
use Modules\Core\Domain\Support\SessionContextManager;
use Modules\Core\Domain\Support\Sessions\GenerateRolloverReportHandler;
use Modules\Core\Domain\Support\Sessions\NullSnapshotPayloadProvider;
use Modules\Core\Domain\Support\Sessions\NullTermBalanceProvider;
use Modules\Core\Domain\Support\Sessions\PendingRolloverHandler;
use Modules\Core\Domain\Support\Sessions\TakePostCloseSnapshotHandler;
use Modules\Core\Domain\Support\Sessions\TakePreCloseSnapshotHandler;
use Modules\Core\Domain\Support\Sessions\VerifyInvariantHandler;
use Modules\Core\Domain\Support\Settings\NullTenantTierProvider;
use Modules\Core\Http\Middleware\EnforceTokenAbility;
use Modules\Core\Http\Middleware\EnsureModuleEnabled;
use Modules\Core\Http\Middleware\EnsureNotInstalled;
use Modules\Core\Http\Middleware\EnsureSubscriptionActive;
use Modules\Core\Http\Middleware\EnsureVendorGuard;
use Modules\Core\Http\Middleware\RecordActivity;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Http\Middleware\SetSchoolContext;
use Modules\Core\Http\Middleware\SetSessionContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\AllocatedNumber;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\ApprovalDelegation;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\CustomFieldValue;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentBatch;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\Core\Models\NumberingSeries;
use Modules\Core\Models\PeriodReopenRequest;
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\PeriodSnapshot;
use Modules\Core\Models\PeriodStateTransition;
use Modules\Core\Models\PersonalAccessToken;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolModule;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Core\Models\TermWeek;
use Modules\Core\Models\UserAccountLink;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Core';

    protected string $nameLower = 'core';

    protected array $providers = [];

    protected array $commands = [
        InstallCommand::class,
        InstallVerifyCommand::class,
        InstallStatusCommand::class,
        UpgradeCommand::class,
        SeedZimbabweCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'core');

        $this->app->singleton(SchoolContextManager::class);
        $this->app->singleton(SessionContextManager::class);

        $this->app->bind(LicenceClient::class, HttpLicenceClient::class);
        $this->app->bind(BackupProvider::class, PreUpgradeBackupProvider::class);
        $this->app->bind(SchoolLifecycleGuard::class, NullSchoolLifecycleGuard::class);
        $this->app->bind(AuditLogger::class, NullAuditLogger::class);
        $this->app->bind(TermBalanceProvider::class, NullTermBalanceProvider::class);
        $this->app->bind(SnapshotPayloadProvider::class, NullSnapshotPayloadProvider::class);
        $this->app->bind(TenantTierProvider::class, NullTenantTierProvider::class);
        $this->app->bind(BreachedPasswordChecker::class, NullBreachedPasswordChecker::class);
        $this->app->bind(OtpDeliveryChannel::class, NullOtpDeliveryChannel::class);
        $this->app->bind(DocumentRenderer::class, HtmlDocumentRenderer::class);
        $this->app->bind(VirusScanner::class, NullVirusScanner::class);
        $this->app->singleton(SignedFileUrlGenerator::class, fn (): SignedFileUrlGenerator => new SignedFileUrlGenerator((string) config('app.key')));
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerMiddlewareAliases();
        $this->registerTenantModels();
        $this->registerSeedPacks();
        $this->registerRolloverHandlers();
        $this->registerSettingDefinitions();
        $this->registerIntegrityChecks();
        $this->registerFileCategories();
        $this->registerHealthChecks();
        $this->registerScheduledTasks();

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        DefaultTemplateFilters::register();

        // `loadRoutesFrom()` just requires the file — unlike the app's own
        // `bootstrap/app.php` routing (which auto-applies `web` to
        // routes/web.php), it does NOT wrap module routes in any
        // middleware group. Without this, every route below ran with no
        // session/CSRF handling at all: `Authenticate` saw a sessionless
        // guest on every request and silently redirected to the bare
        // login layout, which has no sidebar — this is the "no other
        // menus are showing" bug.
        Route::middleware('web')->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/schools.php');
        });
    }

    /**
     * Book A CORE-05 §10. "Every module registers its own settings from
     * its service provider's boot()" (`SettingDefinitionRegistry`'s own
     * docblock) — CORE-05 is the first module to actually do this; the
     * in-code list is mirrored into `setting_definitions` by the
     * `sync_core05_setting_definitions` migration, once boot() has run
     * for every provider (Book A CORE-04 §2).
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['auth.password_min_length', 'int', '10', 'How many characters a password must be at minimum.'],
            ['auth.password_requires_mixed_case', 'bool', '1', 'Require both upper- and lower-case letters.'],
            ['auth.password_requires_number', 'bool', '1', 'Require at least one digit.'],
            ['auth.password_requires_symbol', 'bool', '0', 'Require at least one symbol.'],
            ['auth.password_expiry_days', 'int', '0', 'Force a password change after this many days. 0 disables expiry.'],
            ['auth.max_failed_attempts', 'int', '5', 'Failed logins before the account locks.'],
            ['auth.lockout_minutes', 'int', '15', 'How long an account stays locked after too many failed logins.'],
            ['auth.session_idle_timeout_minutes', 'int', '30', 'Idle time before a web session expires.'],
            ['auth.access_token_ttl_minutes', 'int', '60', 'API access token lifetime.'],
            ['auth.refresh_token_ttl_days', 'int', '30', 'API refresh token lifetime.'],
            ['auth.max_devices_per_user', 'int', '5', 'Active device tokens allowed per user before the least recently used is revoked.'],
            ['auth.require_2fa_roles', 'json', '["super_admin","head","bursar","cashier"]', 'Role names that must enrol in 2FA before reaching any other page.'],
            ['auth.otp_length', 'int', '6', 'Digits in a phone OTP code.'],
            ['auth.otp_ttl_seconds', 'int', '300', 'How long an OTP code remains valid.'],
            ['auth.otp_resend_cooldown_seconds', 'int', '60', 'Minimum wait between OTP requests for the same phone number.'],
            ['auth.impersonation_max_minutes', 'int', '60', 'How long an impersonation session may run before it auto-expires.'],
            ['auth.student_portal_min_grade_ordinal', 'int', '4', 'Lowest grade-level ordinal permitted to have a student portal account.'],
            ['audit.bulk_export_threshold', 'int', '500', 'Row count above which an export raises a security event (BR-CORE-08-010).'],
            ['notifications.quiet_hours_start', 'string', '', 'Start of the daily quiet-hours window (HH:MM), blank disables it.'],
            ['notifications.quiet_hours_end', 'string', '06:00', 'End of the daily quiet-hours window (HH:MM).'],
            ['files.soft_delete_retention_days', 'int', '30', 'Days a soft-deleted file is recoverable before physical removal (BR-CORE-10-011).'],
            ['backups.retention_daily', 'int', '7', 'Grandfather-father-son retention: daily backups to keep (BR-CORE-13-005).'],
            ['backups.retention_weekly', 'int', '4', 'Grandfather-father-son retention: weekly backups to keep (BR-CORE-13-005).'],
            ['backups.retention_monthly', 'int', '12', 'Grandfather-father-son retention: monthly backups to keep (BR-CORE-13-005).'],
            ['backups.retention_yearly', 'int', '7', 'Grandfather-father-son retention: yearly backups to keep (BR-CORE-13-005).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'CORE',
                'group_key' => 'auth',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => $dataType === 'bool' ? 'toggle' : ($dataType === 'json' ? 'tags' : 'text'),
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A CORE-08 §4's integrity check suite. Three checks are real
     * (this module owns their data: the financial audit chain, the
     * CORE-03 snapshot chain, and CORE-06's numbering gap scan); the
     * rest depend on modules that don't exist yet.
     */
    private function registerIntegrityChecks(): void
    {
        IntegrityCheckRegistry::register(new FinancialAuditChainCheck);
        IntegrityCheckRegistry::register(new SnapshotChainCheck);
        IntegrityCheckRegistry::register(new NumberingGapCheck);

        $pending = [
            ['trial_balance', 'FIN-01'],
            ['cross_school_fk', 'FIN-01'],
            ['cached_balance', 'FIN-01'],
            ['orphan_records', 'FIN-01'],
        ];

        foreach ($pending as [$checkType, $module]) {
            IntegrityCheckRegistry::register(new PendingIntegrityCheck($checkType, $module));
        }
    }

    /**
     * Book A CORE-10 §2's seeded category list. Per-category MIME/size/
     * sensitivity choices below are this module's own reasonable
     * defaults — the spec names the 22 category keys but not their
     * individual limits, which each owning module may tune once it
     * exists (a category is `updateOrCreate`d by key, so a later
     * `register()` call with different settings simply supersedes
     * this one, same as any other registry).
     */
    private function registerFileCategories(): void
    {
        $image = ['image/jpeg', 'image/png', 'image/webp'];
        $document = ['application/pdf'];
        $documentOrImage = [...$document, ...$image];

        $categories = [
            new FileCategoryDefinition('learner_photo', 'Learner Photo', 'PPL-01', $image, 5 * 1024 * 1024, generatesVariants: true),
            new FileCategoryDefinition('birth_certificate', 'Birth Certificate', 'PPL-01', $documentOrImage, 10 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('national_registration', 'National Registration', 'PPL-01', $documentOrImage, 10 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('learner_permit', 'Learner Permit', 'PPL-01', $documentOrImage, 10 * 1024 * 1024, isSensitive: true, requiresExpiry: true),
            new FileCategoryDefinition('medical_report', 'Medical Report', 'WEL-01', $documentOrImage, 10 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('immunisation_record', 'Immunisation Record', 'WEL-01', $documentOrImage, 10 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('transfer_letter', 'Transfer Letter', 'PPL-01', $document, 10 * 1024 * 1024),
            new FileCategoryDefinition('staff_photo', 'Staff Photo', 'PPL-02', $image, 5 * 1024 * 1024, generatesVariants: true),
            new FileCategoryDefinition('staff_contract', 'Staff Contract', 'PPL-02', $document, 10 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('qualification', 'Qualification', 'PPL-02', $documentOrImage, 10 * 1024 * 1024),
            new FileCategoryDefinition('police_clearance', 'Police Clearance', 'PPL-02', $documentOrImage, 10 * 1024 * 1024, isSensitive: true, requiresExpiry: true),
            new FileCategoryDefinition('supplier_document', 'Supplier Document', 'FIN-08', $document, 10 * 1024 * 1024),
            new FileCategoryDefinition('tax_clearance', 'Tax Clearance', 'FIN-08', $document, 10 * 1024 * 1024, requiresExpiry: true),
            new FileCategoryDefinition('asset_photo', 'Asset Photo', 'H1-01', $image, 5 * 1024 * 1024, generatesVariants: true),
            new FileCategoryDefinition('incident_evidence', 'Incident Evidence', 'WEL-01', $documentOrImage, 20 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('assignment_submission', 'Assignment Submission', 'ACA-01', $documentOrImage, 20 * 1024 * 1024),
            new FileCategoryDefinition('sbp_evidence', 'SBP Evidence', 'ACA-01', $documentOrImage, 20 * 1024 * 1024),
            new FileCategoryDefinition('lms_content', 'LMS Content', 'ACA-01', [...$documentOrImage, 'video/mp4'], 100 * 1024 * 1024),
            new FileCategoryDefinition('school_logo', 'School Logo', 'CORE-02', $image, 2 * 1024 * 1024, generatesVariants: true),
            new FileCategoryDefinition('signature', 'Signature', 'CORE-02', ['image/png'], 1024 * 1024),
            new FileCategoryDefinition('receipt_attachment', 'Receipt Attachment', 'FIN-01', $documentOrImage, 5 * 1024 * 1024),
            new FileCategoryDefinition('safeguarding_evidence', 'Safeguarding Evidence', 'WEL-01', $documentOrImage, 20 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('import_correction_file', 'Import Correction File', 'CORE-11', ['text/plain', 'text/csv'], 20 * 1024 * 1024),
            new FileCategoryDefinition('import_source_file', 'Import Source File', 'CORE-11', ['text/plain', 'text/csv'], 20 * 1024 * 1024),
            new FileCategoryDefinition('payroll_bank_file', 'Payroll Bank File', 'PPL-05', ['text/plain', 'text/csv'], 5 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('close_pack', 'Period Close Pack', 'FIN-12', ['application/json', 'text/plain'], 5 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('accounting_export', 'Accounting Export', 'FIN-12', ['text/csv', 'text/plain'], 20 * 1024 * 1024),
            new FileCategoryDefinition('zimsec_export', 'ZIMSEC Candidate Export', 'CMP-01', ['application/json', 'text/plain'], 20 * 1024 * 1024, isSensitive: true),
            new FileCategoryDefinition('zimsec_statement_of_entry', 'ZIMSEC Statement of Entry', 'CMP-01', ['application/json', 'text/plain'], 5 * 1024 * 1024),
            new FileCategoryDefinition('statutory_return_export', 'Statutory School Return Export', 'CMP-02', ['application/json', 'text/plain'], 10 * 1024 * 1024),
            new FileCategoryDefinition('inspection_pack', 'Inspection Pack', 'CMP-02', ['application/json', 'text/plain'], 20 * 1024 * 1024, isSensitive: true),
        ];

        foreach ($categories as $category) {
            FileCategoryRegistry::register($category);
        }
    }

    /**
     * Book A CORE-12 §3's standard health check suite. Eight checks run
     * for real against this application's own infrastructure; the rest
     * (fiscalisation queue depth, gateway reachability, trial balance,
     * SMS/WhatsApp credit) depend on modules that don't exist yet.
     */
    private function registerHealthChecks(): void
    {
        HealthCheckRegistry::register(new QueueDepthHealthCheck);
        HealthCheckRegistry::register(new FailedJobsHealthCheck);
        HealthCheckRegistry::register(new OldestQueuedJobHealthCheck);
        HealthCheckRegistry::register(new DatabaseConnectionHealthCheck);
        HealthCheckRegistry::register(new RedisConnectionHealthCheck);
        HealthCheckRegistry::register(new StorageFreeSpaceHealthCheck);
        HealthCheckRegistry::register(new SchedulerLastRunHealthCheck);
        HealthCheckRegistry::register(new NotificationFailureRateHealthCheck);
        HealthCheckRegistry::register(new BackupFreshnessHealthCheck);
        HealthCheckRegistry::register(new RestoreVerificationHealthCheck);

        $pending = [
            ['fiscalisation_queue_depth', 'FIN-05'],
            ['gateway_reachability', 'FIN-07'],
            ['trial_balance_status', 'FIN-01'],
            ['sms_whatsapp_credit', 'COM-01'],
        ];

        foreach ($pending as [$checkKey, $module]) {
            HealthCheckRegistry::register(new PendingHealthCheck($checkKey, $module));
        }
    }

    /**
     * Book A CORE-12 §2's task registry. `core.scheduler_heartbeat` is
     * this module's own minute-by-minute proof of life that
     * `SchedulerLastRunHealthCheck` watches (AC-CORE-12-003) — every
     * other task ships with the module whose work it schedules.
     */
    private function registerScheduledTasks(): void
    {
        ScheduledTaskRegistry::register(new ScheduledTaskDefinitionData(
            key: SchedulerLastRunHealthCheck::HEARTBEAT_TASK_KEY,
            moduleCode: 'CORE-12',
            name: 'Scheduler Heartbeat',
            command: 'core:scheduler-heartbeat',
            scheduleExpression: '* * * * *',
            description: 'Proves the scheduler is running at all — watched by the scheduler_last_run health check.',
            alertOnFailure: false,
        ));
    }

    private function registerSeedPacks(): void
    {
        SeedPackRegistry::register(new CalendarSeedPack);
        SeedPackRegistry::register(new RoleSeedPack);

        $pending = [
            ['coa', 'Chart of Accounts', 'Chart of accounts for a Zimbabwean school, plus mandatory system accounts.', 'FIN-01'],
            ['currencies', 'Currencies', 'USD, ZWG with precision and formatting.', 'FIN-06'],
            ['grading', 'Grading Scales', 'ZIMSEC, Cambridge, and primary grading scales.', 'ACA-05'],
            ['learning_areas', 'Learning Areas', 'Heritage-Based Curriculum learning areas with ZIMSEC subject codes.', 'ACA-01'],
            ['levels', 'Levels', 'ECD A, ECD B, Grade 1-7, Form 1-6 with default section mapping.', 'CORE-02'],
            ['templates', 'Document Templates', 'Report card, invoice, receipt, statement, exeat pass defaults.', 'CORE-06'],
            ['notifications', 'Notification Templates', 'Default message templates for all COM-02 triggers.', 'CORE-09'],
            ['settings', 'Settings Defaults', 'Sensible Zimbabwean defaults for every registered setting.', 'CORE-04'],
        ];

        foreach ($pending as [$code, $label, $description, $module]) {
            SeedPackRegistry::register(new PendingSeedPack($code, $label, $description, $module));
        }
    }

    /**
     * Book A CORE-03 §5's registered-handler-order table. Only the four
     * CORE-03-owned handlers (60/900/910/920) are real; the other
     * thirteen belong to modules that don't exist yet, so they're
     * `PendingRolloverHandler` stubs — same shape as `registerSeedPacks()`.
     */
    private function registerRolloverHandlers(): void
    {
        RolloverHandlerRegistry::register(app(TakePreCloseSnapshotHandler::class));
        RolloverHandlerRegistry::register(app(TakePostCloseSnapshotHandler::class));
        RolloverHandlerRegistry::register(app(VerifyInvariantHandler::class));
        RolloverHandlerRegistry::register(new GenerateRolloverReportHandler);

        $pending = [
            ['FIN-01', 'AssertTrialBalanceHandler', 10, true],
            ['FIN-04', 'AssertTillSessionsClosedHandler', 20, true],
            ['FIN-04', 'AssertSuspenseClearedHandler', 30, true],
            ['FIN-05', 'AssertReconciliationCompleteHandler', 40, true],
            ['FIN-06', 'RevalueForeignBalancesHandler', 50, true],
            ['FIN-03', 'CarryForwardLearnerBalancesHandler', 70, true],
            ['FIN-03', 'CarryForwardCreditsHandler', 80, true],
            ['FIN-08', 'CarryForwardSupplierBalancesHandler', 90, true],
            ['PPL-01', 'PromoteLearnersHandler', 100, false],
            ['ACA-02', 'CloneClassAllocationsHandler', 110, false],
            ['FIN-02', 'CloneFeeStructuresHandler', 120, false],
            ['ACA-03', 'CloneTimetableTemplateHandler', 130, false],
            ['BRD-01', 'CloneHostelAllocationsHandler', 140, false],
        ];

        foreach ($pending as [$module, $name, $order, $blocking]) {
            RolloverHandlerRegistry::register(new PendingRolloverHandler($module, $name, $order, $blocking));
        }
    }

    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(
            AcademicYear::class,
            fn (School $school): AcademicYear => AcademicYear::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(Term::class, function (School $school): Term {
            $year = AcademicYear::factory()->for($school)->create();

            return Term::factory()->for($school)->for($year, 'academicYear')->create();
        });

        TenantModelRegistry::register(
            SchoolModule::class,
            fn (School $school): SchoolModule => SchoolModule::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(
            SchoolSection::class,
            fn (School $school): SchoolSection => SchoolSection::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(House::class, fn (School $school): House => House::factory()->for($school)->create());

        TenantModelRegistry::register(GradeLevel::class, function (School $school): GradeLevel {
            $section = SchoolSection::factory()->for($school)->create();

            return GradeLevel::factory()->for($school)->for($section, 'section')->create();
        });

        TenantModelRegistry::register(SchoolClass::class, function (School $school): SchoolClass {
            $section = SchoolSection::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
            $year = AcademicYear::factory()->for($school)->create();

            return SchoolClass::factory()
                ->for($school)
                ->for($year, 'academicYear')
                ->for($gradeLevel, 'gradeLevel')
                ->create();
        });

        TenantModelRegistry::register(TermWeek::class, function (School $school): TermWeek {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return TermWeek::factory()->for($school)->for($term)->create();
        });

        TenantModelRegistry::register(
            CalendarHoliday::class,
            function (School $school): CalendarHoliday {
                $year = AcademicYear::factory()->for($school)->create();

                return CalendarHoliday::factory()->for($school)->for($year, 'academicYear')->create();
            },
        );

        TenantModelRegistry::register(PeriodStateTransition::class, function (School $school): PeriodStateTransition {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return PeriodStateTransition::factory()->for($school)->for($term)->create();
        });

        TenantModelRegistry::register(PeriodSnapshot::class, function (School $school): PeriodSnapshot {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return PeriodSnapshot::factory()->for($school)->for($year, 'academicYear')->for($term)->create();
        });

        TenantModelRegistry::register(PeriodRollover::class, function (School $school): PeriodRollover {
            $year = AcademicYear::factory()->for($school)->create();
            $fromTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 1, 'name' => 'Term 1']);
            $toTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 2, 'name' => 'Term 2']);

            return PeriodRollover::factory()
                ->for($school)
                ->for($fromTerm, 'fromTerm')
                ->for($toTerm, 'toTerm')
                ->create();
        });

        TenantModelRegistry::register(
            PeriodReopenRequest::class,
            fn (School $school): PeriodReopenRequest => PeriodReopenRequest::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(
            CustomFieldDefinition::class,
            fn (School $school): CustomFieldDefinition => CustomFieldDefinition::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(CustomFieldValue::class, function (School $school): CustomFieldValue {
            $definition = CustomFieldDefinition::factory()->for($school)->create();

            return CustomFieldValue::factory()->for($school)->for($definition, 'definition')->create();
        });

        TenantModelRegistry::register(
            UserAccountLink::class,
            fn (School $school): UserAccountLink => UserAccountLink::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(
            NumberingSeries::class,
            fn (School $school): NumberingSeries => NumberingSeries::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(AllocatedNumber::class, function (School $school): AllocatedNumber {
            $series = NumberingSeries::factory()->for($school)->create();

            return AllocatedNumber::factory()->for($school)->create(['series_id' => $series->id]);
        });

        TenantModelRegistry::register(
            DocumentTemplate::class,
            fn (School $school): DocumentTemplate => DocumentTemplate::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(
            Document::class,
            fn (School $school): Document => Document::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(DocumentBatch::class, function (School $school): DocumentBatch {
            $template = DocumentTemplate::factory()->for($school)->create();

            return DocumentBatch::factory()->for($school)->create(['template_id' => $template->id]);
        });

        TenantModelRegistry::register(
            ApprovalChain::class,
            fn (School $school): ApprovalChain => ApprovalChain::factory()->for($school)->create(),
        );

        TenantModelRegistry::register(ApprovalRequest::class, function (School $school): ApprovalRequest {
            $year = AcademicYear::factory()->for($school)->create();
            $chain = ApprovalChain::factory()->for($school)->create();

            return ApprovalRequest::factory()->for($school)->create([
                'academic_year_id' => $year->id,
                'chain_id' => $chain->id,
            ]);
        });

        TenantModelRegistry::register(
            ApprovalDelegation::class,
            fn (School $school): ApprovalDelegation => ApprovalDelegation::factory()->for($school)->create(),
        );
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('serp.not-installed', EnsureNotInstalled::class);
        $router->aliasMiddleware('serp.resolve-tenant', ResolveTenant::class);
        $router->aliasMiddleware('serp.subscription-active', EnsureSubscriptionActive::class);
        $router->aliasMiddleware('serp.school-context', SetSchoolContext::class);
        $router->aliasMiddleware('serp.session-context', SetSessionContext::class);
        $router->aliasMiddleware('serp.module-enabled', EnsureModuleEnabled::class);
        $router->aliasMiddleware('serp.token-ability', EnforceTokenAbility::class);
        $router->aliasMiddleware('serp.record-activity', RecordActivity::class);
        $router->aliasMiddleware('serp.vendor-guard', EnsureVendorGuard::class);

        // `serp.module-enabled` takes the module code as a route-declared
        // parameter (Book A Part 1.10, step 6) and so is never a bare
        // member of these groups — a module's own routes append it
        // explicitly, e.g. ->middleware(['serp.web', 'serp.module-enabled:FIN']).
        $router->middlewareGroup('serp.web', [
            'serp.resolve-tenant',
            'serp.subscription-active',
            'auth',
            'serp.school-context',
            'serp.session-context',
            'serp.record-activity',
        ]);

        $router->middlewareGroup('serp.api', [
            'serp.resolve-tenant',
            'serp.subscription-active',
            'auth:sanctum',
            'serp.school-context',
            'serp.session-context',
            'serp.token-ability',
            'serp.record-activity',
        ]);

        // Book J SAA-02 §3 ⭐/§0.2 — deliberately NOT composed with
        // `serp.resolve-tenant`/`serp.subscription-active`/
        // `serp.school-context`: the vendor console is cross-tenant,
        // never scoped to any one tenant's context, and a THIRD guard
        // entirely separate from `serp.web`/`serp.api`.
        $router->middlewareGroup('serp.vendor', [
            'auth',
            'serp.vendor-guard',
            'serp.record-activity',
        ]);
    }
}
