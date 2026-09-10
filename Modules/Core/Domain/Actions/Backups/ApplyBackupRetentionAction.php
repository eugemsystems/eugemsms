<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Backups;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Backup;

/**
 * ACT-ApplyBackupRetention (Book A CORE-13 §3/BR-CORE-13-005).
 * Grandfather-father-son: a system-scope backup survives if it is the
 * most recent one taken on its own calendar day, ISO week, month, or
 * year, within however many of each the `backups.retention_*` settings
 * keep (CORE-05 §10's resolution chain — system default unless a
 * tenant/school override exists). Everything else is expired: the
 * underlying file is deleted and the row is kept, marked `expired`,
 * as its own audit trail.
 */
final class ApplyBackupRetentionAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array<int, Backup>
     */
    public function execute(): array
    {
        $backups = Backup::query()
            ->where('scope', 'system')
            ->whereIn('status', ['completed', 'verified'])
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->get();

        $keepIds = collect()
            ->merge($this->mostRecentPerBucket($backups, fn (Backup $b): string => $b->completed_at->toDateString(), (int) $this->settings->get('backups.retention_daily')))
            ->merge($this->mostRecentPerBucket($backups, fn (Backup $b): string => $b->completed_at->format('o-\WW'), (int) $this->settings->get('backups.retention_weekly')))
            ->merge($this->mostRecentPerBucket($backups, fn (Backup $b): string => $b->completed_at->format('Y-m'), (int) $this->settings->get('backups.retention_monthly')))
            ->merge($this->mostRecentPerBucket($backups, fn (Backup $b): string => $b->completed_at->format('Y'), (int) $this->settings->get('backups.retention_yearly')))
            ->pluck('id')
            ->unique();

        $expired = $backups->reject(fn (Backup $b): bool => $keepIds->contains($b->id));

        foreach ($expired as $backup) {
            Storage::disk($backup->disk)->delete($backup->path);

            $backup->update(['status' => 'expired', 'expires_at' => now()]);
        }

        return $expired->values()->all();
    }

    /**
     * @param  Collection<int, Backup>  $backups  ordered most-recent-first
     * @param  Closure(Backup): string  $bucketKey
     * @return Collection<int, Backup>
     */
    private function mostRecentPerBucket(Collection $backups, Closure $bucketKey, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return $backups
            ->groupBy($bucketKey)
            ->take($limit)
            ->map(fn (Collection $group): Backup => $group->first())
            ->values();
    }
}
