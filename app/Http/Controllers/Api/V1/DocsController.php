<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocsController extends Controller
{
    public function __invoke(Request $request): HttpResponse
    {
        if ($request->query('format') === 'yaml' || $request->is('*openapi.yaml')) {
            $yamlPath = base_path('docs/openapi.yaml');

            return response(File::get($yamlPath), 200, ['Content-Type' => 'application/yaml']);
        }

        $url = url('/api/openapi.yaml');

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Notification System API</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>body { margin: 0; }</style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            SwaggerUIBundle({
                url: '{$url}',
                dom_id: '#swagger-ui',
                deepLinking: true,
            });
        };
    </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
