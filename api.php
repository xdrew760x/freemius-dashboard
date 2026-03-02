<?php

header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$base   = $config['api_base'];
$pid    = $config['product_id'];
$bearer = $config['bearer'];

function apiRequest(string $url, string $bearer, string $method = 'GET'): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            "Authorization: Bearer {$bearer}",
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($method === 'DELETE') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

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
    ];
}

// Pagination defaults
$count  = min((int) ($_GET['count'] ?? 25), 50);
$offset = max((int) ($_GET['offset'] ?? 0), 0);
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

switch ($action) {
    // ── List endpoints ──────────────────────────────────────────────
    case 'list_users':
        $url = "{$base}/products/{$pid}/users.json?count={$count}&offset={$offset}";
        if ($filter) $url .= '&filter=' . urlencode($filter);
        if ($search) $url .= '&search=' . urlencode($search);
        echo json_encode(apiRequest($url, $bearer));
        break;

    case 'list_licenses':
        $url = "{$base}/products/{$pid}/licenses.json?count={$count}&offset={$offset}&enriched=true";
        if ($filter) $url .= '&filter=' . urlencode($filter);
        if ($search) $url .= '&search=' . urlencode($search);
        echo json_encode(apiRequest($url, $bearer));
        break;

    case 'list_subscriptions':
        $url = "{$base}/products/{$pid}/subscriptions.json?count={$count}&offset={$offset}&extended=true";
        if ($filter) $url .= '&filter=' . urlencode($filter);
        echo json_encode(apiRequest($url, $bearer));
        break;

    case 'list_installs':
        $url = "{$base}/products/{$pid}/installs.json?count={$count}&offset={$offset}";
        echo json_encode(apiRequest($url, $bearer));
        break;

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
        echo json_encode(apiRequest("{$base}/products/{$pid}/licenses/{$lid}.json", $bearer, 'DELETE'));
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
