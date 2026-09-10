<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Support;

/**
 * Book G BRD-06 §0.2 ⭐ — the three-tier visibility model.
 */
enum MedicalTier: string
{
    case Existence = 'existence';
    case Actionable = 'actionable';
    case Clinical = 'clinical';
}
