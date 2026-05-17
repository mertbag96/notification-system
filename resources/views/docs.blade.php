@extends('layouts.app')

@section('title', config('app.name', 'Notification System') . ' — API Docs')
@section('subtitle', 'API reference')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        .swagger-ui .topbar { display: none; }
        .swagger-ui { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .swagger-ui .info { margin: 0; padding: 0; }
        #swagger-ui .wrapper { padding: 0; }
    </style>
@endpush

@section('header_actions')
    <a href="{{ url('/api/openapi.yaml') }}"
       class="inline-flex items-center gap-2 rounded-md border border-black px-2.5 sm:px-3 py-1.5 text-xs font-medium hover:bg-black hover:text-white transition"
       aria-label="Download OpenAPI YAML">
        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        <span class="hidden sm:inline">Download YAML</span>
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 py-8 sm:py-10">
        <div class="mb-6">
            <h2 class="text-lg font-semibold tracking-tight">API reference</h2>
            <p class="text-sm text-black/50">
                Interactive OpenAPI 3.1 documentation. Use the
                <code class="rounded bg-black/[0.04] px-1.5 py-0.5 text-[11px]">X-Api-Key</code>
                header to authenticate against <code class="rounded bg-black/[0.04] px-1.5 py-0.5 text-[11px]">/api/v1</code>.
            </p>
        </div>

        <div class="rounded-lg border border-black/10 bg-white p-2 sm:p-4">
            <div id="swagger-ui"></div>
        </div>
    </div>
@endsection

@push('body_end')
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.addEventListener('load', () => {
            window.SwaggerUIBundle({
                url: @json(url('/api/openapi.yaml')),
                dom_id: '#swagger-ui',
                deepLinking: true,
                docExpansion: 'list',
                defaultModelsExpandDepth: 0,
            });
        });
    </script>
@endpush
