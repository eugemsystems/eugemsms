<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Core\Domain\Actions\Install\ActivateLicenceAction;
use Modules\Core\Domain\Actions\Install\CreateSuperAdminAction;
use Modules\Core\Domain\Actions\Install\FinaliseInstallationAction;
use Modules\Core\Domain\Actions\Install\ProvisionFirstSchoolAction;
use Modules\Core\Domain\Actions\Install\ProvisionFirstTenantAction;
use Modules\Core\Domain\Actions\Install\RunInstallMigrationsAction;
use Modules\Core\Domain\Actions\Install\SeedZimbabweBaselineAction;
use Modules\Core\Domain\Actions\Install\VerifyRequirementsAction;
use Modules\Core\Domain\Actions\Install\WriteEnvironmentFileAction;
use Modules\Core\Domain\DataObjects\Install\DatabaseCredentialsData;
use Modules\Core\Domain\DataObjects\Install\EnvironmentData;
use Modules\Core\Domain\DataObjects\Install\FinaliseInstallationData;
use Modules\Core\Domain\DataObjects\Install\LicenceActivation;
use Modules\Core\Domain\DataObjects\Install\LicenceKeyData;
use Modules\Core\Domain\DataObjects\Install\SchoolProvisionData;
use Modules\Core\Domain\DataObjects\Install\SeedPackData;
use Modules\Core\Domain\DataObjects\Install\SuperAdminData;
use Modules\Core\Domain\DataObjects\Install\TenantProvisionData;
use Modules\Core\Domain\Support\Install\DeploymentMode;
use Modules\Core\Domain\Support\Install\InstallProgress;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Models\School;
use RuntimeException;
use Throwable;

/**
 * `php artisan serp:install --headless --config=install.json` (Book A
 * CORE-01 §6) — the CLI equivalent of the browser wizard, for CI/on-prem.
 * Resumable: re-running after a failure restarts at the first
 * non-completed step (BR-CORE-01-003).
 */
final class InstallCommand extends Command
{
    protected $signature = 'serp:install {--headless} {--config= : Path to a JSON config file}';

    protected $description = 'Run the sERP installer headlessly from a JSON config file.';

    public function handle(): int
    {
        if (InstallProgress::isInstalled()) {
            $this->error('This platform is already installed.');

            return self::FAILURE;
        }

        $configPath = $this->option('config');

        if (! is_string($configPath) || ! file_exists($configPath)) {
            $this->error('--config={path to a readable JSON file} is required.');

            return self::FAILURE;
        }

        $config = json_decode((string) file_get_contents($configPath), true);

        if (! is_array($config)) {
            $this->error('The config file is not valid JSON.');

            return self::FAILURE;
        }

        $progress = new InstallProgress;

        try {
            $this->runRequirements($progress);
            $this->runEnvironment($progress, $config);
            $this->runMigrations($progress);
            $licence = $this->runLicence($progress, $config);
            $admin = $this->runAdministrator($progress, $config);
            $school = $this->runOrganisation($progress, $config, $admin->id);
            $this->runSeed($progress, $config, $school->id);
            $this->finalise($progress, $config, $licence);
        } catch (Throwable $exception) {
            $this->error("Installation failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Installation complete.');

        return self::SUCCESS;
    }

    private function runRequirements(InstallProgress $progress): void
    {
        if ($progress->statusOf(InstallStepKey::Requirements)->value === 'completed') {
            return;
        }

        $progress->markRunning(InstallStepKey::Requirements);
        $report = app(VerifyRequirementsAction::class)->execute();

        if (! $report->passesMandatory()) {
            $names = implode(', ', array_map(fn ($f) => $f->name, $report->failures()));
            $progress->markFailed(InstallStepKey::Requirements, "Mandatory requirements not met: {$names}");

            throw new RuntimeException("Mandatory requirements not met: {$names}");
        }

        $progress->markCompleted(InstallStepKey::Requirements);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runEnvironment(InstallProgress $progress, array $config): void
    {
        if ($progress->statusOf(InstallStepKey::Environment)->value === 'completed') {
            return;
        }

        $progress->markRunning(InstallStepKey::Environment);

        $env = (array) ($config['environment'] ?? []);
        $db = (array) ($env['database'] ?? []);

        app(WriteEnvironmentFileAction::class)->execute(new EnvironmentData(
            appName: (string) ($env['app_name'] ?? config('app.name')),
            appUrl: (string) ($env['app_url'] ?? config('app.url')),
            timezone: (string) ($env['timezone'] ?? 'Africa/Harare'),
            locale: (string) ($env['locale'] ?? 'en_ZW'),
            deploymentMode: DeploymentMode::from((string) ($env['deployment_mode'] ?? 'saas')),
            database: new DatabaseCredentialsData(
                driver: (string) ($db['driver'] ?? config('database.default')),
                host: (string) ($db['host'] ?? ''),
                port: (int) ($db['port'] ?? 3306),
                database: (string) ($db['database'] ?? ''),
                username: (string) ($db['username'] ?? ''),
                password: (string) ($db['password'] ?? ''),
            ),
        ));

        $progress->markCompleted(InstallStepKey::Environment);
    }

    private function runMigrations(InstallProgress $progress): void
    {
        if ($progress->statusOf(InstallStepKey::Migrations)->value === 'completed') {
            return;
        }

        $progress->markRunning(InstallStepKey::Migrations);
        $result = app(RunInstallMigrationsAction::class)->execute();

        if (! $result->successful) {
            $progress->markFailed(InstallStepKey::Migrations, $result->errorMessage ?? 'Migration failed.');

            throw new RuntimeException($result->errorMessage ?? 'Migration failed.');
        }

        $progress->markCompleted(InstallStepKey::Migrations);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runLicence(InstallProgress $progress, array $config): LicenceActivation
    {
        $activation = app(ActivateLicenceAction::class)->execute(new LicenceKeyData(
            key: isset($config['licence_key']) ? (string) $config['licence_key'] : null,
        ));

        $progress->markCompleted(InstallStepKey::Licence, ['status' => $activation->status->value]);

        return $activation;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runAdministrator(InstallProgress $progress, array $config): User
    {
        $admin = (array) ($config['administrator'] ?? []);

        $user = app(CreateSuperAdminAction::class)->execute(new SuperAdminData(
            name: (string) ($admin['name'] ?? ''),
            email: (string) ($admin['email'] ?? ''),
            password: (string) ($admin['password'] ?? ''),
        ));

        $progress->markCompleted(InstallStepKey::Administrator, ['user_id' => $user->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runOrganisation(InstallProgress $progress, array $config, int $adminUserId): School
    {
        $tenantConfig = (array) ($config['tenant'] ?? []);
        $schoolConfig = (array) ($config['school'] ?? []);

        $tenant = app(ProvisionFirstTenantAction::class)->execute(new TenantProvisionData(
            name: (string) ($tenantConfig['name'] ?? ''),
            slug: (string) ($tenantConfig['slug'] ?? ''),
        ));

        $school = app(ProvisionFirstSchoolAction::class)->execute(new SchoolProvisionData(
            tenantId: $tenant->id,
            name: (string) ($schoolConfig['name'] ?? ''),
            code: (string) ($schoolConfig['code'] ?? ''),
            baseCurrency: (string) ($schoolConfig['base_currency'] ?? 'USD'),
            timezone: (string) ($schoolConfig['timezone'] ?? 'Africa/Harare'),
            locale: (string) ($schoolConfig['locale'] ?? 'en_ZW'),
            adminUserId: $adminUserId,
        ));

        $progress->markCompleted(InstallStepKey::Organisation, ['tenant_id' => $tenant->id, 'school_id' => $school->id]);

        return $school;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runSeed(InstallProgress $progress, array $config, int $schoolId): void
    {
        $packs = array_map('strval', (array) ($config['seed_packs'] ?? ['calendar']));

        app(SeedZimbabweBaselineAction::class)->execute(new SeedPackData(schoolId: $schoolId, packs: $packs));

        $progress->markCompleted(InstallStepKey::Seed, ['packs' => $packs]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function finalise(InstallProgress $progress, array $config, LicenceActivation $licence): void
    {
        $env = (array) ($config['environment'] ?? []);

        app(FinaliseInstallationAction::class)->execute(new FinaliseInstallationData(
            deploymentMode: DeploymentMode::from((string) ($env['deployment_mode'] ?? 'saas')),
            licenceKey: isset($config['licence_key']) ? (string) $config['licence_key'] : null,
            licenceActivatedAt: $licence->activatedAt,
            licenceExpiresAt: $licence->expiresAt,
        ));

        $progress->markCompleted(InstallStepKey::Finalise);
    }
}
