<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Registry;

/**
 * Book H3 CMP-03 §3/BR-CMP-03-005 ⭐ (AC-CMP-03-006). A code-owned list
 * — mirrors `Modules\Core\Domain\Registry\TenantModelRegistry`'s own
 * shape (register/all/clear). Every table a module registers here is
 * what `CheckRetentionScheduleCoverageAction`'s nightly check compares
 * against active `retention_schedules` rows.
 *
 * **Scope boundary**: this pass pre-registers only the tables this
 * module can directly confirm hold personal data (`ComplianceServiceProvider::
 * registerPersonalDataTables()`) — `students`, `staff`, `guardians`,
 * `zimsec_candidates`, `consents` itself. Retrofitting every one of
 * this codebase's other ~17 modules' own tables into their own
 * `ServiceProvider::boot()` (the way `TenantModelRegistry` genuinely
 * is retrofitted everywhere) is real, valuable follow-up work, not
 * done here — the registry architecture is complete and extensible,
 * matching the same documented boundary Book H3 FIN-12's
 * `CloseChecklistRegistry` adoption drew.
 */
final class PersonalDataTableRegistry
{
    /**
     * @var array<string, array{owning_module: string, description: string}>
     */
    private static array $tables = [];

    public static function register(string $tableName, string $owningModule, string $description = ''): void
    {
        self::$tables[$tableName] = ['owning_module' => $owningModule, 'description' => $description];
    }

    /**
     * @return array<string, array{owning_module: string, description: string}>
     */
    public static function all(): array
    {
        return self::$tables;
    }

    public static function clear(): void
    {
        self::$tables = [];
    }
}
