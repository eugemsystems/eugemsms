<?php

use Modules\Core\Domain\Actions\Install\WriteEnvironmentFileAction;
use Modules\Core\Domain\DataObjects\Install\DatabaseCredentialsData;
use Modules\Core\Domain\DataObjects\Install\EnvironmentData;
use Modules\Core\Domain\Support\Install\DeploymentMode;
use Modules\Core\Domain\Support\Install\EnvFileWriter;

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/serp_write_env_test_*') ?: [] as $file) {
        @unlink($file);
    }
});

it('writes app and database settings into the env file', function (): void {
    $path = sys_get_temp_dir().'/serp_write_env_test_'.uniqid().'.env';
    $action = new WriteEnvironmentFileAction(new EnvFileWriter($path));

    $action->execute(new EnvironmentData(
        appName: 'sERP',
        appUrl: 'https://sunrise.serp.test',
        timezone: 'Africa/Harare',
        locale: 'en_ZW',
        deploymentMode: DeploymentMode::Saas,
        database: new DatabaseCredentialsData(
            driver: 'mysql',
            host: '127.0.0.1',
            port: 3306,
            database: 'serp',
            username: 'serp',
            password: 'secret',
        ),
    ));

    $contents = file_get_contents($path);

    expect($contents)->toContain('APP_NAME=sERP')
        ->and($contents)->toContain('APP_URL=https://sunrise.serp.test')
        ->and($contents)->toContain('DB_DATABASE=serp')
        ->and($contents)->toContain('DB_PASSWORD=secret')
        ->and($contents)->toContain('SERP_DEPLOYMENT_MODE=saas');
});

it('defaults to writing the real application .env when no writer is injected', function (): void {
    $action = new WriteEnvironmentFileAction;

    expect($action)->toBeInstanceOf(WriteEnvironmentFileAction::class);
});
