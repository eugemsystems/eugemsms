<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\TestDatabaseConnectionAction;
use Modules\Core\Domain\Actions\Install\WriteEnvironmentFileAction;
use Modules\Core\Domain\DataObjects\Install\DatabaseCredentialsData;
use Modules\Core\Domain\DataObjects\Install\EnvironmentData;
use Modules\Core\Domain\Support\Install\DeploymentMode;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Database` (Book A CORE-01 §5). Credentials + Test connection
 * before Next is enabled. BR-CORE-01-004: credentials are validated by an
 * actual connection before being written to .env.
 */
#[Title('Database')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Database])]
final class Database extends InstallStepComponent
{
    public string $driver = 'mysql';

    public string $host = '127.0.0.1';

    public int $port = 3306;

    public string $database = '';

    public string $username = '';

    public string $password = '';

    public bool $tested = false;

    public bool $canProceed = false;

    public ?string $testMessage = null;

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Database;
    }

    public function mount(): void
    {
        parent::mount();

        $this->driver = (string) config('database.default');
        $connection = (array) config('database.connections.'.$this->driver, []);
        $this->host = (string) ($connection['host'] ?? $this->host);
        $this->port = (int) ($connection['port'] ?? $this->port);
        $this->database = (string) ($connection['database'] ?? '');
        $this->username = (string) ($connection['username'] ?? '');
    }

    public function testConnection(): void
    {
        $this->validate($this->rules());

        $result = app(TestDatabaseConnectionAction::class)->execute($this->credentials());

        $this->tested = true;
        $this->canProceed = $result->canProceed();
        $this->testMessage = $result->message;
    }

    public function continue(): void
    {
        if (! $this->canProceed) {
            return;
        }

        $this->validate($this->rules());

        $environment = $this->progress()->payloadOf(InstallStepKey::Environment);

        app(WriteEnvironmentFileAction::class)->execute(new EnvironmentData(
            appName: $environment['app_name'] ?? (string) config('app.name'),
            appUrl: $environment['app_url'] ?? (string) config('app.url'),
            timezone: $environment['timezone'] ?? 'Africa/Harare',
            locale: $environment['locale'] ?? 'en_ZW',
            deploymentMode: DeploymentMode::from($environment['deployment_mode'] ?? 'saas'),
            database: $this->credentials(),
        ));

        // Reconfigure the current process's live connection so the
        // remaining installer steps (migrations, admin creation, ...) use
        // the database that was just tested and written to .env.
        config([
            'database.connections.'.$this->driver => [
                'driver' => $this->driver,
                'host' => $this->host,
                'port' => $this->port,
                'database' => $this->database,
                'username' => $this->username,
                'password' => $this->password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'database.default' => $this->driver,
        ]);
        DB::purge($this->driver);

        $this->completeStepAndContinue();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'driver' => ['required', 'string'],
            'host' => ['required', 'string'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
        ];
    }

    private function credentials(): DatabaseCredentialsData
    {
        return new DatabaseCredentialsData(
            driver: $this->driver,
            host: $this->host,
            port: $this->port,
            database: $this->database,
            username: $this->username,
            password: $this->password,
        );
    }

    public function render(): View
    {
        return view('core::install.database');
    }
}
