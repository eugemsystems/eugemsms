<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

use Modules\Core\Domain\Contracts\Install\SeedPack;
use Modules\Core\Domain\Contracts\Install\SeedPackOutcome;
use Modules\Core\Models\School;

/**
 * A seed pack named in Book A CORE-01 §7 whose owning module (roles →
 * CORE-05, coa → FIN-01, grading → ACA-05, learning_areas → ACA-01,
 * levels → CORE-02, templates → CORE-06, notifications → CORE-09,
 * settings → CORE-04) is not built yet. Registered so the installer's
 * Seed screen can list it — visibly, honestly disabled — instead of
 * either lying about seeding it or crashing.
 */
final readonly class PendingSeedPack implements SeedPack
{
    public function __construct(
        private string $code,
        private string $label,
        private string $description,
        private string $owningModule,
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function run(School $school): SeedPackOutcome
    {
        return new SeedPackOutcome(
            packCode: $this->code,
            ran: false,
            message: "Not yet available — ships with {$this->owningModule}.",
        );
    }
}
