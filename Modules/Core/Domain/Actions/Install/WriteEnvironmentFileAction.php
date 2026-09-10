<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\EnvironmentData;
use Modules\Core\Domain\Support\Install\EnvFileWriter;

/**
 * ACT-WriteEnvironmentFile (Book A CORE-01 §3).
 */
final class WriteEnvironmentFileAction extends Action
{
    protected bool $transactional = false;

    private readonly EnvFileWriter $writer;

    public function __construct(?EnvFileWriter $writer = null)
    {
        $this->writer = $writer ?? new EnvFileWriter(base_path('.env'));
    }

    public function execute(EnvironmentData $data): void
    {
        $this->writer->write([
            'APP_NAME' => $data->appName,
            'APP_URL' => $data->appUrl,
            'APP_TIMEZONE' => $data->timezone,
            'APP_LOCALE' => $data->locale,
            'SERP_DEPLOYMENT_MODE' => $data->deploymentMode->value,
            'DB_CONNECTION' => $data->database->driver,
            'DB_HOST' => $data->database->host,
            'DB_PORT' => (string) $data->database->port,
            'DB_DATABASE' => $data->database->database,
            'DB_USERNAME' => $data->database->username,
            'DB_PASSWORD' => $data->database->password,
        ]);
    }
}
