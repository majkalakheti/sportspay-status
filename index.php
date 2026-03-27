<?php
declare(strict_types=1);

require_once __DIR__ . '/checker.php';

ensureDataFiles();

$config = getConfig();
$lastStatus = getLastStatus();
$serverBlueprints = array_map(static function (array $server): array {
    $baseUrl = (string)($server['base_url'] ?? '');
    $hostname = (string)($server['hostname'] ?? '');
    return [
        'base_url' => $baseUrl,
        'url' => buildHealthCheckUrl($baseUrl, $hostname),
        'hostname' => $hostname,
        'internal_ip' => (string)($server['internal_ip'] ?? ''),
        'group' => (string)($server['group'] ?? ''),
        'role' => (string)($server['role'] ?? ''),
        'machine_type' => (string)($server['machine_type'] ?? 'vm'),
        'is_caddy' => (bool)($server['is_caddy'] ?? false),
    ];
}, $config['servers']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Monitor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }
        .status-green { background-color: #198754; }
        .status-orange { background-color: #fd7e14; }
        .status-red { background-color: #dc3545; }
        .small-muted { font-size: 0.85rem; color: #6c757d; }
        .server-item { cursor: pointer; border: 1px solid #d9d9d9; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; background: #fff; }
        .server-name { font-weight: 600; }
        .group-title { font-weight: 700; letter-spacing: .03em; }
        .board-box { border: 1px solid #b8bec5; border-radius: 10px; background: #fff; }
        .status-pending { background-color: #6c757d; }
        .placeholder-text { min-height: 0.9rem; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="h3 mb-3">Server Monitor Dashboard</h1>
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <strong>Live Status</strong>
                <span class="small-muted ms-2" id="lastCheckedText">
                    Last checked:
                    <?php echo isset($lastStatus['checked_at']) ? htmlspecialchars((string)$lastStatus['checked_at'], ENT_QUOTES, 'UTF-8') : 'never'; ?>
                </span>
            </div>
            <button id="manualCheckBtn" class="btn btn-primary btn-sm">Check Now</button>
        </div>
        <div class="card-body">
            <div id="dashboardBoard"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.INITIAL_RESULTS = <?php echo json_encode($lastStatus['results'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
    window.SERVER_BLUEPRINTS = <?php echo json_encode($serverBlueprints, JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>
