<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

/**
 * ACT-WriteEnvironmentFile (Book A CORE-01 §3): atomic write, backs up
 * the existing `.env` first (BR-CORE-01-004 — credentials are validated
 * by an actual connection before ever reaching this class).
 */
final class EnvFileWriter
{
    public function __construct(
        private readonly string $envPath,
    ) {}

    /**
     * @param  array<string, string>  $values
     */
    public function write(array $values): void
    {
        $existing = file_exists($this->envPath) ? file_get_contents($this->envPath) : '';
        $existing = $existing === false ? '' : $existing;

        if ($existing !== '') {
            file_put_contents($this->envPath.'.backup-'.date('YmdHis'), $existing, LOCK_EX);
        }

        $lines = $existing === '' ? [] : explode("\n", $existing);
        $remainingKeys = $values;

        foreach ($lines as $index => $line) {
            $key = $this->keyOf($line);

            if ($key !== null && array_key_exists($key, $remainingKeys)) {
                $lines[$index] = $this->formatLine($key, $remainingKeys[$key]);
                unset($remainingKeys[$key]);
            }
        }

        foreach ($remainingKeys as $key => $value) {
            $lines[] = $this->formatLine($key, $value);
        }

        $tempPath = $this->envPath.'.tmp-'.uniqid();
        file_put_contents($tempPath, implode("\n", $lines), LOCK_EX);
        rename($tempPath, $this->envPath);
    }

    private function keyOf(string $line): ?string
    {
        if (! str_contains($line, '=') || str_starts_with(trim($line), '#')) {
            return null;
        }

        return trim(explode('=', $line, 2)[0]);
    }

    private function formatLine(string $key, string $value): string
    {
        $needsQuotes = $value === '' || preg_match('/\s|#|"/', $value) === 1;
        $escaped = str_replace('"', '\"', $value);

        return $needsQuotes ? "{$key}=\"{$escaped}\"" : "{$key}={$value}";
    }
}
