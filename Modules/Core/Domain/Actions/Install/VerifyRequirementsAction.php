<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\RequirementCheck;
use Modules\Core\Domain\DataObjects\Install\RequirementCheckStatus;
use Modules\Core\Domain\DataObjects\Install\RequirementsReport;

/**
 * ACT-VerifyRequirements (Book A CORE-01 §3). PHP version, extensions,
 * writable paths, Composer, Node, memory limit, max_execution_time.
 */
final class VerifyRequirementsAction extends Action
{
    protected bool $transactional = false;

    private const MANDATORY_EXTENSIONS = ['pdo', 'mbstring', 'openssl', 'gd', 'zip', 'bcmath', 'intl'];

    private const MANDATORY_MEMORY_MB = 256;

    public function execute(): RequirementsReport
    {
        $checks = [
            $this->phpVersionCheck(),
            ...$this->extensionChecks(),
            ...$this->writablePathChecks(),
            $this->memoryLimitCheck(),
            $this->maxExecutionTimeCheck(),
            $this->composerCheck(),
            $this->nodeCheck(),
        ];

        return new RequirementsReport($checks);
    }

    private function phpVersionCheck(): RequirementCheck
    {
        $ok = version_compare(PHP_VERSION, '8.4.0', '>=');

        return new RequirementCheck(
            name: 'PHP version',
            status: $ok ? RequirementCheckStatus::Pass : RequirementCheckStatus::Fail,
            message: $ok ? PHP_VERSION.' meets the minimum of 8.4.0.' : PHP_VERSION.' is below the required 8.4.0.',
            mandatory: true,
            remediation: $ok ? null : 'Upgrade the server\'s PHP runtime to 8.4 or later.',
        );
    }

    /**
     * @return array<int, RequirementCheck>
     */
    private function extensionChecks(): array
    {
        return array_map(
            fn (string $extension): RequirementCheck => new RequirementCheck(
                name: "PHP extension: {$extension}",
                status: extension_loaded($extension) ? RequirementCheckStatus::Pass : RequirementCheckStatus::Fail,
                message: extension_loaded($extension) ? 'Loaded.' : 'Not loaded.',
                mandatory: true,
                remediation: extension_loaded($extension) ? null : "Install or enable the php-{$extension} extension.",
            ),
            self::MANDATORY_EXTENSIONS,
        );
    }

    /**
     * @return array<int, RequirementCheck>
     */
    private function writablePathChecks(): array
    {
        $paths = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        return array_map(
            fn (string $label, string $path): RequirementCheck => new RequirementCheck(
                name: "Writable: {$label}",
                status: is_writable($path) ? RequirementCheckStatus::Pass : RequirementCheckStatus::Fail,
                message: is_writable($path) ? 'Writable.' : 'Not writable by the web server process.',
                mandatory: true,
                remediation: is_writable($path) ? null : "Grant the web server write permission on {$path}.",
            ),
            array_keys($paths),
            array_values($paths),
        );
    }

    private function memoryLimitCheck(): RequirementCheck
    {
        $limit = $this->phpIniBytes(ini_get('memory_limit'));
        $ok = $limit === -1 || $limit >= self::MANDATORY_MEMORY_MB * 1024 * 1024;

        return new RequirementCheck(
            name: 'Memory limit',
            status: $ok ? RequirementCheckStatus::Pass : RequirementCheckStatus::Fail,
            message: ini_get('memory_limit').' configured.',
            mandatory: true,
            remediation: $ok ? null : 'Raise memory_limit to at least '.self::MANDATORY_MEMORY_MB.'M in php.ini.',
        );
    }

    private function maxExecutionTimeCheck(): RequirementCheck
    {
        $seconds = (int) ini_get('max_execution_time');
        $ok = $seconds === 0 || $seconds >= 60;

        return new RequirementCheck(
            name: 'Max execution time',
            status: $ok ? RequirementCheckStatus::Pass : RequirementCheckStatus::Warn,
            message: $seconds === 0 ? 'Unlimited.' : "{$seconds} seconds.",
            mandatory: false,
            remediation: $ok ? null : 'Raise max_execution_time to at least 60 seconds for long-running installer steps.',
        );
    }

    private function composerCheck(): RequirementCheck
    {
        $ok = file_exists(base_path('vendor/autoload.php'));

        return new RequirementCheck(
            name: 'Composer dependencies',
            status: $ok ? RequirementCheckStatus::Pass : RequirementCheckStatus::Fail,
            message: $ok ? 'vendor/ is present.' : 'vendor/ is missing — composer install has not run.',
            mandatory: true,
            remediation: $ok ? null : 'Run `composer install --no-dev --optimize-autoloader` on the server.',
        );
    }

    private function nodeCheck(): RequirementCheck
    {
        $ok = file_exists(public_path('build/manifest.json'));

        return new RequirementCheck(
            name: 'Frontend build',
            status: $ok ? RequirementCheckStatus::Pass : RequirementCheckStatus::Warn,
            message: $ok ? 'public/build is present.' : 'public/build is missing — the frontend has not been built.',
            mandatory: false,
            remediation: $ok ? null : 'Run `npm install && npm run build` before going live.',
        );
    }

    private function phpIniBytes(string|false $value): int
    {
        if ($value === false || $value === '') {
            return 0;
        }

        if ($value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
