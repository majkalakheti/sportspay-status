<?php
declare(strict_types=1);

require_once __DIR__ . '/../checker.php';

header('Content-Type: application/json');

$statusPayload = runChecks(false);
echo json_encode($statusPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
