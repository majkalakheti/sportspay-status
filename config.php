<?php
declare(strict_types=1);

const DEFAULT_CONFIG = [
    'check_interval_seconds' => 60,
    'servers' => [
        [
            'base_url' => 'https://svra.interpaypos.com',
            'hostname' => 'svra.interpaypos.com',
            'internal_ip' => '',
            'instruction' => '',
            'group' => 'CORE',
            'role' => 'P',
            'machine_type' => 'vm',
            'is_caddy' => true,
        ],
        [
            'base_url' => 'https://svrb.interpaypos.com',
            'hostname' => 'svrb.interpaypos.com',
            'internal_ip' => '',
            'instruction' => '',
            'group' => 'CORE',
            'role' => 'S',
            'machine_type' => 'vm',
            'is_caddy' => true,
        ],
        [
            'base_url' => 'https://paynow.interpaypos.com',
            'hostname' => 'paynow.interpaypos.com',
            'internal_ip' => '192.168.3.64',
            'instruction' => '',
            'group' => 'OZROG',
            'role' => 'P',
            'machine_type' => 'physical',
            'is_caddy' => false,
        ],
        [
            'base_url' => 'https://paypage.interpaypos.com',
            'hostname' => 'paypage.interpaypos.com',
            'internal_ip' => '192.168.3.65',
            'instruction' => '',
            'group' => 'OZROG',
            'role' => 'S',
            'machine_type' => 'vm',
            'is_caddy' => false,
        ],
        [
            'base_url' => 'https://payment.interpaypos.com',
            'hostname' => 'payment.interpaypos.com',
            'internal_ip' => '192.168.4.66',
            'instruction' => '',
            'group' => 'OZBELL',
            'role' => 'P',
            'machine_type' => 'vm',
            'is_caddy' => false,
        ],
        [
            'base_url' => 'https://payhost.interpaypos.com',
            'hostname' => 'payhost.interpaypos.com',
            'internal_ip' => '192.168.4.67',
            'instruction' => '',
            'group' => 'OZBELL',
            'role' => 'S',
            'machine_type' => 'vm',
            'is_caddy' => false,
        ],
        [
            'base_url' => 'https://checkout.interpaypos.com',
            'hostname' => 'checkout.interpaypos.com',
            'internal_ip' => '192.168.2.64',
            'instruction' => '',
            'group' => 'TORROG',
            'role' => 'P',
            'machine_type' => 'physical',
            'is_caddy' => false,
        ],
        [
            'base_url' => 'https://paynew.interpaypos.com',
            'hostname' => 'paynew.interpaypos.com',
            'internal_ip' => '192.168.2.65',
            'instruction' => '',
            'group' => 'TORROG',
            'role' => 'S',
            'machine_type' => 'vm',
            'is_caddy' => false,
        ],
        [
            'base_url' => 'https://testgate.interpaypos.com',
            'hostname' => 'testgate.interpaypos.com',
            'internal_ip' => '192.168.2.66',
            'instruction' => '',
            'group' => 'TORROG',
            'role' => 'T',
            'machine_type' => 'vm',
            'is_caddy' => false,
        ],
        [
            'base_url' => 'https://devgate.interpaypos.com',
            'hostname' => 'devgate.interpaypos.com',
            'internal_ip' => '192.168.2.67',
            'instruction' => '',
            'group' => 'TORROG',
            'role' => 'D',
            'machine_type' => 'vm',
            'is_caddy' => false,
        ],
    ],
];

const CONFIG_FILE = __DIR__ . '/data/config.json';
const STATUS_FILE = __DIR__ . '/data/status.json';
const INCIDENT_LOG_FILE = __DIR__ . '/data/incidents.log';

function ensureDataFiles(): void
{
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }

    if (!file_exists(CONFIG_FILE)) {
        file_put_contents(CONFIG_FILE, json_encode(DEFAULT_CONFIG, JSON_PRETTY_PRINT));
    }

    if (!file_exists(STATUS_FILE)) {
        file_put_contents(STATUS_FILE, json_encode([], JSON_PRETTY_PRINT));
    }

    if (!file_exists(INCIDENT_LOG_FILE)) {
        file_put_contents(INCIDENT_LOG_FILE, '');
    }
}

function getConfig(): array
{
    ensureDataFiles();
    return [
        'check_interval_seconds' => DEFAULT_CONFIG['check_interval_seconds'],
        'servers' => normalizeServers(DEFAULT_CONFIG['servers']),
    ];
}

function saveConfig(array $config): void
{
    ensureDataFiles();
    // Configuration is hardcoded for this dashboard mode.
    file_put_contents(CONFIG_FILE, json_encode([
        'note' => 'Configuration is hardcoded in config.php',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function getLastStatus(): array
{
    ensureDataFiles();
    $raw = file_get_contents(STATUS_FILE);
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

function saveLastStatus(array $status): void
{
    ensureDataFiles();
    file_put_contents(STATUS_FILE, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function normalizeServers(array $servers): array
{
    $normalized = [];

    foreach ($servers as $server) {
        if (is_string($server)) {
            $url = trim($server);
            if ($url === '') {
                continue;
            }
            $normalized[] = [
                'base_url' => $url,
                'hostname' => '',
                'internal_ip' => '',
                'instruction' => '',
                'group' => '',
                'role' => '',
                'machine_type' => 'vm',
                'is_caddy' => false,
            ];
            continue;
        }

        if (!is_array($server)) {
            continue;
        }

        $url = trim((string) ($server['base_url'] ?? ($server['url'] ?? '')));
        if ($url === '') {
            continue;
        }

        $normalized[] = [
            'base_url' => $url,
            'hostname' => trim((string) ($server['hostname'] ?? '')),
            'internal_ip' => trim((string) ($server['internal_ip'] ?? '')),
            'instruction' => trim((string) ($server['instruction'] ?? '')),
            'group' => trim((string) ($server['group'] ?? '')),
            'role' => trim((string) ($server['role'] ?? '')),
            'machine_type' => trim((string) ($server['machine_type'] ?? 'vm')),
            'is_caddy' => (bool) ($server['is_caddy'] ?? false),
        ];
    }

    return $normalized;
}

function appendIncidentLog(array $entry): void
{
    ensureDataFiles();
    file_put_contents(INCIDENT_LOG_FILE, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}
