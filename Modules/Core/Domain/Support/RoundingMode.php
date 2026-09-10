<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

/**
 * BR-GLOBAL-023: default rounding is banker's rounding, applied once, at
 * the point of persistence (Book A Part 1.4).
 */
enum RoundingMode
{
    case Banker;
    case HalfUp;
}
