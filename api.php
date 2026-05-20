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
            $json = json_encode($body);
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

    case 'test_create_license': {
        // Probe the Freemius license-create endpoint. Reports raw response so we
        // can see required fields / error messages without committing to the
        // final payload shape.
        $body = [
            'plan_id'       => (int) ($_GET['plan_id'] ?? 0),
            'pricing_id'    => (int) ($_GET['pricing_id'] ?? 0),
            'user_id'       => (int) ($_GET['user_id'] ?? 0),
            'install_id'    => (int) ($_GET['install_id'] ?? 0),
            'quota'         => 1,
            'billing_cycle' => 0, // 0 = lifetime in Freemius convention (1=monthly, 12=annual)
            'expiration'    => null,
        ];
        $url = "{$base}/products/{$pid}/licenses.json";
        echo json_encode([
            'attempt_url'  => $url,
            'attempt_body' => $body,
            'response'     => apiRequest($url, $bearer, 'POST', $body),
        ]);
        break;
    }

    case 'make_license_lifetime': {
        $lid = (int) ($_GET['license_id'] ?? 0);
        if (!$lid) { echo json_encode(['success' => false, 'error' => 'Missing license_id']); break; }
        $url = "{$base}/products/{$pid}/licenses/{$lid}.json";
        echo json_encode(apiRequest($url, $bearer, 'PUT', ['expiration' => null]));
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

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
