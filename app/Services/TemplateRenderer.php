<?php

namespace App\Services;

use App\Exceptions\TemplateRenderException;
use App\Models\NotificationTemplate;

final class TemplateRenderer
{
    /**
     * @param  array<string,scalar|null>  $variables
     */
    public function render(NotificationTemplate $template, array $variables): string
    {
        $required = $template->variables ?? [];

        foreach ($required as $key) {
            if (! array_key_exists($key, $variables)) {
                throw new TemplateRenderException("Missing template variable: {$key}");
            }
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $matches) use ($variables): string {
                $key = $matches[1];

                return isset($variables[$key]) ? (string) $variables[$key] : $matches[0];
            },
            $template->body
        );
    }
}
