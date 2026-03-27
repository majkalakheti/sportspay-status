<?php
declare(strict_types=1);

require_once __DIR__ . '/../checker.php';

header('Content-Type: application/x-ndjson');
header('Cache-Control: no-cache');

$config = getConfig();
$previousStatus = getLastStatus();
$previousByUrl = [];
foreach (($previousStatus['results'] ?? []) as $oldRow) {
    if (isset($oldRow['url'])) {
        $previousByUrl[(string) $oldRow['url']] = $oldRow;
    }
}

$results = [];

foreach ($config['servers'] as $server) {
    $baseUrl = (string) ($server['base_url'] ?? '');
    $hostname = (string) ($server['hostname'] ?? '');
    $url = buildHealthCheckUrl($baseUrl, $hostname);
    $internalIp = (string) ($server['internal_ip'] ?? '');
    $instruction = (string) ($server['instruction'] ?? '');
    $group = (string) ($server['group'] ?? '');
    $role = (string) ($server['role'] ?? '');
    $machineType = (string) ($server['machine_type'] ?? 'vm');
    $isCaddy = (bool) ($server['is_caddy'] ?? false);

    $result = checkOneUrl($url);
    $result['base_url'] = $baseUrl;
    $result['hostname'] = $hostname;
    $result['internal_ip'] = $internalIp;
    $result['instruction'] = $instruction;
    $result['group'] = $group;
    $result['role'] = $role;
    $result['machine_type'] = $machineType;
    $result['is_caddy'] = $isCaddy;

    $previous = $previousByUrl[$url] ?? [];
    $previousIssueSince = isset($previous['issue_active_since']) ? (string) $previous['issue_active_since'] : null;
    $previousStatusValue = isset($previous['status']) ? (string) $previous['status'] : null;
    $currentStatus = (string) $result['status'];
    $isIssue = in_array($currentStatus, ['orange', 'red'], true);

    if ($isIssue) {
        $issueSince = $previousIssueSince;
        if ($issueSince === null || !in_array((string) $previousStatusValue, ['orange', 'red'], true)) {
            $issueSince = date(DATE_ATOM);
        }

        $result['issue_active_since'] = $issueSince;
        $startTs = strtotime($issueSince);
        $result['issue_duration_seconds'] = $startTs !== false ? max(0, time() - $startTs) : 0;
    } else {
        $result['issue_active_since'] = null;
        $result['issue_duration_seconds'] = 0;

        if (in_array((string) $previousStatusValue, ['orange', 'red'], true) && !empty($previousIssueSince)) {
            $startTs = strtotime($previousIssueSince);
            $duration = $startTs !== false ? max(0, time() - $startTs) : null;
            appendIncidentLog([
                'url' => $url,
                'hostname' => $hostname,
                'internal_ip' => $internalIp,
                'started_at' => $previousIssueSince,
                'resolved_at' => date(DATE_ATOM),
                'duration_seconds' => $duration,
                'last_issue_status' => $previousStatusValue,
            ]);
        }
    }

    $results[] = $result;
    echo json_encode(['type' => 'result', 'result' => $result], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

$statusPayload = [
    'checked_at' => date(DATE_ATOM),
    'check_interval_seconds' => $config['check_interval_seconds'],
    'alert_email' => 'disabled',
    'results' => $results,
];
saveLastStatus($statusPayload);

echo json_encode(['type' => 'done', 'checked_at' => $statusPayload['checked_at']], JSON_UNESCAPED_SLASHES) . PHP_EOL;
if (function_exists('ob_flush')) {
    @ob_flush();
}
flush();
