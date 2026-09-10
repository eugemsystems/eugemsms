<?php

use Modules\Core\Domain\Support\Install\EnvFileWriter;

function envTempPath(): string
{
    return sys_get_temp_dir().'/serp_env_writer_test_'.uniqid().'.env';
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/serp_env_writer_test_*') ?: [] as $file) {
        @unlink($file);
    }
});

it('creates a new env file with the given values', function (): void {
    $path = envTempPath();
    (new EnvFileWriter($path))->write(['APP_NAME' => 'sERP', 'APP_URL' => 'https://example.test']);

    $contents = file_get_contents($path);

    expect($contents)->toContain('APP_NAME=sERP')
        ->and($contents)->toContain('APP_URL=https://example.test');
});

it('updates an existing key in place rather than duplicating it', function (): void {
    $path = envTempPath();
    file_put_contents($path, "APP_NAME=Old\nAPP_ENV=local\n");

    (new EnvFileWriter($path))->write(['APP_NAME' => 'New']);

    $contents = file_get_contents($path);

    expect(substr_count($contents, 'APP_NAME='))->toBe(1)
        ->and($contents)->toContain('APP_NAME=New')
        ->and($contents)->toContain('APP_ENV=local');
});

it('quotes values containing whitespace', function (): void {
    $path = envTempPath();
    (new EnvFileWriter($path))->write(['APP_NAME' => 'Sunrise School']);

    expect(file_get_contents($path))->toContain('APP_NAME="Sunrise School"');
});

it('backs up the existing file before writing', function (): void {
    $path = envTempPath();
    file_put_contents($path, "APP_NAME=Old\n");

    (new EnvFileWriter($path))->write(['APP_NAME' => 'New']);

    $backups = glob($path.'.backup-*') ?: [];

    expect($backups)->toHaveCount(1);
    expect(file_get_contents($backups[0]))->toBe("APP_NAME=Old\n");
});

it('writes nothing to back up for a brand new file', function (): void {
    $path = envTempPath();
    (new EnvFileWriter($path))->write(['APP_NAME' => 'sERP']);

    expect(glob($path.'.backup-*') ?: [])->toBe([]);
});
