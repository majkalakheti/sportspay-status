<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function classifyStatus(int $httpCode, ?bool $repl): string
{
    if ($httpCode !== 200) {
        return 'red';
    }
    if ($repl === true) {
        return 'green';
    }
    return 'orange';
}

function buildHealthCheckUrl(string $baseUrl, string $hostname = ''): string
{
    $normalizedBase = rtrim($baseUrl, '/');
    $normalizedHost = strtolower(trim($hostname));

    if (in_array($normalizedHost, ['svra.interpaypos.com', 'svrb.interpaypos.com'], true)) {
        return $normalizedBase . ':1443/Health/check';
    }

    return $normalizedBase . '/api/Health/check';
}

function checkOneUrl(string $url): array
{
    $start = microtime(true);
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $body = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $elapsedMs = (int) round((microtime(true) - $start) * 1000);
    curl_close($ch);

    $payload = null;
    $repl = null;
    $avgResTime = null;
    $dur = null;

    if ($body !== false) {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $payload = $decoded;
            $repl = array_key_exists('repl', $decoded) ? (bool) $decoded['repl'] : null;
            $avgResTime = $decoded['avg_res_time'] ?? null;
            $dur = $decoded['dur'] ?? null;
        }
    }

    return [
        'url' => $url,
        'checked_at' => date(DATE_ATOM),
        'http_code' => $httpCode,
        'curl_error' => $curlErr !== '' ? $curlErr : null,
        'repl' => $repl,
        'avg_res_time' => $avgResTime,
        'dur' => $dur,
        'elapsed_ms' => $elapsedMs,
        'status' => classifyStatus($httpCode, $repl),
        'raw_payload' => $payload,
    ];
}

function sendNonGreenReport(array $results): void
{
    $nonGreen = array_values(array_filter($results, static fn(array $row): bool => ($row['status'] ?? 'green') !== 'green'));
    if ($nonGreen === []) {
        return;
    }

    $lines = ['Non-green server report:', ''];
    foreach ($nonGreen as $row) {
        $lines[] = 'Status: ' . strtoupper((string) $row['status']);
        $lines[] = 'URL: ' . (string) ($row['url'] ?? '');
        $lines[] = 'Hostname: ' . ((string) ($row['hostname'] ?? '') !== '' ? (string) $row['hostname'] : 'N/A');
        $lines[] = 'Internal IP: ' . ((string) ($row['internal_ip'] ?? '') !== '' ? (string) $row['internal_ip'] : 'N/A');
        $lines[] = 'HTTP Code: ' . (string) ($row['http_code'] ?? '');
        $lines[] = 'repl: ' . json_encode($row['repl'] ?? null);
        $lines[] = 'Issue active since: ' . ((string) ($row['issue_active_since'] ?? '') !== '' ? (string) $row['issue_active_since'] : 'N/A');
        $lines[] = 'Issue duration (seconds): ' . (string) ($row['issue_duration_seconds'] ?? 0);
        $lines[] = 'Instruction: ' . ((string) ($row['instruction'] ?? '') !== '' ? (string) $row['instruction'] : 'N/A');
        $lines[] = 'Error: ' . ((string) ($row['curl_error'] ?? '') !== '' ? (string) $row['curl_error'] : 'N/A');
        $lines[] = 'Checked at: ' . (string) ($row['checked_at'] ?? '');
        $lines[] = str_repeat('-', 40);
    }

    $subject = sprintf('[Server Report] %d non-green server(s)', count($nonGreen));
    @mail('it@posconnect', $subject, implode(PHP_EOL, $lines));
}

function runChecks(bool $sendReportEmail = true): array
{
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
    }

    if ($sendReportEmail) {
        sendNonGreenReport($results);
    }

    $statusPayload = [
        'checked_at' => date(DATE_ATOM),
        'check_interval_seconds' => $config['check_interval_seconds'],
        'alert_email' => 'it@posconnect',
        'results' => $results,
    ];

    saveLastStatus($statusPayload);
    return $statusPayload;
}
