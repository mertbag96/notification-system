@extends('layouts.app')

@section('title', config('app.name', 'Notification System') . ' — Dashboard')

@push('head')
    <meta name="api-base" content="{{ url('/api/v1') }}">
    <meta name="api-key" content="{{ config('notifications.api.key', '') }}">
    @vite(['resources/js/dashboard.js'])
@endpush

@section('header_actions')
    <div id="status-pill"
         class="hidden sm:inline-flex items-center gap-2 rounded-full border border-black/10 px-3 py-1.5 text-xs font-medium whitespace-nowrap">
        <span class="status-dot status-dot--idle"></span>
        <span class="status-text">Connecting…</span>
    </div>

    <button id="refresh-btn"
            class="inline-flex items-center gap-2 rounded-md border border-black px-2.5 sm:px-3 py-1.5 text-xs font-medium hover:bg-black hover:text-white transition"
            aria-label="Refresh">
        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 1 1-3-6.7L21 8"/>
            <path d="M21 3v5h-5"/>
        </svg>
        <span class="hidden sm:inline">Refresh</span>
    </button>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 py-8 sm:py-10 space-y-10 sm:space-y-12">

        <section>
            <div class="flex items-end justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">System health</h2>
                    <p class="text-sm text-black/50">Live checks for core dependencies.</p>
                </div>
                <span id="health-meta" class="text-xs text-black/40">—</span>
            </div>

            <div id="health-grid" class="grid grid-cols-1 sm:grid-cols-3 gap-4"></div>

            {{-- Kept as a sibling of #health-grid so the JS poll can clear the grid
                 (grid.innerHTML = '') without removing this template from the DOM. --}}
            <template id="health-card-template">
                <div class="rounded-lg border border-black/10 p-5 hover:border-black/30 transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wider text-black/50" data-name></span>
                        <span class="status-dot" data-dot></span>
                    </div>
                    <div class="mt-3 text-2xl font-semibold tracking-tight" data-status>—</div>
                    <div class="mt-1 text-xs text-black/50" data-message>Awaiting response.</div>
                </div>
            </template>
        </section>

        <section>
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold tracking-tight">Metrics</h2>
                    <p class="text-sm text-black/50">
                        Queue depth and realtime delivery <span id="metrics-window" class="whitespace-nowrap">(last 5 min)</span>.
                    </p>
                </div>
                <span id="metrics-meta" class="text-xs text-black/40 whitespace-nowrap">—</span>
            </div>

            <div class="space-y-4">
                <div class="rounded-lg border border-black/10 p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs uppercase tracking-wider text-black/50">Queue depth</h3>
                        <span class="hidden sm:inline text-[11px] text-black/40 whitespace-nowrap">Pending + queued</span>
                    </div>
                    <div id="queue-depth" class="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                        <div class="col-span-3 text-sm text-black/40">Loading…</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="rounded-lg border border-black/10 p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs uppercase tracking-wider text-black/50">Totals</h3>
                            <span class="hidden sm:inline text-[11px] text-black/40 whitespace-nowrap">Success vs failure</span>
                        </div>
                        <div id="totals-grid" class="mt-4 grid grid-cols-2 gap-2 sm:gap-3">
                            <div class="col-span-2 text-sm text-black/40">Loading…</div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-black/10 p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs uppercase tracking-wider text-black/50">Latency</h3>
                            <span class="hidden sm:inline text-[11px] text-black/40 whitespace-nowrap">All channels</span>
                        </div>
                        <div id="latency-grid" class="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                            <div class="col-span-3 text-sm text-black/40">Loading…</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-black/10 overflow-hidden">
                    <div class="flex items-center justify-between gap-2 px-5 pt-5 pb-3">
                        <h3 class="text-xs uppercase tracking-wider text-black/50">By channel</h3>
                        <span class="hidden sm:inline text-[11px] text-black/40 whitespace-nowrap">Counts &amp; latency per channel</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-black/[0.02] text-[11px] uppercase tracking-wider text-black/50">
                                <tr>
                                    <th class="text-left font-medium px-5 py-2.5">Channel</th>
                                    <th class="text-right font-medium px-5 py-2.5">Success</th>
                                    <th class="text-right font-medium px-5 py-2.5">Failure</th>
                                    <th class="text-right font-medium px-5 py-2.5">p50</th>
                                    <th class="text-right font-medium px-5 py-2.5">p95</th>
                                    <th class="text-right font-medium px-5 py-2.5">p99</th>
                                </tr>
                            </thead>
                            <tbody id="by-channel-tbody" class="divide-y divide-black/5">
                                <tr><td colspan="6" class="px-5 py-6 text-center text-sm text-black/40">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">Notifications</h2>
                    <p class="text-sm text-black/50">Latest deliveries across all channels.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select id="filter-status" class="select">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="queued">Queued</option>
                        <option value="processing">Processing</option>
                        <option value="sent">Sent</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="dead_lettered">Dead-lettered</option>
                    </select>

                    <select id="filter-channel" class="select">
                        <option value="">All channels</option>
                        <option value="sms">SMS</option>
                        <option value="email">Email</option>
                        <option value="push">Push</option>
                    </select>

                    <select id="filter-per-page" class="select">
                        <option value="10">10 / page</option>
                        <option value="25" selected>25 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100">100 / page</option>
                    </select>
                </div>
            </div>

            <div class="rounded-lg border border-black/10 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-black/[0.02] text-xs uppercase tracking-wider text-black/50">
                            <tr>
                                <th class="text-left font-medium px-4 py-3">Status</th>
                                <th class="text-left font-medium px-4 py-3">Channel</th>
                                <th class="text-left font-medium px-4 py-3">Recipient</th>
                                <th class="text-left font-medium px-4 py-3">Priority</th>
                                <th class="text-left font-medium px-4 py-3">Attempts</th>
                                <th class="text-left font-medium px-4 py-3">Created</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody id="notifications-tbody" class="divide-y divide-black/5">
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-black/40">
                                    Loading notifications…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-black/10 px-4 py-3 text-xs text-black/60">
                    <span id="pagination-summary">—</span>
                    <div class="flex items-center gap-2">
                        <button id="prev-page" class="btn-page" disabled>Previous</button>
                        <button id="next-page" class="btn-page" disabled>Next</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('body_end')
    <div id="drawer" class="drawer" aria-hidden="true">
        <div class="drawer__backdrop" data-close></div>
        <aside class="drawer__panel">
            <header class="flex items-start justify-between gap-4 px-6 py-5 border-b border-black/10">
                <div>
                    <div class="text-xs uppercase tracking-wider text-black/50">Notification</div>
                    <h3 id="drawer-title" class="mt-1 text-base font-semibold tracking-tight break-all">—</h3>
                </div>
                <button data-close class="rounded-md border border-black/10 p-1.5 hover:border-black transition" aria-label="Close">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </header>
            <div id="drawer-body" class="overflow-y-auto px-6 py-5 space-y-6">
                <p class="text-sm text-black/50">Loading…</p>
            </div>
        </aside>
    </div>
@endpush
