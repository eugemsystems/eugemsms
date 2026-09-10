<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

use Modules\Core\Domain\Exceptions\TemplateSandboxViolationException;
use Modules\Core\Domain\Exceptions\UnknownTemplateVariableException;
use Modules\Core\Domain\Registry\TemplateFilterRegistry;
use Modules\Core\Domain\Registry\TemplateVariableRegistry;

/**
 * Book A CORE-06 §4/BR-CORE-06-009/010/AC-CORE-06-004. Parses and
 * checks a template at save time, before a single character of it is
 * ever rendered: every variable it references must be in its type's
 * registered set, and every filter it calls must be a registered one.
 */
final class TemplateValidator
{
    public function __construct(
        private readonly TemplateParser $parser,
    ) {}

    public function validate(string $templateType, string $content): void
    {
        $nodes = $this->parser->parse($content);

        $this->walk($templateType, $nodes, []);
    }

    /**
     * @param  array<int, TemplateNode>  $nodes
     * @param  array<string, string>  $aliases  loop item name → list path it iterates
     */
    private function walk(string $templateType, array $nodes, array $aliases): void
    {
        foreach ($nodes as $node) {
            match ($node->type) {
                'variable' => $this->checkVariable($templateType, $node, $aliases),
                'if' => $this->walkIf($templateType, $node, $aliases),
                'foreach' => $this->walkForeach($templateType, $node, $aliases),
                default => null,
            };
        }
    }

    /**
     * @param  array<string, string>  $aliases
     */
    private function checkVariable(string $templateType, TemplateNode $node, array $aliases): void
    {
        $resolvedPath = $this->resolveAliasedPath((string) $node->path, $aliases);

        if (! TemplateVariableRegistry::isRegistered($templateType, $resolvedPath)) {
            throw new UnknownTemplateVariableException(
                "Template type [{$templateType}] has no registered variable [{$node->path}].",
                ['template_type' => $templateType, 'variable' => $node->path],
            );
        }

        foreach ($node->filters as $filter) {
            if (! TemplateFilterRegistry::has($filter['name'])) {
                throw new TemplateSandboxViolationException(
                    "Filter [{$filter['name']}] is not registered.",
                    ['filter' => $filter['name']],
                );
            }
        }
    }

    /**
     * @param  array<string, string>  $aliases
     */
    private function walkIf(string $templateType, TemplateNode $node, array $aliases): void
    {
        $condition = (string) $node->condition;

        if (preg_match('/^([a-zA-Z0-9_.]+)/', $condition, $matches) === 1) {
            $path = $this->resolveAliasedPath($matches[1], $aliases);

            if (! TemplateVariableRegistry::isRegistered($templateType, $path)) {
                throw new UnknownTemplateVariableException(
                    "Template type [{$templateType}] has no registered variable [{$matches[1]}].",
                    ['template_type' => $templateType, 'variable' => $matches[1]],
                );
            }
        }

        $this->walk($templateType, $node->children, $aliases);
    }

    /**
     * @param  array<string, string>  $aliases
     */
    private function walkForeach(string $templateType, TemplateNode $node, array $aliases): void
    {
        $listPath = $this->resolveAliasedPath((string) $node->listPath, $aliases);

        if (! TemplateVariableRegistry::isRegistered($templateType, $listPath) && ! TemplateVariableRegistry::isRegistered($templateType, "{$listPath}.*")) {
            throw new UnknownTemplateVariableException(
                "Template type [{$templateType}] has no registered variable [{$node->listPath}].",
                ['template_type' => $templateType, 'variable' => $node->listPath],
            );
        }

        $this->walk($templateType, $node->children, [...$aliases, (string) $node->itemName => $listPath]);
    }

    /**
     * @param  array<string, string>  $aliases
     */
    private function resolveAliasedPath(string $path, array $aliases): string
    {
        [$root, $rest] = array_pad(explode('.', $path, 2), 2, null);

        if ($root !== null && array_key_exists($root, $aliases)) {
            return $rest !== null ? "{$aliases[$root]}.{$rest}" : $aliases[$root];
        }

        return $path;
    }
}
