<?php

namespace Tests\Unit\Services;

use App\Exceptions\TemplateRenderException;
use App\Models\NotificationTemplate;
use App\Services\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_substitutes_variables(): void
    {
        $template = NotificationTemplate::factory()->create([
            'body' => 'Hello {{name}}, your code is {{code}}.',
            'variables' => ['name', 'code'],
        ]);

        $rendered = (new TemplateRenderer)->render($template, ['name' => 'Maya', 'code' => '1234']);

        $this->assertSame('Hello Maya, your code is 1234.', $rendered);
    }

    public function test_it_fails_when_required_variable_is_missing(): void
    {
        $template = NotificationTemplate::factory()->create([
            'body' => 'Hi {{name}}',
            'variables' => ['name'],
        ]);

        $this->expectException(TemplateRenderException::class);

        (new TemplateRenderer)->render($template, []);
    }
}
