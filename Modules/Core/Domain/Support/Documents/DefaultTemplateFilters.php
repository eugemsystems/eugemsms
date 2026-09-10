<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Registry\TemplateFilterRegistry;
use Modules\Core\Domain\Support\Money;

/**
 * Book A CORE-06 §4's built-in filter catalogue. `image`/`signature`/
 * `qr`/`barcode` render inert placeholder markup carrying the raw
 * value as a data attribute rather than a real rendered image — actual
 * QR/barcode generation is a new dependency, deferred to a later wave
 * alongside real PDF rendering (`HtmlDocumentRenderer`'s own docblock).
 */
final class DefaultTemplateFilters
{
    public static function register(): void
    {
        TemplateFilterRegistry::register('upper', fn (mixed $v): string => mb_strtoupper((string) $v));
        TemplateFilterRegistry::register('lower', fn (mixed $v): string => mb_strtolower((string) $v));
        TemplateFilterRegistry::register('title', fn (mixed $v): string => Str::title((string) $v));

        TemplateFilterRegistry::register('money', fn (mixed $v): string => $v instanceof Money ? $v->format() : (string) $v);

        TemplateFilterRegistry::register('number', fn (mixed $v, array $args): string => number_format((float) $v, isset($args[0]) ? (int) $args[0] : 0));

        TemplateFilterRegistry::register('date', function (mixed $v, array $args): string {
            $date = $v instanceof Carbon ? $v : Carbon::parse((string) $v);

            return $date->format($args[0] ?? 'd M Y');
        });

        TemplateFilterRegistry::register('date_diff', function (mixed $v): string {
            $date = $v instanceof Carbon ? $v : Carbon::parse((string) $v);

            return $date->diffForHumans();
        });

        TemplateFilterRegistry::register('ordinal', fn (mixed $v): string => self::ordinal((int) $v));

        TemplateFilterRegistry::register('percentage', fn (mixed $v, array $args): string => number_format(((float) $v) * 100, isset($args[0]) ? (int) $args[0] : 0).'%');

        TemplateFilterRegistry::register('truncate', fn (mixed $v, array $args): string => Str::limit((string) $v, isset($args[0]) ? (int) $args[0] : 50));

        TemplateFilterRegistry::register('default', fn (mixed $v, array $args): mixed => $v !== null && $v !== '' ? $v : ($args[0] ?? ''));

        TemplateFilterRegistry::register('image', fn (mixed $v, array $args): string => sprintf('<img src="%s" width="%s">', $v, $args[0] ?? 'auto'));

        TemplateFilterRegistry::register('signature', fn (mixed $v): string => sprintf('<img class="signature" src="%s">', $v));

        TemplateFilterRegistry::register('qr', fn (mixed $v): string => sprintf('<div class="qr-code" data-value="%s"></div>', $v));

        TemplateFilterRegistry::register('barcode', fn (mixed $v): string => sprintf('<div class="barcode" data-value="%s"></div>', $v));

        TemplateFilterRegistry::register('table', function (mixed $v, array $args): string {
            if (! is_iterable($v)) {
                return '';
            }

            $header = '<tr>'.implode('', array_map(fn (string $column): string => '<th>'.e($column).'</th>', $args)).'</tr>';

            $rows = '';

            foreach ($v as $row) {
                $rows .= '<tr>'.implode('', array_map(function (string $column) use ($row): string {
                    $cell = is_array($row) ? ($row[$column] ?? '') : ($row->{$column} ?? '');

                    return '<td>'.e((string) $cell).'</td>';
                }, $args)).'</tr>';
            }

            return "<table>{$header}{$rows}</table>";
        });
    }

    private static function ordinal(int $number): string
    {
        if (in_array($number % 100, [11, 12, 13], true)) {
            return "{$number}th";
        }

        $suffix = match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };

        return "{$number}{$suffix}";
    }
}
