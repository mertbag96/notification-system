<?php

namespace App\Actions;

use App\Exceptions\TemplateRenderException;
use App\Models\NotificationTemplate;
use App\Services\TemplateRenderer;

final class RenderTemplateAction
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string,scalar|null>  $variables
     */
    public function execute(?string $templateId, ?string $content, array $variables): string
    {
        if ($templateId === null) {
            if ($content === null || $content === '') {
                throw new TemplateRenderException('Either content or template_id must be provided.');
            }

            return $content;
        }

        $template = NotificationTemplate::query()->findOrFail($templateId);

        return $this->renderer->render($template, $variables);
    }
}
