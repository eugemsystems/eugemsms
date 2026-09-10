<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\ServiceTestData;
use Modules\Core\Domain\DataObjects\Install\ServiceTestResult;
use Throwable;

/**
 * ACT-TestServiceConnection (Book A CORE-01 §3). BR-CORE-01-010: every
 * service test is skippable — a school without an SMS gateway on day one
 * must still be able to finish installing.
 */
final class TestServiceConnectionAction extends Action
{
    protected bool $transactional = false;

    public function execute(ServiceTestData $data): ServiceTestResult
    {
        return match ($data->service) {
            'mail' => $this->testMail($data->config),
            'storage' => $this->testStorage(),
            'queue' => $this->testQueue(),
            'sms', 'whatsapp' => new ServiceTestResult(false, ucfirst($data->service).' gateways ship with COM-01 — not yet available.'),
            default => new ServiceTestResult(false, "Unknown service: {$data->service}."),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function testMail(array $config): ServiceTestResult
    {
        $transport = (string) ($config['transport'] ?? config('mail.default'));

        if ($transport !== 'smtp') {
            return new ServiceTestResult(true, "Mail transport [{$transport}] does not require a connectivity test.");
        }

        $host = (string) ($config['host'] ?? config('mail.mailers.smtp.host'));
        $port = (int) ($config['port'] ?? config('mail.mailers.smtp.port', 587));

        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 5);

        if ($socket === false) {
            return new ServiceTestResult(false, "Could not reach {$host}:{$port} — {$errorMessage}");
        }

        fclose($socket);

        return new ServiceTestResult(true, "Connected to {$host}:{$port}.");
    }

    private function testStorage(): ServiceTestResult
    {
        $path = 'serp-install-test-'.Str::random(8).'.txt';

        try {
            Storage::put($path, 'ok');
            $written = Storage::get($path) === 'ok';
            Storage::delete($path);

            return $written
                ? new ServiceTestResult(true, 'Storage disk is writable.')
                : new ServiceTestResult(false, 'Wrote to storage but could not read the file back.');
        } catch (Throwable $exception) {
            return new ServiceTestResult(false, 'Storage test failed: '.$exception->getMessage());
        }
    }

    private function testQueue(): ServiceTestResult
    {
        $connection = (string) config('queue.default');

        return match ($connection) {
            'sync' => new ServiceTestResult(true, 'Queue driver is [sync] — jobs run inline, no connection to test.'),
            'redis' => $this->testRedisQueue(),
            'database' => new ServiceTestResult(true, 'Queue driver is [database] — uses the already-verified database connection.'),
            default => new ServiceTestResult(true, "Queue driver [{$connection}] configured."),
        };
    }

    private function testRedisQueue(): ServiceTestResult
    {
        try {
            $pong = Redis::connection()->ping();

            return new ServiceTestResult(true, 'Redis responded: '.(is_string($pong) ? $pong : 'PONG'));
        } catch (Throwable $exception) {
            return new ServiceTestResult(false, 'Redis connection failed: '.$exception->getMessage());
        }
    }
}
