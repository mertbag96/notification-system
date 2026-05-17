<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <title>@yield('title', config('app.name', 'Notification System'))</title>
    @vite(['resources/css/dashboard.css'])
    @stack('head')
</head>
<body class="font-sans antialiased bg-white text-black">
    @php
        $isDashboard = request()->routeIs('dashboard');
        $isDocs = request()->routeIs('api.docs');
    @endphp

    <div class="min-h-screen flex flex-col">
        <header class="border-b border-black/10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 py-4 sm:py-5 flex items-center justify-between gap-3 sm:gap-4">
                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 group focus:outline-none min-w-0">
                    <div class="h-8 w-8 shrink-0 rounded-md bg-black text-white flex items-center justify-center text-sm font-semibold tracking-tight group-hover:bg-black/80 transition">
                        NS
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base font-semibold tracking-tight leading-tight truncate group-hover:text-black/70 transition">
                            {{ config('app.name', 'Notification System') }}
                        </h1>
                        <p class="text-xs text-black/50 truncate">@yield('subtitle', 'Operations dashboard')</p>
                    </div>
                </a>

                <nav class="hidden lg:flex items-center gap-1 text-xs font-medium">
                    <a href="{{ route('dashboard') }}"
                       class="rounded-md px-3 py-1.5 transition {{ $isDashboard ? 'bg-black text-white' : 'text-black/60 hover:text-black hover:bg-black/[0.04]' }}">
                        Overview
                    </a>
                    <a href="{{ route('api.docs') }}"
                       class="rounded-md px-3 py-1.5 transition {{ $isDocs ? 'bg-black text-white' : 'text-black/60 hover:text-black hover:bg-black/[0.04]' }}">
                        API Docs
                    </a>
                </nav>

                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    @yield('header_actions')
                </div>
            </div>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-black/10">
            @php
                $apiKey = (string) config('notifications.api.key', '');
                $apiKeyQuery = $apiKey !== '' ? ('?'.http_build_query(['api_key' => $apiKey])) : '';
            @endphp
            <div class="mx-auto max-w-7xl px-4 sm:px-6 py-6 flex flex-wrap items-center justify-between gap-2 text-xs text-black/50">
                <div>Laravel v{{ Illuminate\Foundation\Application::VERSION }} · PHP v{{ PHP_VERSION }}</div>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/api/v1/health').$apiKeyQuery }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="hover:text-black transition">Health</a>
                    <a href="{{ url('/api/v1/metrics').$apiKeyQuery }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="hover:text-black transition">Metrics</a>
                    <a href="{{ route('api.docs') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="hover:text-black transition">OpenAPI</a>
                </div>
            </div>
        </footer>
    </div>

    @stack('body_end')
</body>
</html>
