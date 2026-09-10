<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Modules\Core\Domain\Actions\Install\WriteEnvironmentFileAction;
use Modules\Core\Domain\Support\Install\EnvFileWriter;
use Modules\Core\Models\School;
use Modules\Core\Models\SystemInstallation;
use Modules\Core\Models\Tenant;

function installConfigPath(): string
{
    return sys_get_temp_dir().'/serp_install_config_'.uniqid().'.json';
}

beforeEach(function (): void {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

    // The action resolves its EnvFileWriter through the container, so
    // this test must redirect it to a throwaway file — never the real
    // project .env.
    $this->envPath = sys_get_temp_dir().'/serp_install_env_'.uniqid().'.env';
    $this->app->instance(
        WriteEnvironmentFileAction::class,
        new WriteEnvironmentFileAction(new EnvFileWriter($this->envPath)),
    );
});

afterEach(function (): void {
    File::delete(storage_path('installed.lock'));
    @unlink($this->envPath);
    foreach (glob(sys_get_temp_dir().'/serp_install_config_*') ?: [] as $file) {
        @unlink($file);
    }
});

it('runs the full headless installer end to end', function (): void {
    $configPath = installConfigPath();

    file_put_contents($configPath, json_encode([
        'environment' => [
            'app_name' => 'sERP',
            'app_url' => 'https://sunrise.serp.test',
            'timezone' => 'Africa/Harare',
            'locale' => 'en_ZW',
            'deployment_mode' => 'saas',
        ],
        'licence_key' => null,
        'administrator' => [
            'name' => 'Head Admin',
            'email' => 'head@sunrise.test',
            'password' => 'a-genuinely-unusual-passphrase-93!',
        ],
        'tenant' => ['name' => 'Sunrise Group', 'slug' => 'sunrise-install-test'],
        'school' => [
            'name' => 'Sunrise Girls High',
            'code' => 'SGH',
            'base_currency' => 'USD',
            'timezone' => 'Africa/Harare',
            'locale' => 'en_ZW',
        ],
        'seed_packs' => ['calendar'],
    ]));

    $this->artisan('serp:install', ['--headless' => true, '--config' => $configPath])
        ->assertSuccessful();

    expect(file_exists(storage_path('installed.lock')))->toBeTrue()
        ->and(SystemInstallation::count())->toBe(1)
        ->and(Tenant::where('slug', 'sunrise-install-test')->exists())->toBeTrue()
        ->and(School::where('code', 'SGH')->exists())->toBeTrue()
        ->and(file_get_contents($this->envPath))->toContain('APP_NAME=sERP');
});

it('refuses to run once already installed', function (): void {
    File::put(storage_path('installed.lock'), '{}');

    $configPath = installConfigPath();
    file_put_contents($configPath, json_encode(['administrator' => []]));

    $this->artisan('serp:install', ['--headless' => true, '--config' => $configPath])
        ->assertFailed();
});

it('fails cleanly when the config file is missing', function (): void {
    $this->artisan('serp:install', ['--headless' => true, '--config' => '/nonexistent/config.json'])
        ->assertFailed();
});
