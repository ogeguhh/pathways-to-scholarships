<?php
declare(strict_types=1);
/** FR-IMP-06: URL-based duplicate checks (not name-based). */
function import_normalize_url(string $url): ?string {
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $parts = parse_url($url);
    if (!$parts || !isset($parts['host'], $parts['scheme'])) return null;
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http','https'], true)) return null;
    $host = strtolower(rtrim($parts['host'], '.'));
    $port = isset($parts['port']) && !(($scheme==='http' && $parts['port']===80) || ($scheme==='https' && $parts['port']===443)) ? ':' . $parts['port'] : '';
    $path = rtrim($parts['path'] ?? '', '/');
    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/^(utm_|fbclid$|gclid$|msclkid$|mc_cid$|mc_eid$|ref$|source$)/i', (string)$key)) unset($query[$key]);
        }
        ksort($query);
    }
    // Treat http and https as equivalent for identifying scholarship pages.
    return $host . $port . $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}
/** Return normalized URL => live scholarship ID, including older records with unnormalized URLs. */
function import_live_url_map(PDO $pdo): array {
    $map = [];
    $stmt = $pdo->query("SELECT scholarship_id, official_url FROM scholarships WHERE status = 'active'");
    foreach ($stmt as $record) {
        $key = import_normalize_url((string)$record['official_url']);
        if ($key !== null && !isset($map[$key])) $map[$key] = (int)$record['scholarship_id'];
    }
    return $map;
}
/** Include previously staged files, not just the files in the current request. */
function import_staged_url_map(PDO $pdo, array $excludedBatchIds = []): array {
    $map = [];
    $stmt = $pdo->query("SELECT s.staging_row_id, s.import_batch_id, s.raw_data, s.validation_status FROM import_staging_rows s JOIN import_batches b ON b.import_batch_id = s.import_batch_id WHERE b.batch_status NOT IN ('rolled_back', 'rejected') AND s.validation_status IN ('Ready','Warning','Pending') ORDER BY s.staging_row_id");
    foreach ($stmt as $row) {
        if (in_array((int)$row['import_batch_id'], $excludedBatchIds, true)) continue;
        $raw = json_decode((string)$row['raw_data'], true);
        if (!is_array($raw)) continue;
        $key = import_normalize_url((string)($raw['Official scholarship URL'] ?? ''));
        if ($key !== null && !isset($map[$key])) $map[$key] = (int)$row['staging_row_id'];
    }
    return $map;
}
