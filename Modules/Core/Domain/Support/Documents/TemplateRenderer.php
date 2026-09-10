<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

use Modules\Core\Domain\Exceptions\TemplateSandboxViolationException;
use Modules\Core\Domain\Registry\TemplateFilterRegistry;

/**
 * Book A CORE-06 §4/BR-CORE-06-011. Renders an already-validated
 * `TemplateNode` tree against a plain data array — no database
 * queries, no filesystem access, nothing beyond array/string
 * manipulation, so the same (template version, data) pair always
 * produces byte-identical output (the determinism `file_hash` proves).
 */
final class TemplateRenderer
{
    public function __construct(
        private readonly TemplateParser $parser,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $content, array $data): string
    {
        return $this->renderNodes($this->parser->parse($content), $data);
    }

    /**
     * @param  array<int, TemplateNode>  $nodes
     * @param  array<string, mixed>  $data
     */
    private function renderNodes(array $nodes, array $data): string
    {
        $output = '';

        foreach ($nodes as $node) {
            $output .= match ($node->type) {
                'text' => $node->text,
                'variable' => $this->renderVariable($node, $data),
                'if' => $this->evaluateCondition((string) $node->condition, $data) ? $this->renderNodes($node->children, $data) : '',
                'foreach' => $this->renderForeach($node, $data),
                default => '',
            };
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderVariable(TemplateNode $node, array $data): string
    {
        $value = $this->resolve((string) $node->path, $data);

        foreach ($node->filters as $filter) {
            $value = TemplateFilterRegistry::apply($filter['name'], $value, $filter['args']);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderForeach(TemplateNode $node, array $data): string
    {
        $list = $this->resolve((string) $node->listPath, $data);

        if (! is_iterable($list)) {
            return '';
        }

        $output = '';

        foreach ($list as $item) {
            $output .= $this->renderNodes($node->children, [...$data, (string) $node->itemName => $item]);
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        if (! preg_match('/^([a-zA-Z0-9_.]+)\s*(==|!=|>=|<=|>|<)?\s*(.*)$/', $condition, $matches)) {
            throw new TemplateSandboxViolationException("Malformed @if condition: [{$condition}].");
        }

        $left = $this->resolve($matches[1], $data);
        $operator = $matches[2] !== '' ? $matches[2] : null;

        if ($operator === null) {
            return (bool) $left;
        }

        $right = $this->parseLiteral(trim($matches[3]));

        if ($operator === '==') {
            return $left == $right;
        }

        if ($operator === '!=') {
            return $left != $right;
        }

        if ($operator === '>') {
            return $left > $right;
        }

        if ($operator === '<') {
            return $left < $right;
        }

        if ($operator === '>=') {
            return $left >= $right;
        }

        // The capture group in the condition regex above only ever
        // yields one of the six operators handled above, or null
        // (handled earlier) — this is therefore the only case left.
        return $left <= $right;
    }

    private function parseLiteral(string $raw): mixed
    {
        return match (true) {
            $raw === 'true' => true,
            $raw === 'false' => false,
            $raw === 'null' => null,
            preg_match('/^-?\d+(\.\d+)?$/', $raw) === 1 => str_contains($raw, '.') ? (float) $raw : (int) $raw,
            (str_starts_with($raw, "'") && str_ends_with($raw, "'")) || (str_starts_with($raw, '"') && str_ends_with($raw, '"')) => substr($raw, 1, -1),
            default => $raw,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolve(string $path, array $data): mixed
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            if (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};

                continue;
            }

            return null;
        }

        return $value;
    }
}
