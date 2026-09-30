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
        /* Cross-product mode is read-only: hide per-row action cells (always the last <td>). */
        body.all-products-mode #tableBody td:last-child { visibility: hidden; }
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
        <button onclick="switchTab('coupons')" id="tab-coupons" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Coupons</button>
        <button onclick="switchTab('coverage')" id="tab-coverage" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">Coverage</button>
        <button onclick="switchTab('allinstalls')" id="tab-allinstalls" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">All Installs</button>
        <button onclick="switchTab('unlicensed')" id="tab-unlicensed" class="tab-btn px-6 py-3 text-sm hover:text-blue-400 transition">No License</button>
    </div>
</nav>

<!-- Main Content -->
<main class="max-w-7xl mx-auto p-6">

    <!-- Coverage — known domains vs Freemius installs (sites that never entered a key) -->
    <section id="coveragePanel" class="hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-1">
                <label for="coverageDomains" class="block text-sm text-gray-400 mb-2">Known client domains — one per line (URLs fine)</label>
                <textarea id="coverageDomains" rows="12" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:border-blue-500" placeholder="example.com&#10;https://www.another-site.com/"></textarea>
                <div class="flex items-center gap-3 mt-3">
                    <button id="coverageRunBtn" onclick="runCoverage()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">Run Check</button>
                    <label class="flex items-center gap-2 text-xs text-gray-400"><input type="checkbox" id="coverageScanSites" checked> Scan homepages</label>
                    <button onclick="exportCoverageCsv()" class="ml-auto bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200 px-4 py-2 rounded-lg text-sm transition">Export CSV</button>
                </div>
                <p id="coverageStatus" class="text-sm text-gray-400 mt-3"></p>
            </div>
            <div class="lg:col-span-2">
                <div id="coverageSummary" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"></div>
                <div class="flex items-center gap-3 mb-3">
                    <select id="coverageFilter" onchange="renderCoverage()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                        <option value="">All statuses</option>
                        <option value="missing">Missing from Freemius</option>
                        <option value="nokey">In Freemius, no license</option>
                        <option value="licensed">Licensed</option>
                        <option value="other">Not xpress-2 / unreachable</option>
                    </select>
                    <span class="text-xs text-gray-500">Checks every configured product's installs, regardless of the Product dropdown.</span>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-800 text-gray-400 text-left">
                                <tr><th class="px-4 py-3">Domain</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Freemius</th><th class="px-4 py-3">Site scan</th><th class="px-4 py-3">xpress-2 ver</th></tr>
                            </thead>
                            <tbody id="coverageBody" class="divide-y divide-gray-800">
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Paste domains and click "Run Check"</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- All Installs — every product's installs, one row per domain -->
    <section id="allInstallsPanel" class="hidden">
        <div id="allInstallsCap" class="hidden mb-4 bg-yellow-900/30 border border-yellow-800 text-yellow-200 text-sm rounded-lg px-4 py-3"></div>
        <div id="allInstallsSummary" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"></div>
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <input type="text" id="allInstallsSearch" placeholder="Search domains..." oninput="renderAllInstalls()" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm w-64 focus:outline-none focus:border-blue-500">
            <select id="allInstallsProduct" onchange="renderAllInstalls()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                <option value="">All products</option>
            </select>
            <select id="allInstallsLicense" onchange="renderAllInstalls()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                <option value="">Licensed + no license</option>
                <option value="licensed">Licensed only</option>
                <option value="nokey">No license only</option>
            </select>
            <label class="flex items-center gap-2 text-xs text-gray-400"><input type="checkbox" id="allInstallsActive" onchange="renderAllInstalls()"> Active only</label>
            <button onclick="loadAllInstalls(true)" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200 px-4 py-2 rounded-lg text-sm transition">Refresh</button>
            <button onclick="copyAllInstallsDomains()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">Copy domains</button>
            <button onclick="exportAllInstallsCsv()" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200 px-4 py-2 rounded-lg text-sm transition">Export CSV</button>
            <span id="allInstallsStatus" class="text-sm text-gray-400"></span>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-800 text-gray-400 text-left">
                        <tr><th class="px-4 py-3">Domain</th><th class="px-4 py-3">Products &amp; license</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Version</th><th class="px-4 py-3">Installed</th></tr>
                    </thead>
                    <tbody id="allInstallsBody" class="divide-y divide-gray-800">
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- No License — persistent list, fed automatically by every Coverage check -->
    <section id="unlicensedPanel" class="hidden">
        <div id="unlicensedSummary" class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4"></div>
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <input type="text" id="unlicensedSearch" placeholder="Search domains..." oninput="renderUnlicensed()" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm w-64 focus:outline-none focus:border-blue-500">
            <select id="unlicensedFilter" onchange="renderUnlicensed()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                <option value="">All reasons</option>
                <option value="missing">Missing from Freemius</option>
                <option value="nokey">In Freemius, no license</option>
            </select>
            <button onclick="copyUnlicensedDomains()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition">Copy domains</button>
            <button onclick="exportUnlicensedCsv()" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200 px-4 py-2 rounded-lg text-sm transition">Export CSV</button>
            <span id="unlicensedStatus" class="text-sm text-gray-400"></span>
        </div>
        <p class="text-xs text-gray-500 mb-3">Sites are added here automatically whenever a Coverage check finds them without a license, and drop off once a check sees them licensed.</p>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-800 text-gray-400 text-left">
                        <tr><th class="px-4 py-3">Domain</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Freemius</th><th class="px-4 py-3">xpress-2 ver</th><th class="px-4 py-3">First seen</th><th class="px-4 py-3">Last seen</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody id="unlicensedBody" class="divide-y divide-gray-800">
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div id="standardView">
    <!-- Toolbar -->
    <div class="flex items-center gap-4 mb-6">
        <input type="text" id="searchInput" placeholder="Search..." class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm w-64 focus:outline-none focus:border-blue-500" oninput="applyViewAndRender()" onkeydown="if(event.key==='Enter') loadCurrentTab()">
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
        <button id="newCouponBtn" onclick="openNewCoupon()" class="hidden bg-green-700 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm transition">+ New Coupon</button>
        <div id="installsTotalBadge" class="hidden ml-auto text-sm bg-gray-800 border border-gray-700 rounded-lg px-3 py-2">
            <span class="text-gray-500">Total sites:</span>
            <span id="installsTotalValue" class="text-white font-semibold ml-1">—</span>
            <button onclick="refreshInstallsTotal()" title="Refresh" class="ml-2 text-gray-500 hover:text-blue-400">↻</button>
        </div>
        <div id="statusMsg" class="text-sm text-gray-400 ml-4"></div>
    </div>

    <!-- Bulk-action strip — visible only when items are selected on a bulk-capable tab -->
    <div id="bulkStrip" class="hidden flex items-center gap-3 mb-3 bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-sm">
        <span id="bulkStripCount" class="text-white font-medium"></span>
        <button onclick="bulkDelete()" class="bg-red-700 hover:bg-red-600 text-white px-3 py-1 rounded text-xs transition">Delete Selected</button>
        <button onclick="bulkClearAndRerender()" class="text-gray-400 hover:text-white text-xs ml-auto">Clear</button>
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
    </div><!-- /#standardView -->
</main>

<!-- Edit License Modal -->
<div id="editLicenseModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 max-w-lg w-full mx-4">
        <h3 class="text-lg font-semibold text-white mb-1">Edit License</h3>
        <p id="editLicenseInfo" class="text-xs text-gray-500 mb-5">&nbsp;</p>

        <div class="space-y-4 text-sm">
            <div>
                <label class="block text-gray-400 mb-1">Plan</label>
                <select id="editLicensePlan" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"></select>
            </div>
            <div>
                <label class="block text-gray-400 mb-1">Pricing</label>
                <select id="editLicensePricing" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"></select>
            </div>
            <div>
                <label class="block text-gray-400 mb-1">Expiration</label>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-1.5"><input type="radio" name="editLicenseExp" id="editLicenseExpLifetime" value="lifetime"> <span>Lifetime</span></label>
                    <label class="flex items-center gap-1.5"><input type="radio" name="editLicenseExp" id="editLicenseExpDated" value="date"> <span>Date:</span></label>
                    <input type="date" id="editLicenseExpDate" class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-1 disabled:opacity-40">
                </div>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">Quota</label>
                    <select id="editLicenseQuota" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
                        <option value="1">1 site</option>
                        <option value="5">5 sites</option>
                        <option value="25">25 sites</option>
                        <option value="null">Unlimited</option>
                    </select>
                </div>
                <div class="flex-1 flex items-end pb-1">
                    <label class="flex items-center gap-2"><input type="checkbox" id="editLicenseWhitelabel"> <span>Whitelabel</span></label>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeEditLicense()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition">Cancel</button>
            <button id="editLicenseSaveBtn" onclick="saveEditLicense()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm text-white transition">Save Changes</button>
        </div>
    </div>
</div>

<!-- Coupon Edit Modal -->
<div id="editCouponModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 max-w-lg w-full mx-4">
        <h3 id="editCouponTitle" class="text-lg font-semibold text-white mb-1">New Coupon</h3>
        <p id="editCouponInfo" class="text-xs text-gray-500 mb-5">&nbsp;</p>

        <div class="space-y-4 text-sm">
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">Code</label>
                    <input type="text" id="editCouponCode" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 font-mono focus:outline-none focus:border-blue-500" placeholder="e.g. BLACKFRIDAY">
                </div>
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">Title</label>
                    <input type="text" id="editCouponTitleField" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500" placeholder="Display name (optional)">
                </div>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">Discount</label>
                    <input type="number" id="editCouponDiscount" min="0" step="0.01" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">Type</label>
                    <select id="editCouponDiscountType" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
                        <option value="percentage">Percentage (%)</option>
                        <option value="dollar">Dollar ($)</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">Limit</label>
                    <input type="number" id="editCouponLimit" min="1" placeholder="∞" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
                </div>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">Start Date</label>
                    <input type="date" id="editCouponStart" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex-1">
                    <label class="block text-gray-400 mb-1">End Date</label>
                    <input type="date" id="editCouponEnd" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500">
                </div>
            </div>
            <div class="flex gap-6">
                <label class="flex items-center gap-2"><input type="checkbox" id="editCouponRenewals"> <span>Apply on renewals</span></label>
                <label class="flex items-center gap-2"><input type="checkbox" id="editCouponOnePer"> <span>One per user</span></label>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeEditCoupon()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition">Cancel</button>
            <button id="editCouponSaveBtn" onclick="saveCoupon()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm text-white transition">Save</button>
        </div>
    </div>
</div>

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
