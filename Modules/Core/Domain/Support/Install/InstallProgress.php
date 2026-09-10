<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

use Illuminate\Support\Carbon;
use Modules\Core\Models\InstallStep;

/**
 * Book A CORE-01 §2 / BR-CORE-01-001, BR-CORE-01-003. The installer's own
 * resumability ledger — every step's outcome is persisted, and a resumed
 * installation restarts at the first non-completed step, never step 1.
 */
final class InstallProgress
{
    public static function isInstalled(): bool
    {
        return file_exists(storage_path('installed.lock'));
    }

    public function resumeStep(): InstallStepKey
    {
        foreach (InstallStepKey::ordered() as $key) {
            if ($this->statusOf($key) !== InstallStepStatus::Completed) {
                return $key;
            }
        }

        return InstallStepKey::Finalise;
    }

    /**
     * @return array<int, InstallStepKey>
     */
    public function completedSteps(): array
    {
        return array_values(array_filter(
            InstallStepKey::ordered(),
            fn (InstallStepKey $key): bool => $this->statusOf($key) === InstallStepStatus::Completed,
        ));
    }

    public function statusOf(InstallStepKey $key): InstallStepStatus
    {
        $step = $this->find($key);

        return $step !== null ? $step->status : InstallStepStatus::Pending;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadOf(InstallStepKey $key): array
    {
        $step = $this->find($key);

        return $step !== null ? ($step->payload ?? []) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markRunning(InstallStepKey $key, array $payload = []): void
    {
        $this->upsert($key, InstallStepStatus::Running, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markCompleted(InstallStepKey $key, array $payload = []): void
    {
        $this->upsert($key, InstallStepStatus::Completed, $payload, completedAt: Carbon::now());
    }

    public function markFailed(InstallStepKey $key, string $errorMessage): void
    {
        $this->upsert($key, InstallStepStatus::Failed, errorMessage: $errorMessage);
    }

    public function reset(): void
    {
        InstallStep::query()->delete();
    }

    private function find(InstallStepKey $key): ?InstallStep
    {
        return InstallStep::where('step_key', $key->value)->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsert(
        InstallStepKey $key,
        InstallStepStatus $status,
        array $payload = [],
        ?Carbon $completedAt = null,
        ?string $errorMessage = null,
    ): void {
        $step = $this->find($key) ?? new InstallStep(['step_key' => $key->value]);

        $step->status = $status;
        $step->payload = $payload !== [] ? array_merge($step->payload ?? [], $payload) : $step->payload;
        $step->completed_at = $completedAt;
        $step->error_message = $errorMessage;
        $step->save();
    }
}
