<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\WhatsAppTemplate;

final class TemplateSubmitted
{
    public function __construct(
        public readonly WhatsAppTemplate $template,
    ) {}
}
