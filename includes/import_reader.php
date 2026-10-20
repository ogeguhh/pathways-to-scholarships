<?php
declare(strict_types=1);

/**
 * Iteration 3 Requirements 1–3:
 * Read CSV and Excel scholarship files.
 * Preserve raw values for the team's validation module.
 */
declare(strict_types=1);

/**
 * Iteration 3 Requirements 1–3:
 * Read CSV and Excel scholarship files.
 * Preserve raw values for the team's validation module.
 */

function import_expected_headers(): array {
    $path = __DIR__ . '/../templates/scholarship_import_template.csv';
    $f = fopen($path, 'rb');

    if (!$f) {
        throw new RuntimeException('Missing CSV template.');
    }

    try {
        $headers = fgetcsv($f, 0, ',', '"', '');
    } finally {
        fclose($f);
    }

    if (!$headers) {
        throw new RuntimeException('Template is empty.');
    }

    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

    return $headers;
}

function import_csv_rows(string $path): array {
    $fh = fopen($path, 'rb');

    if (!$fh) {
        throw new RuntimeException('Could not open CSV.');
    }

    $rows = [];

    try {
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }

            $rows[] = $row;

            if (count($rows) > 5001) {
                throw new RuntimeException('Too many rows (maximum 5,000 scholarships).');
            }
        }
    } finally {
        fclose($fh);
    }

    if ($rows) {
        $rows[0][0] = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            (string)$rows[0][0]
        );
    }

    return $rows;
}

function import_xlsx_rows(string $path): array {
    if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
        throw new RuntimeException(
            'Enable PHP ZIP and SimpleXML extensions to import Excel files.'
        );
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Invalid Excel workbook.');
    }

    try {
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $total += $stat['size'];

            if ($total > 30 * 1024 * 1024) {
                throw new RuntimeException(
                    'Excel workbook expands beyond 30 MB.'
                );
            }
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        // Read shared strings.
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml !== false) {
            $xml = simplexml_load_string(
                $sharedXml,
                'SimpleXMLElement',
                LIBXML_NONET
            );

            if ($xml === false) {
                throw new RuntimeException('Invalid Excel shared strings.');
            }

            $xml->registerXPathNamespace('x', $namespace);

            foreach ($xml->xpath('//x:si') as $si) {
                $si->registerXPathNamespace('x', $namespace);

                $str = '';

                foreach ($si->xpath('.//x:t') as $t) {
                    $str .= (string)$t;
                }

                $shared[] = $str;
            }
        }

        // Read the first worksheet.
        $data = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($data === false) {
            throw new RuntimeException('Excel first worksheet is missing.');
        }

        $xml = simplexml_load_string(
            $data,
            'SimpleXMLElement',
            LIBXML_NONET
        );

        if ($xml === false) {
            throw new RuntimeException('Invalid Excel worksheet.');
        }

        $xml->registerXPathNamespace('x', $namespace);

        $rows = [];

        foreach ($xml->xpath('//x:sheetData/x:row') as $row) {
            $row->registerXPathNamespace('x', $namespace);

            $cells = [];

            foreach ($row->xpath('./x:c') as $cell) {
                $cell->registerXPathNamespace('x', $namespace);

                $ref = (string)$cell['r'];

                if (!preg_match('/^([A-Z]+)\d+$/', $ref, $m)) {
                    continue;
                }

                $idx = 0;

                foreach (str_split($m[1]) as $letter) {
                    $idx = $idx * 26 + ord($letter) - 64;
                }

                $idx--;

                if ($idx >= 29) {
                    continue;
                }

                $type = (string)$cell['t'];
                $v = $cell->xpath('./x:v');

                if ($type === 'inlineStr') {
                    $t = $cell->xpath('.//x:t');

                    $value = implode('', array_map('strval', $t));
                } else {
                    $value = isset($v[0]) ? (string)$v[0] : '';

                    if ($type === 's') {
                        $value = $shared[(int)$value] ?? '';
                    }
                }

                $cells[$idx] = $value;
            }

            if ($cells) {
    // Always return all 29 columns, even when some cells are empty.
    $values = array_fill(0, 29, '');

    foreach ($cells as $idx => $value) {
        $values[$idx] = $value;
    }

                $rows[] = $values;

                if (count($rows) > 5001) {
                    throw new RuntimeException(
                        'Too many rows (maximum 5,000 scholarships).'
                    );
                }
            }
        }

        return $rows;

    } finally {
        $zip->close();
    }
}

function import_read_rows(string $path, string $ext): array {
    if ($ext === 'csv') {
        return import_csv_rows($path);
    }

    if ($ext === 'xlsx') {
        return import_xlsx_rows($path);
    }

    throw new RuntimeException('Unsupported file type.');
}

function import_check_headers(array $actual): void {
    $expected = import_expected_headers();

    $actual = array_map(
        fn($v) => trim((string)$v),
        $actual
    );

    if ($actual !== $expected) {
        throw new RuntimeException(
            'The 29 column headers must match the downloadable template exactly and in order.'
        );
    }
}

function import_student_from_filename(string $name): ?string {
    $stem = pathinfo($name, PATHINFO_FILENAME);

    if (preg_match(
        '/^([A-Za-z][A-Za-z\x27-]*)_([A-Za-z][A-Za-z\x27-]*)(?:[_ -].*)?$/',
        $stem,
        $m
    )) {
        return $m[1] . '_' . $m[2];
    }

    return null;
}
