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
        <select id="filterSelect" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500" onchange="loadCurrentTab()">
            <option value="">All</option>
        </select>
        <button onclick="loadCurrentTab()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">Load</button>
        <div id="statusMsg" class="text-sm text-gray-400 ml-auto"></div>
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
const PER_PAGE = 25;
let currentTab = 'users';
let currentOffset = 0;
let lastResultCount = 0;

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
    installs:      ['ID','User ID','URL','Version','License ID','Plan ID','Active','Actions'],
};

function switchTab(tab) {
    currentTab = tab;
    currentOffset = 0;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-active'));
    document.getElementById('tab-' + tab).classList.add('tab-active');

    // Update filters
    const sel = document.getElementById('filterSelect');
    sel.innerHTML = '';
    filters[tab].forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.v;
        opt.textContent = f.l;
        sel.appendChild(opt);
    });

    // Update search placeholder
    const search = document.getElementById('searchInput');
    search.value = '';
    if (tab === 'users') search.placeholder = 'Search by name, email, or ID...';
    else if (tab === 'licenses') search.placeholder = 'Search by ID or key...';
    else search.placeholder = 'Search...';

    loadCurrentTab();
}

function loadCurrentTab() {
    const search = document.getElementById('searchInput').value;
    const filter = document.getElementById('filterSelect').value;

    let params = `action=list_${currentTab}&count=${PER_PAGE}&offset=${currentOffset}`;
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
    const head = document.getElementById('tableHead');
    const body = document.getElementById('tableBody');

    // Build header
    head.innerHTML = '<tr>' + headers[currentTab].map(h => `<th class="px-4 py-3 font-medium">${h}</th>`).join('') + '</tr>';

    // Determine the collection key
    const key = currentTab;
    const items = data?.[key] || [];
    lastResultCount = items.length;

    if (!items.length) {
        body.innerHTML = '<tr><td colspan="99" class="px-4 py-8 text-center text-gray-500">No results</td></tr>';
        setStatus('0 results');
        updatePagination();
        return;
    }

    body.innerHTML = items.map(item => renderRow(item)).join('');
    setStatus(`${items.length} results (offset ${currentOffset})`);
    updatePagination();
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
            return `<tr class="hover:bg-gray-800/50 transition">
                <td class="px-4 py-3">${item.id}</td>
                <td class="px-4 py-3">${item.user_id || '-'}</td>
                <td class="px-4 py-3 text-xs max-w-xs truncate">${esc(item.url || '-')}</td>
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
                setStatus(`Error on ${label}: ${res.error || JSON.stringify(res.data)}`);
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
    currentOffset = Math.max(0, currentOffset - PER_PAGE);
    loadCurrentTab();
}

function nextPage() {
    currentOffset += PER_PAGE;
    loadCurrentTab();
}

function updatePagination() {
    const page = Math.floor(currentOffset / PER_PAGE) + 1;
    document.getElementById('pageInfo').textContent = `Page ${page}`;
    document.getElementById('prevBtn').disabled = currentOffset === 0;
    document.getElementById('nextBtn').disabled = lastResultCount < PER_PAGE;
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
