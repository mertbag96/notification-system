const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

const API_BASE = meta('api-base');
const API_KEY = meta('api-key');

const REFRESH_INTERVAL = 8000;

const STATUS_LABELS = {
    pending: 'Pending',
    queued: 'Queued',
    processing: 'Processing',
    sent: 'Sent',
    failed: 'Failed',
    cancelled: 'Cancelled',
    dead_lettered: 'Dead-lettered',
};

const CHANNEL_LABELS = {
    sms: 'SMS',
    email: 'Email',
    push: 'Push',
};

const PRIORITY_LABELS = {
    high: 'High',
    normal: 'Normal',
    low: 'Low',
};

const state = {
    page: 1,
    perPage: 25,
    status: '',
    channel: '',
    lastResult: null,
    pollHandles: { health: null, metrics: null, list: null },
};

async function api(path, params = {}) {
    const url = new URL(`${API_BASE}${path}`, window.location.origin);
    Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) {
            url.searchParams.set(k, v);
        }
    });

    const headers = { Accept: 'application/json' };
    if (API_KEY) {
        headers['X-Api-Key'] = API_KEY;
    }

    const res = await fetch(url.toString(), { headers });
    const body = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, body };
}

function formatNumber(value) {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'number') {
        if (Number.isInteger(value)) return value.toLocaleString('en-US');
        return value.toLocaleString('en-US', { maximumFractionDigits: 2 });
    }
    return String(value);
}

function formatRelative(iso) {
    if (!iso) return '—';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '—';
    const diffMs = Date.now() - date.getTime();
    const sec = Math.round(diffMs / 1000);
    if (sec < 5) return 'just now';
    if (sec < 60) return `${sec}s ago`;
    const min = Math.round(sec / 60);
    if (min < 60) return `${min}m ago`;
    const hr = Math.round(min / 60);
    if (hr < 24) return `${hr}h ago`;
    const day = Math.round(hr / 24);
    if (day < 30) return `${day}d ago`;
    return date.toLocaleDateString();
}

function formatDateTime(iso) {
    if (!iso) return '—';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString();
}

function setStatusPill(status, message) {
    const pill = document.getElementById('status-pill');
    const dot = pill.querySelector('.status-dot');
    const text = pill.querySelector('.status-text');

    dot.className = 'status-dot';
    if (status === 'ok') dot.classList.add('status-dot--ok');
    else if (status === 'bad') dot.classList.add('status-dot--bad');
    else dot.classList.add('status-dot--idle');

    text.textContent = message;
}

function statusBadge(status) {
    const label = STATUS_LABELS[status] ?? status;
    const variant = (status === 'sent')
        ? 'badge--solid'
        : (status === 'failed' || status === 'dead_lettered')
            ? 'badge--outline'
            : 'badge--neutral';
    return `<span class="badge ${variant}">${label}</span>`;
}

function channelBadge(channel) {
    return `<span class="badge badge--neutral">${CHANNEL_LABELS[channel] ?? channel}</span>`;
}

function priorityBadge(priority) {
    const variant = priority === 'high' ? 'badge--solid' : 'badge--neutral';
    return `<span class="badge ${variant}">${PRIORITY_LABELS[priority] ?? priority}</span>`;
}

async function refreshHealth() {
    const grid = document.getElementById('health-grid');
    const meta = document.getElementById('health-meta');
    const tpl = document.getElementById('health-card-template');

    const { ok, status, body } = await api('/health');

    if (!ok && status !== 503) {
        setStatusPill('bad', status === 401 ? 'Unauthorized' : 'API unreachable');
        grid.innerHTML = `<div class="col-span-full rounded-lg border border-black/10 p-5 text-sm text-black/60">
            Unable to load health (HTTP ${status || '—'}).
        </div>`;
        meta.textContent = '—';
        return;
    }

    const data = body?.data ?? {};
    const metaInfo = body?.meta ?? {};
    const checks = data.checks ?? {};

    setStatusPill(data.status === 'healthy' ? 'ok' : 'bad', data.status === 'healthy' ? 'All systems normal' : 'Degraded');
    meta.textContent = metaInfo.time ? `Updated ${formatRelative(metaInfo.time)}` : '—';

    grid.innerHTML = '';

    if (!tpl) {
        console.warn('Missing #health-card-template; cannot render health cards.');
        return;
    }

    Object.entries(checks).forEach(([name, value]) => {
        const node = tpl.content.firstElementChild.cloneNode(true);
        node.querySelector('[data-name]').textContent = name;
        node.querySelector('[data-status]').textContent = value.ok ? 'Healthy' : 'Failing';
        node.querySelector('[data-message]').textContent = value.message
            ? value.message
            : value.ok ? 'Responding within thresholds.' : 'Check failed.';
        const dot = node.querySelector('[data-dot]');
        dot.classList.add(value.ok ? 'status-dot--ok' : 'status-dot--bad');
        grid.appendChild(node);
    });
}

async function refreshMetrics() {
    const metaEl = document.getElementById('metrics-meta');
    const windowEl = document.getElementById('metrics-window');
    const queueEl = document.getElementById('queue-depth');
    const totalsEl = document.getElementById('totals-grid');
    const latencyEl = document.getElementById('latency-grid');
    const byChannelEl = document.getElementById('by-channel-tbody');

    const { ok, body } = await api('/metrics');
    if (!ok) {
        const unavailable = `<div class="col-span-full text-sm text-black/40">Unavailable.</div>`;
        queueEl.innerHTML = unavailable;
        totalsEl.innerHTML = unavailable;
        latencyEl.innerHTML = unavailable;
        byChannelEl.innerHTML = `<tr><td colspan="6" class="px-5 py-6 text-center text-sm text-black/40">Unavailable.</td></tr>`;
        return;
    }

    const data = body?.data ?? {};
    const metaInfo = body?.meta ?? {};
    const depth = data.queue_depth ?? {};
    const realtime = data.realtime ?? {};
    const totals = realtime.totals ?? {};
    const latency = realtime.latency_ms ?? {};
    const byChannel = realtime.by_channel ?? {};

    if (windowEl) {
        const seconds = realtime.window_seconds ?? 300;
        const minutes = Math.max(1, Math.round(seconds / 60));
        windowEl.textContent = `(last ${minutes} min)`;
    }
    metaEl.textContent = metaInfo.generated_at
        ? `Updated ${formatRelative(metaInfo.generated_at)}`
        : '—';

    queueEl.innerHTML = ['high', 'normal', 'low']
        .map((priority) => statTile(PRIORITY_LABELS[priority], formatNumber(depth[priority] ?? 0)))
        .join('');

    totalsEl.innerHTML = [
        statTile('Success', formatNumber(totals.success ?? 0)),
        statTile('Failure', formatNumber(totals.failure ?? 0)),
        statTile('Success rate', formatRate(totals.success_rate)),
        statTile('Failure rate', formatRate(totals.failure_rate)),
    ].join('');

    latencyEl.innerHTML = [
        statTile('p50', formatLatency(latency.p50)),
        statTile('p95', formatLatency(latency.p95)),
        statTile('p99', formatLatency(latency.p99)),
    ].join('');

    const channels = Object.keys(byChannel);
    if (channels.length === 0) {
        byChannelEl.innerHTML = `<tr><td colspan="6" class="px-5 py-6 text-center text-sm text-black/40">No channel data yet.</td></tr>`;
    } else {
        byChannelEl.innerHTML = channels.map((c) => {
            const cdata = byChannel[c] ?? {};
            const lat = cdata.latency_ms ?? {};
            return `
                <tr>
                    <td class="px-5 py-2.5">${channelBadge(c)}</td>
                    <td class="px-5 py-2.5 nums text-right">${formatNumber(cdata.success ?? 0)}</td>
                    <td class="px-5 py-2.5 nums text-right">${formatNumber(cdata.failure ?? 0)}</td>
                    <td class="px-5 py-2.5 nums text-right">${formatLatency(lat.p50)}</td>
                    <td class="px-5 py-2.5 nums text-right">${formatLatency(lat.p95)}</td>
                    <td class="px-5 py-2.5 nums text-right">${formatLatency(lat.p99)}</td>
                </tr>
            `;
        }).join('');
    }
}

function statTile(label, value) {
    return `
        <div class="rounded-md border border-black/5 bg-black/[0.02] px-3 py-3 min-w-0">
            <div class="text-[11px] uppercase tracking-wider text-black/50 truncate" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
            <div class="nums mt-1 text-lg sm:text-xl lg:text-2xl font-semibold tracking-tight truncate">${value}</div>
        </div>
    `;
}

function formatLatency(ms) {
    if (ms === null || ms === undefined) return '<span class="text-black/40">—</span>';
    return `${formatNumber(ms)}<span class="text-[11px] text-black/40 font-normal ml-0.5">ms</span>`;
}

function formatRate(rate) {
    if (rate === null || rate === undefined) return '<span class="text-black/40">—</span>';
    const pct = (Number(rate) * 100).toFixed(1);
    return `${pct}<span class="text-[11px] text-black/40 font-normal ml-0.5">%</span>`;
}

async function refreshList() {
    const tbody = document.getElementById('notifications-tbody');
    const summary = document.getElementById('pagination-summary');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    const { ok, status, body } = await api('/notifications', {
        page: state.page,
        per_page: state.perPage,
        status: state.status,
        channel: state.channel,
    });

    if (!ok) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-12 text-center text-sm text-black/40">
            Unable to load notifications (HTTP ${status}).
        </td></tr>`;
        summary.textContent = '—';
        prevBtn.disabled = true;
        nextBtn.disabled = true;
        return;
    }

    const items = body?.data ?? [];
    const meta = body?.meta ?? {};
    state.lastResult = { items, meta };

    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-12 text-center text-sm text-black/40">
            No notifications match the current filters.
        </td></tr>`;
    } else {
        tbody.innerHTML = items.map((n) => `
            <tr class="hover:bg-black/[0.02] cursor-pointer transition" data-id="${n.id}">
                <td class="px-4 py-3">${statusBadge(n.status)}</td>
                <td class="px-4 py-3">${channelBadge(n.channel)}</td>
                <td class="px-4 py-3 nums text-black/80">${escapeHtml(truncate(n.recipient, 40))}</td>
                <td class="px-4 py-3">${priorityBadge(n.priority)}</td>
                <td class="px-4 py-3 nums text-black/70">${n.attempts ?? 0}</td>
                <td class="px-4 py-3 text-black/60 nums" title="${escapeHtml(formatDateTime(n.created_at))}">${formatRelative(n.created_at)}</td>
                <td class="px-4 py-3 text-right">
                    <span class="text-xs text-black/40 group-hover:text-black">View</span>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('tr[data-id]').forEach((row) => {
            row.addEventListener('click', () => openDrawer(row.dataset.id));
        });
    }

    const start = items.length === 0 ? 0 : ((meta.current_page - 1) * meta.per_page) + 1;
    const end = items.length === 0 ? 0 : start + items.length - 1;
    summary.textContent = `${start}–${end} of ${formatNumber(meta.total ?? 0)} · page ${meta.current_page ?? 1} of ${meta.last_page ?? 1}`;

    prevBtn.disabled = (meta.current_page ?? 1) <= 1;
    nextBtn.disabled = (meta.current_page ?? 1) >= (meta.last_page ?? 1);
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function truncate(value, length) {
    if (!value) return '';
    return value.length > length ? value.slice(0, length - 1) + '…' : value;
}

async function openDrawer(id) {
    const drawer = document.getElementById('drawer');
    const title = document.getElementById('drawer-title');
    const dbody = document.getElementById('drawer-body');

    drawer.dataset.open = 'true';
    drawer.setAttribute('aria-hidden', 'false');
    title.textContent = id;
    dbody.innerHTML = `<p class="text-sm text-black/50">Loading…</p>`;

    const { ok, status, body } = await api(`/notifications/${id}`);
    if (!ok) {
        dbody.innerHTML = `<p class="text-sm text-black/60">Unable to load notification (HTTP ${status}).</p>`;
        return;
    }

    const n = body?.data ?? {};
    const fields = [
        ['Status', statusBadge(n.status)],
        ['Channel', channelBadge(n.channel)],
        ['Priority', priorityBadge(n.priority)],
        ['Recipient', escapeHtml(n.recipient)],
        ['Attempts', `<span class="nums">${n.attempts ?? 0}</span>`],
        ['Template', escapeHtml(n.template_id ?? '—')],
        ['Batch', escapeHtml(n.batch_id ?? '—')],
        ['Correlation', `<span class="nums">${escapeHtml(n.correlation_id ?? '—')}</span>`],
        ['Provider message', `<span class="nums">${escapeHtml(n.provider_message_id ?? '—')}</span>`],
        ['Scheduled', formatDateTime(n.scheduled_at)],
        ['Dispatched', formatDateTime(n.dispatched_at)],
        ['Delivered', formatDateTime(n.delivered_at)],
        ['Cancelled', formatDateTime(n.cancelled_at)],
        ['Created', formatDateTime(n.created_at)],
        ['Updated', formatDateTime(n.updated_at)],
    ];

    const attempts = Array.isArray(n.attempts_log) ? n.attempts_log : [];
    const attemptsBlock = attempts.length === 0
        ? `<p class="text-sm text-black/40">No attempts recorded yet.</p>`
        : `<div class="space-y-3">${attempts.map((a) => `
            <div class="rounded-md border border-black/10 p-3">
                <div class="flex items-center justify-between">
                    <span class="badge badge--neutral">Attempt ${escapeHtml(a.attempt_number ?? '—')}</span>
                    <span class="text-xs text-black/50 nums" title="${escapeHtml(formatDateTime(a.created_at))}">${formatRelative(a.created_at)}</span>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <div><span class="text-black/40">Status code:</span> <span class="nums">${escapeHtml(a.response_status ?? '—')}</span></div>
                    <div><span class="text-black/40">Latency:</span> <span class="nums">${a.latency_ms != null ? a.latency_ms + ' ms' : '—'}</span></div>
                </div>
                ${a.error ? `<div class="mt-2 text-xs text-black/70">${escapeHtml(a.error)}</div>` : ''}
            </div>
        `).join('')}</div>`;

    dbody.innerHTML = `
        <section>
            <h4 class="text-xs uppercase tracking-wider text-black/50 mb-2">Overview</h4>
            <div>${fields.map(([k, v]) => `
                <div class="kv"><div class="kv__key">${k}</div><div class="kv__value">${v ?? '—'}</div></div>
            `).join('')}</div>
        </section>

        <section>
            <h4 class="text-xs uppercase tracking-wider text-black/50 mb-2">Content</h4>
            <pre class="code-block">${escapeHtml(n.content ?? '')}</pre>
        </section>

        ${n.last_error ? `
        <section>
            <h4 class="text-xs uppercase tracking-wider text-black/50 mb-2">Last error</h4>
            <pre class="code-block">${escapeHtml(n.last_error)}</pre>
        </section>` : ''}

        ${n.payload && Object.keys(n.payload).length > 0 ? `
        <section>
            <h4 class="text-xs uppercase tracking-wider text-black/50 mb-2">Payload</h4>
            <pre class="code-block">${escapeHtml(JSON.stringify(n.payload, null, 2))}</pre>
        </section>` : ''}

        <section>
            <h4 class="text-xs uppercase tracking-wider text-black/50 mb-2">Attempts</h4>
            ${attemptsBlock}
        </section>
    `;
}

function closeDrawer() {
    const drawer = document.getElementById('drawer');
    drawer.dataset.open = 'false';
    drawer.setAttribute('aria-hidden', 'true');
}

function refreshAll() {
    refreshHealth();
    refreshMetrics();
    refreshList();
}

function bindUi() {
    document.getElementById('refresh-btn').addEventListener('click', refreshAll);

    document.getElementById('filter-status').addEventListener('change', (e) => {
        state.status = e.target.value;
        state.page = 1;
        refreshList();
    });

    document.getElementById('filter-channel').addEventListener('change', (e) => {
        state.channel = e.target.value;
        state.page = 1;
        refreshList();
    });

    document.getElementById('filter-per-page').addEventListener('change', (e) => {
        state.perPage = parseInt(e.target.value, 10) || 25;
        state.page = 1;
        refreshList();
    });

    document.getElementById('prev-page').addEventListener('click', () => {
        if (state.page > 1) { state.page -= 1; refreshList(); }
    });

    document.getElementById('next-page').addEventListener('click', () => {
        const last = state.lastResult?.meta?.last_page ?? 1;
        if (state.page < last) { state.page += 1; refreshList(); }
    });

    document.querySelectorAll('[data-close]').forEach((el) => {
        el.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDrawer();
    });
}

function startPolling() {
    state.pollHandles.health = setInterval(refreshHealth, REFRESH_INTERVAL);
    state.pollHandles.metrics = setInterval(refreshMetrics, REFRESH_INTERVAL);
    state.pollHandles.list = setInterval(refreshList, REFRESH_INTERVAL);
}

document.addEventListener('DOMContentLoaded', () => {
    bindUi();
    refreshAll();
    startPolling();
});
