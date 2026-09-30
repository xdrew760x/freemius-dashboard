<?php

header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$base   = $config['api_base'];

// Multi-product: every request picks a product by ?product_id=N. Unknown or
// missing → fall back to the first configured product so direct API hits
// during development still work.
$products = $config['products'] ?? [];
if (!$products) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No products configured in config.php']);
    exit;
}
$requestedPid = (int) ($_REQUEST['product_id'] ?? 0);
$activeProduct = null;
foreach ($products as $p) {
    if ((int) $p['id'] === $requestedPid) { $activeProduct = $p; break; }
}
if ($activeProduct === null) $activeProduct = $products[0];

$pid    = (int) $activeProduct['id'];
$bearer = $activeProduct['bearer'];

function apiRequest(string $url, string $bearer, string $method = 'GET', ?array $body = null): array
{
    $curl = curl_init($url);
    $headers = [
        'Accept: application/json',
        "Authorization: Bearer {$bearer}",
    ];

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    if ($method === 'DELETE') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } elseif ($method === 'POST' || $method === 'PUT') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) {
            // JSON_FORCE_OBJECT so an empty $body serializes as {} not [] —
            // Freemius accepts an empty object PUT as "no changes" but
            // chokes on a top-level array.
            $json = json_encode($body, JSON_FORCE_OBJECT);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
        }
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error    = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return ['success' => false, 'error' => $error, 'http_code' => 0];
    }

    $data = json_decode($response, true);

    return [
        'success'   => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data'      => $data,
        'raw'       => $response,
    ];
}

// Builds a coupon PUT/POST body from query params. Only includes fields
// the caller actually set, so update_coupon doesn't blow away unrelated
// columns. The string 'null' is the explicit-null sentinel — needed for
// nullable fields like redemptions_limit / end_date / start_date.
function couponBodyFromRequest(array $req): array
{
    $body = [];
    foreach (['code', 'title', 'discount_type'] as $k) {
        if (isset($req[$k]) && $req[$k] !== '') $body[$k] = (string) $req[$k];
    }
    if (isset($req['discount']) && $req['discount'] !== '') {
        $body['discount'] = is_numeric($req['discount']) ? $req['discount'] + 0 : $req['discount'];
    }
    foreach (['redemptions_limit', 'billing_cycles'] as $k) {
        if (!isset($req[$k])) continue;
        $body[$k] = $req[$k] === 'null' || $req[$k] === '' ? null : (int) $req[$k];
    }
    foreach (['start_date', 'end_date'] as $k) {
        if (!isset($req[$k])) continue;
        $body[$k] = $req[$k] === 'null' || $req[$k] === '' ? null : (string) $req[$k];
    }
    foreach (['has_renewals_discount', 'is_one_per_user'] as $k) {
        if (!isset($req[$k])) continue;
        $body[$k] = $req[$k] === '1' || $req[$k] === 'true';
    }
    return $body;
}

// Freemius API caps count per request at 50 — when the caller wants more,
// chunk transparently so the frontend sees the full requested page.
function fetchList(string $base, string $path, array $query, string $bearer, string $collectionKey): array
{
    $apiMax         = 50;
    $requestedCount = (int) ($query['count'] ?? 25);
    $startOffset    = (int) ($query['offset'] ?? 0);

    if ($requestedCount <= $apiMax) {
        $url = "{$base}{$path}?" . http_build_query($query);
        return apiRequest($url, $bearer);
    }

    $allItems  = [];
    $lastRes   = null;
    $offset    = $startOffset;
    $remaining = $requestedCount;

    while ($remaining > 0) {
        $chunkSize        = min($apiMax, $remaining);
        $query['count']   = $chunkSize;
        $query['offset']  = $offset;
        $url              = "{$base}{$path}?" . http_build_query($query);

        $res = apiRequest($url, $bearer);
        if (!$res['success']) return $res;

        $lastRes = $res;
        $items   = $res['data'][$collectionKey] ?? [];
        $allItems = array_merge($allItems, $items);

        if (count($items) < $chunkSize) break;

        $offset    += $chunkSize;
        $remaining -= $chunkSize;
    }

    $lastRes['data'][$collectionKey] = $allItems;
    return $lastRes;
}

// "https://www.Example.com/foo/" → "example.com". Empty string if it
// doesn't look like a hostname (keeps coverage_scan from fetching junk).
function normalizeHost(string $raw): string
{
    $raw = strtolower(trim($raw));
    if ($raw === '') return '';
    if (!preg_match('#^https?://#', $raw)) $raw = 'http://' . $raw;
    $host = (string) parse_url($raw, PHP_URL_HOST);
    $host = preg_replace('/^www\./', '', $host);
    return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) ? $host : '';
}

// Every install of one product, paged 50 at a time. Lower Freemius plans cap
// API visibility (e.g. 100 installs) and 403 past it — keep what we have and
// report the cap instead of failing. Returns ['items', 'capped' (null|array), 'error' (null|response)].
function fetchProductInstalls(array $p, string $base): array
{
    $items = [];
    $o = 0;
    while (true) {
        $res = apiRequest("{$base}/products/{$p['id']}/installs.json?count=50&offset={$o}", $p['bearer']);
        if (!$res['success'] && ($res['data']['error']['code'] ?? '') === 'insufficient_account_permissions') {
            $capped = ['seen' => $o, 'message' => $res['data']['error']['message'] ?? ''];
            // The count endpoint isn't capped, so the UI can say "100 of 119".
            $cnt = apiRequest("{$base}/products/{$p['id']}/installs/count.json", $p['bearer']);
            if ($cnt['success'] && isset($cnt['data']['count'])) $capped['total'] = (int) $cnt['data']['count'];
            return ['items' => $items, 'capped' => $capped, 'error' => null];
        }
        if (!$res['success']) return ['items' => $items, 'capped' => null, 'error' => $res];
        $page = $res['data']['installs'] ?? [];
        array_push($items, ...$page);
        if (count($page) < 50) break;
        $o += 50;
    }
    return ['items' => $items, 'capped' => null, 'error' => null];
}

// ── No-license list storage ─────────────────────────────────────
// Persistent record of sites Coverage found without a license key. Lives in a
// gitignored JSON file next to the app — it's local state, not Freemius data.
const UNLICENSED_FILE = __DIR__ . '/data/unlicensed.json';

function loadUnlicensed(): array
{
    if (!is_file(UNLICENSED_FILE)) return [];
    $data = json_decode((string) file_get_contents(UNLICENSED_FILE), true);
    return is_array($data) ? $data : [];
}

function saveUnlicensed(array $rows): bool
{
    if (!is_dir(dirname(UNLICENSED_FILE)) && !mkdir(dirname(UNLICENSED_FILE), 0755, true)) return false;
    ksort($rows);
    return file_put_contents(UNLICENSED_FILE, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

// Parallel GET of many URLs. Returns url → [code, body, final_url, error].
function multiGet(array $urls, int $timeout = 15): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh) FreemiusCoverageScan/1.0',
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$url] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $status === CURLM_OK);

    $out = [];
    foreach ($handles as $url => $ch) {
        $out[$url] = [
            'code'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body'  => (string) curl_multi_getcontent($ch),
            'final' => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            'error' => curl_error($ch) ?: (curl_getinfo($ch, CURLINFO_HTTP_CODE) ? '' : 'unreachable (DNS / connect / timeout)'),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

// Fetch each host's homepage, list the /wp-content/themes/{slug}/ dirs it
// references, and for xpress-2 sites read the Version header from style.css.
function scanSites(array $hosts): array
{
    $result = [];
    if (!$hosts) return $result;

    $pages = multiGet(array_map(fn($h) => "https://{$h}/", $hosts));
    $cssUrls = [];
    foreach ($hosts as $h) {
        $r = $pages["https://{$h}/"];
        $themes = [];
        if (preg_match_all('#/wp-content/themes/([a-z0-9._-]+)/#i', $r['body'], $m)) {
            $themes = array_values(array_unique(array_map('strtolower', $m[1])));
        }
        $finalHost = normalizeHost($r['final']);
        $result[$h] = [
            'http_code'   => $r['code'],
            'error'       => $r['error'] ?: null,
            'final_host'  => $finalHost !== $h ? $finalHost : null,
            'is_wp'       => stripos($r['body'], '/wp-content/') !== false || stripos($r['body'], '/wp-includes/') !== false,
            'themes'      => $themes,
            'xpress2'     => in_array('xpress-2', $themes, true),
            'x2_version'  => null,
        ];
        if ($result[$h]['xpress2']) {
            $base = $r['final'] ? rtrim(preg_replace('#^(https?://[^/]+).*$#', '$1', $r['final']), '/') : "https://{$h}";
            $cssUrls[$h] = "{$base}/wp-content/themes/xpress-2/style.css";
        }
    }

    if ($cssUrls) {
        $css = multiGet(array_values($cssUrls), 10);
        foreach ($cssUrls as $h => $u) {
            if (preg_match('/^\s*\*?\s*Version:\s*([^\s*]+)/mi', $css[$u]['body'], $m)) {
                $result[$h]['x2_version'] = $m[1];
            }
        }
    }
    return $result;
}

// Pagination defaults
$count  = min(max((int) ($_GET['count'] ?? 25), 1), 200);
$offset = max((int) ($_GET['offset'] ?? 0), 0);
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

switch ($action) {
    // ── Configured products (for the header dropdown) ───────────────
    case 'list_products': {
        $list = array_map(fn($p) => ['id' => (int) $p['id'], 'label' => $p['label']], $products);
        echo json_encode(['success' => true, 'data' => $list]);
        break;
    }

    // ── List endpoints ──────────────────────────────────────────────
    case 'list_users': {
        $q = ['count' => $count, 'offset' => $offset];
        if ($filter) $q['filter'] = $filter;
        if ($search) $q['search'] = $search;
        echo json_encode(fetchList($base, "/products/{$pid}/users.json", $q, $bearer, 'users'));
        break;
    }

    case 'list_licenses': {
        $q = ['count' => $count, 'offset' => $offset, 'enriched' => 'true'];
        if ($filter) $q['filter'] = $filter;
        if ($search) $q['search'] = $search;
        echo json_encode(fetchList($base, "/products/{$pid}/licenses.json", $q, $bearer, 'licenses'));
        break;
    }

    // For these four tabs we deliberately DON'T forward ?search to Freemius —
    // their server-side search only matches a narrow set of fields (e.g.
    // install title but not URL) and silently drops items that the client-side
    // keyword filter would otherwise catch. The frontend filters lastItems
    // locally after the page is loaded, which gives correct results across
    // every column.
    case 'list_subscriptions': {
        $q = ['count' => $count, 'offset' => $offset, 'extended' => 'true'];
        if ($filter) $q['filter'] = $filter;
        echo json_encode(fetchList($base, "/products/{$pid}/subscriptions.json", $q, $bearer, 'subscriptions'));
        break;
    }

    case 'list_installs': {
        $q = ['count' => $count, 'offset' => $offset];
        echo json_encode(fetchList($base, "/products/{$pid}/installs.json", $q, $bearer, 'installs'));
        break;
    }

    case 'list_payments': {
        $q = ['count' => $count, 'offset' => $offset, 'extended' => 'true'];
        if ($filter) $q['filter'] = $filter;
        echo json_encode(fetchList($base, "/products/{$pid}/payments.json", $q, $bearer, 'payments'));
        break;
    }

    case 'list_coupons': {
        $q = ['count' => $count, 'offset' => $offset];
        if ($filter) $q['filter'] = $filter;
        echo json_encode(fetchList($base, "/products/{$pid}/coupons.json", $q, $bearer, 'coupons'));
        break;
    }

    case 'create_coupon': {
        $body = couponBodyFromRequest($_GET);
        if (!isset($body['code']) || !isset($body['discount']) || !isset($body['discount_type'])) {
            echo json_encode(['success' => false, 'error' => 'code, discount, and discount_type are required']);
            break;
        }
        echo json_encode(apiRequest("{$base}/products/{$pid}/coupons.json", $bearer, 'POST', $body));
        break;
    }

    case 'update_coupon': {
        $cid = (int) ($_GET['coupon_id'] ?? 0);
        if (!$cid) { echo json_encode(['success' => false, 'error' => 'Missing coupon_id']); break; }
        $body = couponBodyFromRequest($_GET);
        echo json_encode(apiRequest("{$base}/products/{$pid}/coupons/{$cid}.json", $bearer, 'PUT', $body));
        break;
    }

    case 'delete_coupon': {
        $cid = (int) ($_GET['coupon_id'] ?? 0);
        if (!$cid) { echo json_encode(['success' => false, 'error' => 'Missing coupon_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/coupons/{$cid}.json", $bearer, 'DELETE'));
        break;
    }

    case 'update_license': {
        $lid = (int) ($_GET['license_id'] ?? 0);
        if (!$lid) { echo json_encode(['success' => false, 'error' => 'Missing license_id']); break; }

        // Only send the fields the caller actually set, so we don't overwrite
        // unrelated columns. The literal string "null" on expiration means
        // "make this license lifetime" — pass real null in the JSON body.
        $body = [];
        if (array_key_exists('expiration', $_GET)) {
            $body['expiration'] = $_GET['expiration'] === 'null' ? null : $_GET['expiration'];
        }
        foreach (['plan_id', 'pricing_id'] as $k) {
            if (isset($_GET[$k]) && $_GET[$k] !== '') $body[$k] = (int) $_GET[$k];
        }
        if (array_key_exists('quota', $_GET)) {
            $body['quota'] = $_GET['quota'] === '' || $_GET['quota'] === 'null' ? null : (int) $_GET['quota'];
        }
        if (isset($_GET['is_whitelabeled'])) {
            $body['is_whitelabeled'] = $_GET['is_whitelabeled'] === '1' || $_GET['is_whitelabeled'] === 'true';
        }

        $url = "{$base}/products/{$pid}/licenses/{$lid}.json";
        echo json_encode(apiRequest($url, $bearer, 'PUT', $body));
        break;
    }

    case 'get_license': {
        $lid = (int) ($_GET['license_id'] ?? 0);
        if (!$lid) { echo json_encode(['success' => false, 'error' => 'Missing license_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/licenses/{$lid}.json", $bearer));
        break;
    }

    case 'list_plans': {
        $url = "{$base}/products/{$pid}/plans.json";
        echo json_encode(apiRequest($url, $bearer));
        break;
    }

    case 'list_pricing': {
        $planId = (int) ($_GET['plan_id'] ?? 0);
        if (!$planId) { echo json_encode(['success' => false, 'error' => 'Missing plan_id']); break; }
        $url = "{$base}/products/{$pid}/plans/{$planId}/pricing.json";
        echo json_encode(apiRequest($url, $bearer));
        break;
    }

    case 'resolve_ips': {
        // Freemius doesn't return hosting IPs — resolve them ourselves via DNS.
        $hosts = $_POST['hosts'] ?? [];
        if (!is_array($hosts)) $hosts = [];
        $result = [];
        foreach ($hosts as $h) {
            $h = trim((string) $h);
            if ($h === '' || isset($result[$h])) continue;
            $ip = @gethostbyname($h);
            $result[$h] = ($ip && $ip !== $h) ? $ip : null;
        }
        echo json_encode(['success' => true, 'data' => $result]);
        break;
    }

    case 'count_installs': {
        // Freemius has no total-count endpoint — iterate 50 at a time until drained.
        $total = 0;
        $o = 0;
        $chunk = 50;
        while (true) {
            $res = apiRequest("{$base}/products/{$pid}/installs.json?count={$chunk}&offset={$o}", $bearer);
            if (!$res['success']) { echo json_encode($res); exit; }
            $items = $res['data']['installs'] ?? [];
            $total += count($items);
            if (count($items) < $chunk) break;
            $o += $chunk;
        }
        echo json_encode(['success' => true, 'data' => ['total' => $total]]);
        break;
    }

    // ── Coverage: every Freemius install host, across ALL products ──
    // Sites that never opted in / never entered a key have no install
    // record, so the Coverage tab diffs this map against a known-domain list.
    case 'coverage_installs': {
        $map = [];
        $capped = []; // product id → installs visible before the plan's view cap hit
        foreach ($products as $p) {
            $r = fetchProductInstalls($p, $base);
            if ($r['error']) { echo json_encode($r['error']); exit; }
            if ($r['capped']) $capped[(string) $p['id']] = $r['capped'];
            foreach ($r['items'] as $it) {
                $host = normalizeHost((string) ($it['url'] ?? ''));
                if ($host === '') continue;
                // Keep the most useful record per host: licensed beats unlicensed.
                if (isset($map[$host]) && $map[$host]['license_id'] && !$it['license_id']) continue;
                $map[$host] = [
                    'install_id' => $it['id'] ?? null,
                    'product_id' => (int) $p['id'],
                    'license_id' => $it['license_id'] ?? null,
                    'plan_id'    => $it['plan_id'] ?? null,
                    'version'    => $it['version'] ?? null,
                    'is_active'  => !empty($it['is_active']),
                ];
            }
        }
        echo json_encode(['success' => true, 'data' => $map, 'capped' => $capped]);
        break;
    }

    // ── Coverage: fetch each site's homepage and detect the theme ───
    case 'coverage_scan': {
        $hosts = $_POST['hosts'] ?? [];
        if (!is_array($hosts)) $hosts = [];
        $hosts = array_slice(array_unique(array_filter(array_map('normalizeHost', $hosts))), 0, 25);
        echo json_encode(['success' => true, 'data' => scanSites($hosts)]);
        break;
    }

    // ── User detail ─────────────────────────────────────────────────
    case 'get_user':
        $uid = (int) ($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false, 'error' => 'Missing user_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/users/{$uid}.json", $bearer));
        break;

    case 'user_licenses':
        $uid = (int) ($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false, 'error' => 'Missing user_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/users/{$uid}/licenses.json?count={$count}&offset={$offset}", $bearer));
        break;

    case 'user_installs':
        $uid = (int) ($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false, 'error' => 'Missing user_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/users/{$uid}/installs.json?count={$count}&offset={$offset}", $bearer));
        break;

    case 'user_subscriptions':
        $uid = (int) ($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false, 'error' => 'Missing user_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/users/{$uid}/subscriptions.json?count={$count}&offset={$offset}", $bearer));
        break;

    // ── Delete / Cancel endpoints ───────────────────────────────────
    case 'delete_license':
        $lid = (int) ($_GET['license_id'] ?? 0);
        if (!$lid) { echo json_encode(['success' => false, 'error' => 'Missing license_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/licenses/{$lid}.json?delete=true", $bearer, 'DELETE'));
        break;

    case 'cancel_subscription':
        $sid = (int) ($_GET['subscription_id'] ?? 0);
        if (!$sid) { echo json_encode(['success' => false, 'error' => 'Missing subscription_id']); break; }
        $reason = $_GET['reason'] ?? '';
        $url = "{$base}/products/{$pid}/subscriptions/{$sid}.json";
        if ($reason) $url .= '?reason=' . urlencode($reason);
        echo json_encode(apiRequest($url, $bearer, 'DELETE'));
        break;

    case 'cancel_license_subscription':
        $lid = (int) ($_GET['license_id'] ?? 0);
        if (!$lid) { echo json_encode(['success' => false, 'error' => 'Missing license_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/licenses/{$lid}/subscription.json", $bearer, 'DELETE'));
        break;

    case 'delete_install':
        $iid = (int) ($_GET['install_id'] ?? 0);
        if (!$iid) { echo json_encode(['success' => false, 'error' => 'Missing install_id']); break; }
        echo json_encode(apiRequest("{$base}/products/{$pid}/installs/{$iid}.json", $bearer, 'DELETE'));
        break;

    // ── All Installs: every install of every product, flat ──────────
    // The All Installs tab groups these by host client-side. Per-product
    // errors are reported rather than aborting, so one bad token doesn't
    // blank the whole view.
    case 'all_installs': {
        $out = [];
        $capped = [];
        $errors = [];
        foreach ($products as $p) {
            $r = fetchProductInstalls($p, $base);
            if ($r['capped']) $capped[(string) $p['id']] = $r['capped'];
            if ($r['error']) $errors[(string) $p['id']] = $r['error']['data']['error']['message'] ?? ('HTTP ' . $r['error']['http_code']);
            foreach ($r['items'] as $it) {
                $out[] = [
                    'install_id' => $it['id'] ?? null,
                    'product_id' => (int) $p['id'],
                    'host'       => normalizeHost((string) ($it['url'] ?? '')),
                    'url'        => $it['url'] ?? '',
                    'user_id'    => $it['user_id'] ?? null,
                    'license_id' => $it['license_id'] ?? null,
                    'plan_id'    => $it['plan_id'] ?? null,
                    'version'    => $it['version'] ?? null,
                    'is_active'  => !empty($it['is_active']),
                    'is_premium' => !empty($it['is_premium']),
                    'created'    => $it['created'] ?? null,
                ];
            }
        }
        echo json_encode(['success' => true, 'data' => $out, 'capped' => $capped, 'errors' => $errors]);
        break;
    }

    // ── No-license list ─────────────────────────────────────────────
    case 'unlicensed_list': {
        echo json_encode(['success' => true, 'data' => array_values(loadUnlicensed())]);
        break;
    }

    // POST JSON {found: [{host, status, product_id, install_id, version}], resolved: [host]}.
    // `found` rows are upserted (first_seen kept); `resolved` hosts — ones the
    // latest scan saw licensed — drop off the list.
    case 'unlicensed_sync': {
        $in = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($in)) { echo json_encode(['success' => false, 'error' => 'Invalid JSON body']); break; }
        $rows = loadUnlicensed();
        $now = gmdate('c');
        $added = $updated = $removed = 0;
        foreach ((array) ($in['found'] ?? []) as $f) {
            $host = normalizeHost((string) ($f['host'] ?? ''));
            $status = (string) ($f['status'] ?? '');
            if ($host === '' || !in_array($status, ['nokey', 'missing'], true)) continue;
            isset($rows[$host]) ? $updated++ : $added++;
            $rows[$host] = [
                'host'       => $host,
                'status'     => $status,
                'product_id' => isset($f['product_id']) ? (int) $f['product_id'] : null,
                'install_id' => isset($f['install_id']) ? (int) $f['install_id'] : null,
                'version'    => isset($f['version']) ? (string) $f['version'] : null,
                'first_seen' => $rows[$host]['first_seen'] ?? $now,
                'last_seen'  => $now,
            ];
        }
        foreach ((array) ($in['resolved'] ?? []) as $h) {
            $host = normalizeHost((string) $h);
            if ($host !== '' && isset($rows[$host])) { unset($rows[$host]); $removed++; }
        }
        if (!saveUnlicensed($rows)) { echo json_encode(['success' => false, 'error' => 'Could not write ' . UNLICENSED_FILE]); break; }
        echo json_encode(['success' => true, 'data' => compact('added', 'updated', 'removed') + ['total' => count($rows)]]);
        break;
    }

    case 'unlicensed_remove': {
        $hosts = $_POST['hosts'] ?? [];
        $rows = loadUnlicensed();
        $removed = 0;
        foreach ((array) $hosts as $h) {
            $host = normalizeHost((string) $h);
            if ($host !== '' && isset($rows[$host])) { unset($rows[$host]); $removed++; }
        }
        if (!saveUnlicensed($rows)) { echo json_encode(['success' => false, 'error' => 'Could not write ' . UNLICENSED_FILE]); break; }
        echo json_encode(['success' => true, 'data' => ['removed' => $removed, 'total' => count($rows)]]);
        break;
    }

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
