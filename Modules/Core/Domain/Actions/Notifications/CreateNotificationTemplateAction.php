<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\Exceptions\UnknownTemplateVariableException;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\Documents\TemplateParser;
use Modules\Core\Models\NotificationTemplate;

/**
 * ACT-CreateNotificationTemplate (Book A CORE-09 §2, "variables JSON —
 * declared, validated at save"). Every variable the body/subject
 * reference must be in the notification key's own registered set
 * (`NotificationKeyRegistry`) — the save-time half of BR-CORE-09-003,
 * `NotificationRenderer` is the render-time half.
 */
final class CreateNotificationTemplateAction extends Action
{
    public function __construct(
        private readonly TemplateParser $parser,
    ) {}

    public function execute(CreateNotificationTemplateData $data): NotificationTemplate
    {
        $definition = NotificationKeyRegistry::get($data->key);

        $this->assertVariablesRegistered($data->body, $definition->variables);

        if ($data->subject !== null) {
            $this->assertVariablesRegistered($data->subject, $definition->variables);
        }

        return $this->transaction(fn (): NotificationTemplate => NotificationTemplate::create([
            'school_id' => $data->schoolId,
            'key' => $data->key,
            'channel' => $data->channel,
            'locale' => $data->locale,
            'subject' => $data->subject,
            'body' => $data->body,
            'variables' => $definition->variables,
            'provider_template_id' => $data->providerTemplateId,
            'is_active' => true,
        ]));
    }

    /**
     * @param  array<int, string>  $registered
     */
    private function assertVariablesRegistered(string $content, array $registered): void
    {
        foreach ($this->parser->parse($content) as $node) {
            if ($node->type === 'variable' && ! in_array($node->path, $registered, true)) {
                throw new UnknownTemplateVariableException(
                    "Variable [{$node->path}] is not declared for this notification key.",
                    ['variable' => $node->path],
                );
            }
        }
    }
}
