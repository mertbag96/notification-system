<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocsController extends Controller
{
    public function __invoke(Request $request): HttpResponse|View
    {
        if ($request->query('format') === 'yaml' || $request->is('*openapi.yaml')) {
            $yamlPath = base_path('docs/openapi.yaml');

            return response(File::get($yamlPath), 200, ['Content-Type' => 'application/yaml']);
        }

        return view('docs');
    }
}
