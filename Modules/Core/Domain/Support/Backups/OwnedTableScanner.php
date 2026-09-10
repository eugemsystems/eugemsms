<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Backups;

/**
 * Book A CORE-13 §2/BR-CORE-13-001. A "full" or "database" backup must
 * dump only this application's own tables — never every table the
 * configured DB connection can see. On at least this codebase's own dev
 * database, that connection is shared with several unrelated WordPress
 * installs and another PHP app (thousands of `wp_*`/`at_*` tables plus
 * things like `quotations`/`payments` that belong to nobody here), so a
 * naive "dump everything" backup would leak someone else's data. The
 * allowlist is derived by scanning this app's own migration files for
 * `Schema::create(...)` calls — self-maintaining as new modules land,
 * rather than a hand-kept table list that inevitably drifts.
 */
final class OwnedTableScanner
{
    /**
     * Infrastructure tables that are either Laravel's own bookkeeping
     * (never application data) or hold transient/sensitive material that
     * has no place in a portable backup.
     *
     * @var array<int, string>
     */
    private const EXCLUDED = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens',
    ];

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        $tables = [];

        foreach (self::migrationDirectories() as $directory) {
            foreach (glob($directory.'/*.php') ?: [] as $file) {
                $contents = file_get_contents($file);

                if ($contents === false) {
                    continue;
                }

                if (preg_match_all('/Schema::create\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $contents, $matches)) {
                    array_push($tables, ...$matches[1]);
                }
            }
        }

        return array_values(array_diff(array_unique($tables), self::EXCLUDED));
    }

    /**
     * @return array<int, string>
     */
    private static function migrationDirectories(): array
    {
        $directories = [database_path('migrations')];

        foreach (glob(base_path('Modules/*/database/migrations'), GLOB_ONLYDIR) ?: [] as $moduleMigrations) {
            $directories[] = $moduleMigrations;
        }

        return $directories;
    }
}
