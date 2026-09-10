<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

/**
 * Book A CORE-01 §5 — the eleven installer screens, in canonical order.
 * BR-CORE-01-003: a resumed installation restarts at the first
 * non-completed step in this order, never step 1.
 */
enum InstallStepKey: string
{
    case Welcome = 'welcome';
    case Requirements = 'requirements';
    case Environment = 'environment';
    case Database = 'database';
    case Migrations = 'migrations';
    case Licence = 'licence';
    case Administrator = 'administrator';
    case Organisation = 'organisation';
    case Seed = 'seed';
    case Services = 'services';
    case Finalise = 'finalise';

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [
            self::Welcome,
            self::Requirements,
            self::Environment,
            self::Database,
            self::Migrations,
            self::Licence,
            self::Administrator,
            self::Organisation,
            self::Seed,
            self::Services,
            self::Finalise,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome',
            self::Requirements => 'Requirements',
            self::Environment => 'Environment',
            self::Database => 'Database',
            self::Migrations => 'Migrations',
            self::Licence => 'Licence',
            self::Administrator => 'Administrator',
            self::Organisation => 'Tenant & School',
            self::Seed => 'Baseline Seed',
            self::Services => 'Services',
            self::Finalise => 'Finalise',
        };
    }

    public function routeName(): string
    {
        return 'install.'.$this->value;
    }

    public function next(): ?self
    {
        $ordered = self::ordered();
        $index = array_search($this, $ordered, true);

        return $ordered[$index + 1] ?? null;
    }
}
