<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Modules\Comms\Domain\DataObjects\SmsSegmentResult;

/**
 * Book I COM-01 §4 ⭐/BR-COM-01-009 (AC-COM-01-003). GSM-7 carries 160
 * characters per segment; the moment a single character falls outside
 * the GSM-7 alphabet, the ENTIRE message drops to UCS-2 at 70
 * characters per segment. `normalize()` maps the common Word/curly-quote
 * culprits (curly quotes, em/en-dashes) to their GSM-7-safe equivalents
 * BEFORE `calculate()` ever runs, so a normalisable message never pays
 * the UCS-2 penalty it didn't need to.
 */
final class SmsSegmentCalculator
{
    /**
     * @var array<string, string>
     */
    private const array NORMALIZE_MAP = [
        "\u{2018}" => "'", // left single quote
        "\u{2019}" => "'", // right single quote
        "\u{201C}" => '"', // left double quote
        "\u{201D}" => '"', // right double quote
        "\u{2013}" => '-', // en-dash
        "\u{2014}" => '-', // em-dash
        "\u{2026}" => '...', // horizontal ellipsis
    ];

    private const string GSM7_PATTERN = '/^[\x0A\x0D\x20-\x7E£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¡ÄÖÑÜ§¿äöñüà]*$/u';

    public function normalize(string $text): string
    {
        return strtr($text, self::NORMALIZE_MAP);
    }

    public function calculate(string $rawBody): SmsSegmentResult
    {
        $normalized = $this->normalize($rawBody);
        $isGsm7 = (bool) preg_match(self::GSM7_PATTERN, $normalized);
        $length = mb_strlen($normalized);

        if ($isGsm7) {
            $singleSegmentLimit = 160;
            $concatenatedSegmentSize = 153;
        } else {
            $singleSegmentLimit = 70;
            $concatenatedSegmentSize = 67;
        }

        $segmentCount = $length <= $singleSegmentLimit ? 1 : (int) ceil($length / $concatenatedSegmentSize);

        return new SmsSegmentResult(
            normalizedBody: $normalized,
            encoding: $isGsm7 ? 'gsm7' : 'ucs2',
            characterCount: $length,
            segmentCount: max(1, $segmentCount),
        );
    }
}
