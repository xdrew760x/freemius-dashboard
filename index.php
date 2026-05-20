<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freemius Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
        .tab-active { border-bottom: 2px solid #3b82f6; color: #3b82f6; font-weight: 600; }
        .fade-in { animation: fadeIn 0.2s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .spinner { border: 3px solid #e5e7eb; border-top-color: #3b82f6; border-radius: 50%; width: 24px; height: 24px; animation: spin 0.6s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gray-950 text-gray-200 min-h-screen">

<!-- Header -->
<header class="bg-gray-900 border-b border-gray-800 px-6 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <h1 class="text-xl font-bold text-white">Freemius <span class="text-blue-400">Dashboard</span></h1>
        <span class="text-xs text-gray-500">Product #<?= (require __DIR__.'/config.php')['product_id'] ?></span>
    </div>
</header>

<!-- Tabs -->
<nav class="bg-gray-900 border-b border-gray-800">
    <div class="max-w-7xl mx-auto flex gap-0">
        <button onclick="switchTab('users')" id="tab-users" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition tab-active">Users</button>
        <button onclick="switchTab('licenses')" id="tab-licenses" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Licenses</button>
        <button onclick="switchTab('subscriptions')" id="tab-subscriptions" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Subscriptions</button>
        <button onclick="switchTab('installs')" id="tab-installs" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Installs</button>
    </div>
</nav>

<!-- Main Content -->
<main class="max-w-7xl mx-auto p-6">

    <!-- Toolbar -->
    <div class="flex items-center gap-4 mb-6">
        <input type="text" id="searchInput" placeholder="Search..." class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm w-64 focus:outline-none focus:border-blue-500" onkeydown="if(event.key==='Enter') loadCurrentTab()">
        <select id="filterSelect" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500" onchange="onFilterChange()">
            <option value="">All</option>
        </select>
        <select id="perPageSelect" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500" onchange="changePerPage()" title="Results per page">
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
            <option value="100">100 / page</option>
            <option value="200">200 / page</option>
        </select>
        <button onclick="loadCurrentTab()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">Load</button>
        <div id="installsTotalBadge" class="hidden ml-auto text-sm bg-gray-800 border border-gray-700 rounded-lg px-3 py-2">
            <span class="text-gray-500">Total sites:</span>
            <span id="installsTotalValue" class="text-white font-semibold ml-1">—</span>
            <button onclick="refreshInstallsTotal()" title="Refresh" class="ml-2 text-gray-500 hover:text-blue-400">↻</button>
        </div>
        <div id="statusMsg" class="text-sm text-gray-400 ml-4"></div>
    </div>

    <!-- Data Table -->
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead id="tableHead" class="bg-gray-800 text-gray-400 text-left">
                    <tr><th class="px-4 py-3">Loading...</th></tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-gray-800">
                    <tr><td class="px-4 py-8 text-center text-gray-500">Click "Load" to fetch data</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between mt-4">
        <button onclick="prevPage()" id="prevBtn" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg text-sm transition disabled:opacity-30" disabled>Previous</button>
        <span id="pageInfo" class="text-sm text-gray-500">Page 1</span>
        <button onclick="nextPage()" id="nextBtn" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg text-sm transition disabled:opacity-30" disabled>Next</button>
    </div>
</main>

<!-- Confirm Modal -->
<div id="confirmModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-white mb-2">Confirm Action</h3>
        <p id="confirmMsg" class="text-gray-400 mb-6"></p>
        <div class="flex justify-end gap-3">
            <button onclick="closeModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition">Cancel</button>
            <button id="confirmBtn" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm text-white transition">Delete</button>
        </div>
    </div>
</div>

<!-- Detail Drawer -->
<div id="detailDrawer" class="fixed inset-y-0 right-0 w-full max-w-lg bg-gray-900 border-l border-gray-800 shadow-2xl z-40 translate-x-full transition-transform duration-300">
    <div class="flex items-center justify-between p-4 border-b border-gray-800">
        <h3 id="drawerTitle" class="text-lg font-semibold text-white">Details</h3>
        <button onclick="closeDrawer()" class="text-gray-400 hover:text-white text-xl">&times;</button>
    </div>
    <div id="drawerContent" class="p-4 overflow-y-auto" style="height: calc(100% - 60px);">
    </div>
</div>

<script>
const perPageByTab = { users: 25, licenses: 25, subscriptions: 25, installs: 100 };
let currentTab = 'users';
let currentOffset = 0;
let lastResultCount = 0;
let installsTotalCache = null;
const ipCache = {}; // host → ip (or null if unresolved)

const filters = {
    users:         [{v:'',l:'All'},{v:'paid',l:'Paid'},{v:'paying',l:'Paying'},{v:'never_paid',l:'Never Paid'},{v:'beta',l:'Beta'}],
    licenses:      [{v:'',l:'All'},{v:'active',l:'Active'},{v:'cancelled',l:'Cancelled'},{v:'expired',l:'Expired'},{v:'abandoned',l:'Abandoned'}],
    subscriptions: [{v:'',l:'All'},{v:'active',l:'Active'},{v:'cancelled',l:'Cancelled'}],
    installs:      [{v:'',l:'All'}],
};

const headers = {
    users:         ['ID','Email','First','Last','Verified','Created','Actions'],
    licenses:      ['ID','Key','Plan ID','Quota','Activations','Expiration','Created','Actions'],
    subscriptions: ['ID','License ID','Plan ID','Gateway','Amount','Status','Next Payment','Actions'],
    installs:      ['ID','User ID','URL','IP','Version','License ID','Plan ID','Active','Actions'],
};

// Sort key per column (null = not sortable). '__ip' = resolved-IP pseudo-field.
const sortKeys = {
    users:         ['id','email','first','last','is_verified','created',null],
    licenses:      ['id','secret_key','plan_id','quota','activated','expiration','created',null],
    subscriptions: ['id','license_id','plan_id','gateway','total_gross','cancel_date','next_payment',null],
    installs:      ['id','user_id','url','__ip','version','license_id','plan_id','is_active',null],
};

let lastItems = [];
let sortState = null; // { col, dir: 'asc'|'desc' } or null

function switchTab(tab) {
    currentTab = tab;
    currentOffset = 0;
    sortState = null;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-active'));
    document.getElementById('tab-' + tab).classList.add('tab-active');

    // Update filters. Installs has no useful server-side filter, so we hijack
    // this dropdown for client-side IP filtering — options are populated once
    // the install URLs resolve.
    const sel = document.getElementById('filterSelect');
    sel.innerHTML = '';
    if (tab === 'installs') {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'All IPs';
        sel.appendChild(opt);
    } else {
        filters[tab].forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.v;
            opt.textContent = f.l;
            sel.appendChild(opt);
        });
    }

    // Update search placeholder
    const search = document.getElementById('searchInput');
    search.value = '';
    if (tab === 'users') search.placeholder = 'Search by name, email, or ID...';
    else if (tab === 'licenses') search.placeholder = 'Search by ID or key...';
    else search.placeholder = 'Search...';

    // Sync per-page select with this tab's remembered preference
    document.getElementById('perPageSelect').value = perPageByTab[tab];

    // Show the total-sites badge only on the installs tab
    const badge = document.getElementById('installsTotalBadge');
    if (tab === 'installs') {
        badge.classList.remove('hidden');
        if (installsTotalCache === null) refreshInstallsTotal();
        else document.getElementById('installsTotalValue').textContent = installsTotalCache;
    } else {
        badge.classList.add('hidden');
    }

    loadCurrentTab();
}

function refreshInstallsTotal() {
    const valEl = document.getElementById('installsTotalValue');
    valEl.textContent = '…';
    fetch('api.php?action=count_installs')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                installsTotalCache = res.data.total;
                valEl.textContent = installsTotalCache;
            } else {
                valEl.textContent = '?';
            }
        })
        .catch(() => { valEl.textContent = '?'; });
}

function changePerPage() {
    const val = parseInt(document.getElementById('perPageSelect').value, 10);
    perPageByTab[currentTab] = val;
    currentOffset = 0;
    loadCurrentTab();
}

function loadCurrentTab() {
    const search = document.getElementById('searchInput').value;
    const filter = document.getElementById('filterSelect').value;
    const perPage = perPageByTab[currentTab];

    let params = `action=list_${currentTab}&count=${perPage}&offset=${currentOffset}`;
    if (filter) params += `&filter=${encodeURIComponent(filter)}`;
    if (search) params += `&search=${encodeURIComponent(search)}`;

    setStatus('Loading...');
    setTableLoading();

    fetch(`api.php?${params}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                setStatus('Error: ' + (res.error || JSON.stringify(res.data)));
                return;
            }
            renderTable(res.data);
        })
        .catch(err => setStatus('Fetch error: ' + err.message));
}

function renderTable(data) {
    const key = currentTab;
    lastItems = data?.[key] || [];
    lastResultCount = lastItems.length;

    renderTableHeaders();

    if (!lastItems.length) {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="99" class="px-4 py-8 text-center text-gray-500">No results</td></tr>';
        setStatus('0 results');
        updatePagination();
        return;
    }

    applyViewAndRender();
    setStatus(`${lastItems.length} results (offset ${currentOffset})`);
    updatePagination();

    if (currentTab === 'installs') populateInstallIps(lastItems);
}

function renderTableHeaders() {
    const head = document.getElementById('tableHead');
    const keys = sortKeys[currentTab];
    head.innerHTML = '<tr>' + headers[currentTab].map((h, i) => {
        const k = keys[i];
        if (!k) return `<th class="px-4 py-3 font-medium">${h}</th>`;
        const arrow = sortState && sortState.col === k
            ? (sortState.dir === 'asc' ? ' <span class="text-blue-400">↑</span>' : ' <span class="text-blue-400">↓</span>')
            : ' <span class="text-gray-700">↕</span>';
        return `<th class="px-4 py-3 font-medium cursor-pointer select-none hover:text-blue-400" onclick="onHeaderClick(${i})">${h}${arrow}</th>`;
    }).join('') + '</tr>';
}

function onHeaderClick(colIdx) {
    const key = sortKeys[currentTab][colIdx];
    if (!key) return;
    if (sortState && sortState.col === key) {
        sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
    } else {
        sortState = { col: key, dir: 'asc' };
    }
    renderTableHeaders();
    applyViewAndRender();
}

function onFilterChange() {
    if (currentTab === 'installs') {
        applyViewAndRender();
    } else {
        loadCurrentTab();
    }
}

function applyViewAndRender() {
    let items = lastItems.slice();

    // Client-side IP filter (installs only)
    if (currentTab === 'installs') {
        const ipFilter = document.getElementById('filterSelect').value;
        if (ipFilter) {
            items = items.filter(it => ipCache[hostOf(it.url)] === ipFilter);
        }
    }

    // Sort
    if (sortState) {
        const dir = sortState.dir === 'asc' ? 1 : -1;
        const col = sortState.col;
        items.sort((a, b) => compareValues(sortValue(a, col), sortValue(b, col)) * dir);
    }

    const body = document.getElementById('tableBody');
    if (!items.length) {
        body.innerHTML = '<tr><td colspan="99" class="px-4 py-8 text-center text-gray-500">No results</td></tr>';
        return;
    }
    body.innerHTML = items.map(item => renderRow(item)).join('');
}

function hostOf(url) {
    try { return new URL(url).hostname; } catch (_) { return ''; }
}

function sortValue(item, col) {
    if (col === '__ip') return ipCache[hostOf(item.url)] || '';
    return item[col];
}

function compareValues(a, b) {
    const aMissing = a == null || a === '';
    const bMissing = b == null || b === '';
    if (aMissing && bMissing) return 0;
    if (aMissing) return 1;  // missing sorts last (in asc)
    if (bMissing) return -1;

    // IPv4 numeric ordering
    const ipRe = /^\d+\.\d+\.\d+\.\d+$/;
    if (ipRe.test(a) && ipRe.test(b)) {
        const ap = a.split('.').map(Number);
        const bp = b.split('.').map(Number);
        for (let i = 0; i < 4; i++) if (ap[i] !== bp[i]) return ap[i] - bp[i];
        return 0;
    }

    // Numeric compare when both sides look numeric
    const na = Number(a), nb = Number(b);
    if (!isNaN(na) && !isNaN(nb) && `${a}`.trim() !== '' && `${b}`.trim() !== '') {
        return na - nb;
    }
    return String(a).localeCompare(String(b));
}

function populateInstallFilterOptions() {
    if (currentTab !== 'installs') return;
    const sel = document.getElementById('filterSelect');
    const current = sel.value;
    const ips = new Set();
    lastItems.forEach(it => {
        const ip = ipCache[hostOf(it.url)];
        if (ip) ips.add(ip);
    });
    const sorted = [...ips].sort(compareValues);
    sel.innerHTML = '<option value="">All IPs</option>' +
        sorted.map(ip => `<option value="${ip}">${ip}</option>`).join('');
    if (sorted.includes(current)) sel.value = current;
}

function populateInstallIps(items) {
    // Extract host + tag each install with its host for quick cell lookup later
    const entries = items.map(item => {
        let host = null;
        try { host = new URL(item.url).hostname; } catch (_) {}
        return { id: item.id, host };
    });

    // Fill known cache hits immediately; collect unknowns for the backend
    const toFetch = new Set();
    entries.forEach(({ id, host }) => {
        const cell = document.querySelector(`[data-install-ip="${id}"]`);
        if (!cell) return;
        if (!host) { cell.textContent = '—'; return; }
        if (host in ipCache) {
            cell.textContent = ipCache[host] || '—';
        } else {
            toFetch.add(host);
        }
    });

    if (!toFetch.size) return;

    // Chunk into parallel requests of ~10 hosts each so the page doesn't block
    // on a slow DNS lookup — each PHP request resolves its chunk sequentially,
    // but chunks run concurrently across separate HTTP requests.
    const hosts = [...toFetch];
    const chunkSize = 10;
    for (let i = 0; i < hosts.length; i += chunkSize) {
        const chunk = hosts.slice(i, i + chunkSize);
        const form = new FormData();
        chunk.forEach(h => form.append('hosts[]', h));

        fetch('api.php?action=resolve_ips', { method: 'POST', body: form })
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                Object.assign(ipCache, res.data);
                entries.forEach(({ id, host }) => {
                    if (!host || !(host in res.data)) return;
                    const cell = document.querySelector(`[data-install-ip="${id}"]`);
                    if (cell) cell.textContent = res.data[host] || '—';
                });
                populateInstallFilterOptions();
                // Re-apply sort if the user is sorting by IP, since new IPs changed the order
                if (sortState && sortState.col === '__ip') applyViewAndRender();
            })
            .catch(() => {
                chunk.forEach(h => {
                    entries.forEach(({ id, host }) => {
                        if (host !== h) return;
                        const cell = document.querySelector(`[data-install-ip="${id}"]`);
                        if (cell) cell.textContent = '?';
                    });
                });
            });
    }
}

function renderRow(item) {
    switch (currentTab) {
        case 'users':
            return `<tr class="hover:bg-gray-800/50 transition">
                <td class="px-4 py-3"><button onclick="viewUser(${item.id})" class="text-blue-400 hover:underline">${item.id}</button></td>
                <td class="px-4 py-3">${esc(item.email || '')}</td>
                <td class="px-4 py-3">${esc(item.first || '')}</td>
                <td class="px-4 py-3">${esc(item.last || '')}</td>
                <td class="px-4 py-3">${item.is_verified ? '<span class="text-green-400">Yes</span>' : '<span class="text-red-400">No</span>'}</td>
                <td class="px-4 py-3 text-gray-500">${shortDate(item.created)}</td>
                <td class="px-4 py-3"><button onclick="viewUser(${item.id})" class="text-xs bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded transition">View</button></td>
            </tr>`;

        case 'licenses':
            return `<tr class="hover:bg-gray-800/50 transition">
                <td class="px-4 py-3">${item.id}</td>
                <td class="px-4 py-3 font-mono text-xs">${esc((item.secret_key || '').substring(0, 20))}...</td>
                <td class="px-4 py-3">${item.plan_id || '-'}</td>
                <td class="px-4 py-3">${item.quota || 'Unlimited'}</td>
                <td class="px-4 py-3">${item.activated ?? '-'}/${item.quota || '&infin;'}</td>
                <td class="px-4 py-3 text-gray-500">${item.expiration ? shortDate(item.expiration) : 'Never'}</td>
                <td class="px-4 py-3 text-gray-500">${shortDate(item.created)}</td>
                <td class="px-4 py-3 flex gap-2">
                    <button onclick="cancelLicenseSub(${item.id})" class="text-xs bg-yellow-700 hover:bg-yellow-600 px-3 py-1 rounded transition" title="Cancel subscription on this license">Cancel Sub</button>
                    <button onclick="deleteLicense(${item.id})" class="text-xs bg-red-700 hover:bg-red-600 px-3 py-1 rounded transition">Delete</button>
                </td>
            </tr>`;

        case 'subscriptions':
            const isActive = !item.cancel_date;
            return `<tr class="hover:bg-gray-800/50 transition">
                <td class="px-4 py-3">${item.id}</td>
                <td class="px-4 py-3">${item.license_id || '-'}</td>
                <td class="px-4 py-3">${item.plan_id || '-'}</td>
                <td class="px-4 py-3">${esc(item.gateway || '-')}</td>
                <td class="px-4 py-3">$${item.total_gross || '0'}</td>
                <td class="px-4 py-3">${isActive ? '<span class="text-green-400">Active</span>' : '<span class="text-red-400">Cancelled</span>'}</td>
                <td class="px-4 py-3 text-gray-500">${item.next_payment ? shortDate(item.next_payment) : '-'}</td>
                <td class="px-4 py-3">
                    ${isActive ? `<button onclick="cancelSubscription(${item.id})" class="text-xs bg-red-700 hover:bg-red-600 px-3 py-1 rounded transition">Cancel</button>` : '<span class="text-gray-600 text-xs">-</span>'}
                </td>
            </tr>`;

        case 'installs':
            const cachedHost = hostOf(item.url);
            const cachedIp = cachedHost in ipCache ? (ipCache[cachedHost] || '—') : '…';
            return `<tr class="hover:bg-gray-800/50 transition">
                <td class="px-4 py-3">${item.id}</td>
                <td class="px-4 py-3">${item.user_id || '-'}</td>
                <td class="px-4 py-3 text-xs max-w-xs truncate">${esc(item.url || '-')}</td>
                <td data-install-ip="${item.id}" class="px-4 py-3 font-mono text-xs text-gray-500">${cachedIp}</td>
                <td class="px-4 py-3">${esc(item.version || '-')}</td>
                <td class="px-4 py-3">${item.license_id || '-'}</td>
                <td class="px-4 py-3">${item.plan_id || '-'}</td>
                <td class="px-4 py-3">${item.is_active ? '<span class="text-green-400">Yes</span>' : '<span class="text-red-400">No</span>'}</td>
                <td class="px-4 py-3">
                    <button onclick="deleteInstall(${item.id})" class="text-xs bg-red-700 hover:bg-red-600 px-3 py-1 rounded transition">Delete</button>
                </td>
            </tr>`;
    }
}

// ── Actions ─────────────────────────────────────────────────────

function deleteLicense(id) {
    showConfirm(`Delete license #${id}? This cannot be undone.`, () => {
        apiAction(`action=delete_license&license_id=${id}`, `License #${id}`);
    });
}

function cancelSubscription(id) {
    showConfirm(`Cancel subscription #${id}? It will not renew.`, () => {
        apiAction(`action=cancel_subscription&subscription_id=${id}`, `Subscription #${id}`);
    });
}

function cancelLicenseSub(licenseId) {
    showConfirm(`Cancel the active subscription on license #${licenseId}?`, () => {
        apiAction(`action=cancel_license_subscription&license_id=${licenseId}`, `Subscription on license #${licenseId}`);
    });
}

function deleteInstall(id) {
    showConfirm(`Delete install #${id}?`, () => {
        apiAction(`action=delete_install&install_id=${id}`, `Install #${id}`);
    });
}

function apiAction(params, label) {
    setStatus(`Processing ${label}...`);
    fetch(`api.php?${params}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                setStatus(`${label} — done.`);
                loadCurrentTab();
            } else {
                const msg = res.error || res.data?.error?.message || JSON.stringify(res.data);
                setStatus(`Error on ${label}: ${msg}`);
            }
        })
        .catch(err => setStatus('Fetch error: ' + err.message));
}

// ── User Detail Drawer ──────────────────────────────────────────

function viewUser(uid) {
    const drawer = document.getElementById('detailDrawer');
    const content = document.getElementById('drawerContent');
    document.getElementById('drawerTitle').textContent = `User #${uid}`;
    content.innerHTML = '<div class="flex justify-center py-12"><div class="spinner"></div></div>';
    drawer.classList.remove('translate-x-full');

    Promise.all([
        fetch(`api.php?action=get_user&user_id=${uid}`).then(r => r.json()),
        fetch(`api.php?action=user_licenses&user_id=${uid}`).then(r => r.json()),
        fetch(`api.php?action=user_installs&user_id=${uid}`).then(r => r.json()),
        fetch(`api.php?action=user_subscriptions&user_id=${uid}`).then(r => r.json()),
    ]).then(([userRes, licRes, instRes, subRes]) => {
        let html = '';
        if (userRes.success && userRes.data) {
            const u = userRes.data;
            html += `<div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-400 uppercase mb-2">User Info</h4>
                <div class="bg-gray-800 rounded-lg p-4 space-y-2 text-sm">
                    <div><span class="text-gray-500">ID:</span> ${u.id}</div>
                    <div><span class="text-gray-500">Email:</span> ${esc(u.email || '')}</div>
                    <div><span class="text-gray-500">Name:</span> ${esc((u.first || '') + ' ' + (u.last || ''))}</div>
                    <div><span class="text-gray-500">Verified:</span> ${u.is_verified ? 'Yes' : 'No'}</div>
                    <div><span class="text-gray-500">IP:</span> ${esc(u.ip || '-')}</div>
                    <div><span class="text-gray-500">Created:</span> ${u.created || '-'}</div>
                </div>
            </div>`;
        }

        // Licenses
        const lics = licRes.data?.licenses || [];
        html += `<div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-400 uppercase mb-2">Licenses (${lics.length})</h4>`;
        if (lics.length) {
            html += '<div class="space-y-2">';
            lics.forEach(l => {
                html += `<div class="bg-gray-800 rounded-lg p-3 text-sm flex justify-between items-center">
                    <div>
                        <span class="text-gray-500">#${l.id}</span>
                        <span class="ml-2 font-mono text-xs">${esc((l.secret_key || '').substring(0, 16))}...</span>
                        <span class="ml-2 text-gray-500">Exp: ${l.expiration ? shortDate(l.expiration) : 'Never'}</span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="cancelLicenseSub(${l.id})" class="text-xs bg-yellow-700 hover:bg-yellow-600 px-2 py-1 rounded">Cancel Sub</button>
                        <button onclick="deleteLicense(${l.id})" class="text-xs bg-red-700 hover:bg-red-600 px-2 py-1 rounded">Delete</button>
                    </div>
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-gray-600 text-sm">No licenses</p>';
        }
        html += '</div>';

        // Subscriptions
        const subs = subRes.data?.subscriptions || [];
        html += `<div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-400 uppercase mb-2">Subscriptions (${subs.length})</h4>`;
        if (subs.length) {
            html += '<div class="space-y-2">';
            subs.forEach(s => {
                const active = !s.cancel_date;
                html += `<div class="bg-gray-800 rounded-lg p-3 text-sm flex justify-between items-center">
                    <div>
                        <span class="text-gray-500">#${s.id}</span>
                        <span class="ml-2">$${s.total_gross || '0'}</span>
                        <span class="ml-2">${active ? '<span class="text-green-400">Active</span>' : '<span class="text-red-400">Cancelled</span>'}</span>
                    </div>
                    ${active ? `<button onclick="cancelSubscription(${s.id})" class="text-xs bg-red-700 hover:bg-red-600 px-2 py-1 rounded">Cancel</button>` : ''}
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-gray-600 text-sm">No subscriptions</p>';
        }
        html += '</div>';

        // Installs
        const insts = instRes.data?.installs || [];
        html += `<div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-400 uppercase mb-2">Installs (${insts.length})</h4>`;
        if (insts.length) {
            html += '<div class="space-y-2">';
            insts.forEach(i => {
                html += `<div class="bg-gray-800 rounded-lg p-3 text-sm flex justify-between items-center">
                    <div>
                        <span class="text-gray-500">#${i.id}</span>
                        <span class="ml-2 text-xs">${esc(i.url || '-')}</span>
                        <span class="ml-2 text-gray-500">v${esc(i.version || '?')}</span>
                    </div>
                    <button onclick="deleteInstall(${i.id})" class="text-xs bg-red-700 hover:bg-red-600 px-2 py-1 rounded">Delete</button>
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-gray-600 text-sm">No installs</p>';
        }
        html += '</div>';

        content.innerHTML = html;
    });
}

function closeDrawer() {
    document.getElementById('detailDrawer').classList.add('translate-x-full');
}

// ── Modal ───────────────────────────────────────────────────────

function showConfirm(msg, onConfirm) {
    document.getElementById('confirmMsg').textContent = msg;
    document.getElementById('confirmModal').classList.remove('hidden');
    document.getElementById('confirmBtn').onclick = () => { closeModal(); onConfirm(); };
}

function closeModal() {
    document.getElementById('confirmModal').classList.add('hidden');
}

// ── Pagination ──────────────────────────────────────────────────

function prevPage() {
    const perPage = perPageByTab[currentTab];
    currentOffset = Math.max(0, currentOffset - perPage);
    loadCurrentTab();
}

function nextPage() {
    currentOffset += perPageByTab[currentTab];
    loadCurrentTab();
}

function updatePagination() {
    const perPage = perPageByTab[currentTab];
    const page = Math.floor(currentOffset / perPage) + 1;
    document.getElementById('pageInfo').textContent = `Page ${page}`;
    document.getElementById('prevBtn').disabled = currentOffset === 0;
    document.getElementById('nextBtn').disabled = lastResultCount < perPage;
}

// ── Helpers ─────────────────────────────────────────────────────

function setStatus(msg) {
    document.getElementById('statusMsg').textContent = msg;
}

function setTableLoading() {
    document.getElementById('tableBody').innerHTML = '<tr><td colspan="99" class="px-4 py-8 text-center"><div class="spinner mx-auto"></div></td></tr>';
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function shortDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// ── Init ────────────────────────────────────────────────────────
switchTab('users');
</script>
</body>
</html>
