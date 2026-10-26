<?php
declare(strict_types=1);

/*
 * Iteration 3 - Duplicate Detection
 *
 * Standardizes scholarship URLs so we can compare
 * them and identify duplicate scholarship records.
 */

/**
 * Normalize a scholarship URL.
 *
 * Returns null when the URL is invalid.
 */
function import_normalize_url(string $url): ?string
{
    // Remove unnecessary spaces.
    $url = trim($url);

    // Only accept valid HTTP and HTTPS URLs.
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    // Split the URL into its individual parts.
    $parts = parse_url($url);

    if ($parts === false || empty($parts['host'])) {
        return null;
    }

    // Standardize the protocol and hostname.
    $scheme = strtolower($parts['scheme'] ?? '');

    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $host = strtolower($parts['host']);

    // Remove trailing slashes from the path.
    $path = rtrim($parts['path'] ?? '', '/');

    // Keep non-tracking query parameters.
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);

        // Remove common tracking parameters.
        foreach (array_keys($query) as $key) {
            if (
                str_starts_with(strtolower($key), 'utm_') ||
                in_array(strtolower($key), ['fbclid', 'gclid'], true)
            ) {
                unset($query[$key]);
            }
        }

        // Sort remaining parameters consistently.
        ksort($query);
    }

    // Rebuild the standardized URL.
    $normalized = $scheme . '://' . $host;

    if (isset($parts['port'])) {
        $normalized .= ':' . $parts['port'];
    }

    $normalized .= $path;

    if ($query !== []) {
        $normalized .= '?' . http_build_query($query);
    }

    // URL fragments are intentionally removed.
    return $normalized;
}
