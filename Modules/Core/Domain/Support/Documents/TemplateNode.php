<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

/**
 * Book A CORE-06 §4. One node of a parsed template's tree — literal
 * text, a `{{ variable | filter }}` interpolation, or an `@if`/
 * `@foreach` block with its own child nodes.
 */
final class TemplateNode
{
    /**
     * @param  array<int, array{name: string, args: array<int, string>}>  $filters
     * @param  array<int, self>  $children
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $text = null,
        public readonly ?string $path = null,
        public readonly array $filters = [],
        public readonly ?string $condition = null,
        public readonly ?string $listPath = null,
        public readonly ?string $itemName = null,
        public readonly array $children = [],
    ) {}

    public static function text(string $text): self
    {
        return new self('text', text: $text);
    }

    /**
     * @param  array<int, array{name: string, args: array<int, string>}>  $filters
     */
    public static function variable(string $path, array $filters): self
    {
        return new self('variable', path: $path, filters: $filters);
    }

    /**
     * @param  array<int, self>  $children
     */
    public static function ifBlock(string $condition, array $children): self
    {
        return new self('if', condition: $condition, children: $children);
    }

    /**
     * @param  array<int, self>  $children
     */
    public static function foreachBlock(string $listPath, string $itemName, array $children): self
    {
        return new self('foreach', listPath: $listPath, itemName: $itemName, children: $children);
    }
}
