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
        <div class="flex items-center gap-3">
            <label for="productSelect" class="text-xs text-gray-500">Product:</label>
            <select id="productSelect" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500" onchange="changeProduct()"></select>
        </div>
    </div>
</header>

<!-- Tabs -->
<nav class="bg-gray-900 border-b border-gray-800">
    <div class="max-w-7xl mx-auto flex gap-0">
        <button onclick="switchTab('users')" id="tab-users" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition tab-active">Users</button>
        <button onclick="switchTab('licenses')" id="tab-licenses" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Licenses</button>
        <button onclick="switchTab('subscriptions')" id="tab-subscriptions" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Subscriptions</button>
        <button onclick="switchTab('installs')" id="tab-installs" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Installs</button>
        <button onclick="switchTab('payments')" id="tab-payments" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Payments</button>
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
        <button onclick="exportCsv()" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200 px-4 py-2 rounded-lg text-sm transition" title="Export current view to CSV">Export CSV</button>
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

<script src="dashboard.js?v=<?= filemtime(__DIR__.'/dashboard.js') ?>"></script>
</body>
</html>
