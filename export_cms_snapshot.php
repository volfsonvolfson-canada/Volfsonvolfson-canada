<?php
/**
 * Export CMS text/UI-related rows from MySQL to JSON (for Git backup).
 *
 * Run where config.php can reach the database (SSH on hosting or PC with PHP):
 *   php export_cms_snapshot.php
 *
 * Writes:
 *   database/snapshots/cms_content_snapshot.json           (latest)
 *   database/snapshots/cms_content_snapshot_<ISO8601>.json (copy)
 */
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Run from CLI only: php export_cms_snapshot.php\n");
    exit(1);
}

require_once __DIR__ . '/config.php';

$outDir = __DIR__ . '/database/snapshots';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create directory: $outDir\n");
    exit(1);
}

function cmsTableExists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    return $r && $r->num_rows > 0;
}

/** @return array<int, array<string, mixed>>|null */
function cmsFetchAll(mysqli $conn, string $sql): ?array {
    $r = $conn->query($sql);
    if ($r === false) {
        return null;
    }
    $rows = [];
    while ($row = $r->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** Single-row tables keyed by id = 1 (site uses row id 1). */
$singleIdTables = [
    'content_settings',
    'rooms_settings',
    'room_cards_settings',
    'wellness_settings',
    'floorplan_settings',
    'homepage_settings',
    'room_pages_settings',
    'massage_settings',
    'special_settings',
    'about_settings',
    'retreat_settings',
];

$snapshot = [
    'exported_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'database' => $database,
    'tables' => [],
];

foreach ($singleIdTables as $table) {
    if (!cmsTableExists($conn, $table)) {
        continue;
    }
    $rows = cmsFetchAll($conn, "SELECT * FROM `$table` WHERE id = 1 LIMIT 1");
    if ($rows === null) {
        fwrite(STDERR, "Query failed for $table: {$conn->error}\n");
        continue;
    }
    $snapshot['tables'][$table] = $rows[0] ?? null;
}

$json = json_encode(
    $snapshot,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($json === false) {
    fwrite(STDERR, "JSON encode failed.\n");
    exit(1);
}

$latest = $outDir . '/cms_content_snapshot.json';
$stamp = $outDir . '/cms_content_snapshot_' . gmdate('Y-m-d\THis\Z') . '.json';

if (file_put_contents($latest, $json . "\n") === false) {
    fwrite(STDERR, "Cannot write: $latest\n");
    exit(1);
}
if (file_put_contents($stamp, $json . "\n") === false) {
    fwrite(STDERR, "Cannot write: $stamp\n");
    exit(1);
}

echo "OK: $latest\n";
echo "OK: $stamp\n";

$conn->close();
