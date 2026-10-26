<?php
declare(strict_types=1);
/** FR-IMP-04/05: validate a 29-column scholarship row; preserve the original row separately. */

function imp_clean(string $value): string {
    $value = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xEF\xBB\xBF"], ' ', $value);
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}
function imp_field(array $row, string $label): string {
    foreach ($row as $key => $value) {
        if (strcasecmp(imp_clean((string)$key), $label) === 0) return (string)$value;
    }
    return '';
}
function imp_state_codes(PDO $pdo): array {
    $map = ['national' => 'NA', 'na' => 'NA'];
    foreach ($pdo->query('SELECT state_code, state_name FROM states') as $state) {
        $map[strtolower($state['state_code'])] = strtoupper($state['state_code']);
        $map[strtolower($state['state_name'])] = strtoupper($state['state_code']);
    }
    return $map;
}
function imp_date(string $value): ?string {
    if ($value === '' || strcasecmp($value, 'rolling') === 0 || strcasecmp($value, 'n/a') === 0) return null;
    foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y', '!m-d-Y', '!n-j-Y'] as $format) {
        $d = DateTimeImmutable::createFromFormat($format, $value);
        if ($d && $d->format(ltrim($format, '!')) === $value) return $d->format('Y-m-d');
    }
    // Excel stores dates as serial numbers; accept only plausible serials.
    if (ctype_digit($value) && (int)$value >= 20000 && (int)$value <= 90000) {
        return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)$value . ' days')->format('Y-m-d');
    }
    return null;
}
function imp_amount(string $value): ?string {
    if ($value === '') return null;
    $v = str_replace(['$', ',', ' '], '', $value);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $v)) return null;
    return number_format((float)$v, 2, '.', '');
}
function imp_grades(string $value): ?string {
    if ($value === '') return null;
    $value = preg_replace('/\b(?:grades?|th|st|nd|rd)\b/i', '', $value) ?? $value;
    $value = str_replace(['–', '—'], '-', $value);
    $grades = [];
    foreach (preg_split('/[,;\/]+/', $value) as $part) {
        $part = trim($part);
        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})$/', $part, $m)) {
            if ((int)$m[1] > (int)$m[2]) return null;
            foreach (range((int)$m[1], (int)$m[2]) as $n) $grades[] = $n;
        } elseif (preg_match('/^\d{1,2}$/', $part)) $grades[] = (int)$part;
        else return null;
    }
    $grades = array_values(array_unique($grades)); sort($grades);
    foreach ($grades as $grade) if ($grade < 6 || $grade > 12) return null;
    return $grades ? implode(',', $grades) : null;
}
function imp_yes_no(string $value): ?string {
    $v = strtolower($value);
    if ($v === '' || in_array($v, ['unknown', 'unsure', 'n/a', 'not specified'], true)) return 'Unknown';
    if (in_array($v, ['yes', 'y', 'true', '1'], true)) return 'Yes';
    if (in_array($v, ['no', 'n', 'false', '0'], true)) return 'No';
    return null;
}
/** Returns ['clean'=>..., 'status'=>Ready|Warning|Error, 'reasons'=>...]. */
function import_validate_row(array $raw, array $stateMap): array {
    $clean = []; $errors = []; $warnings = [];
    foreach ($raw as $key => $value) $clean[$key] = imp_clean((string)$value);
    $name = imp_field($clean, 'Scholarship name');
    $provider = imp_field($clean, 'Scholarship provider/organization');
    $url = imp_field($clean, 'Official scholarship URL');
    if ($name === '') $errors[] = 'Scholarship name is required';
    if ($provider === '') $errors[] = 'Scholarship provider/organization is required';
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true) || !parse_url($url, PHP_URL_HOST)) {
        $errors[] = 'Official scholarship URL must be a valid http(s) URL';
    }
    $stateKey = 'State/region';
    $stateRaw = imp_field($clean, $stateKey);
    if ($stateRaw !== '') {
        $parts = preg_split('/\s*[,;|]+\s*/', $stateRaw);
        $codes = [];
        foreach ($parts as $part) {
            $code = $stateMap[strtolower(trim($part))] ?? null;
            if ($code === null) $errors[] = "Unrecognized state/region: $part";
            else $codes[] = $code;
        }
        $codes = array_values(array_unique($codes));
        $clean[$stateKey] = implode(',', $codes);
    } else $codes = [];
    $residency = imp_field($clean, 'Residency requirement');
    // Only infer multi-state eligibility when two or more state codes are explicitly listed.
    $residencyCodes = [];
    foreach (preg_split('/\s*[,;|]+\s*/', $residency) as $part) {
        $code = $stateMap[strtolower(trim($part))] ?? null;
        if ($code && $code !== 'NA') $residencyCodes[$code] = true;
    }
    foreach ($codes as $code) if ($code !== 'NA') $residencyCodes[$code] = true;
    $declared = strtolower(imp_field($clean, 'National vs. state/local'));
    $classification = in_array($declared, ['national', 'state/local', 'state-local', 'state', 'local'], true)
        ? ($declared === 'national' ? 'National' : 'State/Local') : null;
    if ($classification === null) $errors[] = 'National vs. state/local must be National or State/Local';
    if (count($residencyCodes) >= 2 || in_array('NA', $codes, true)) {
        if ($classification !== 'National') $warnings[] = 'Classification corrected to National based on multi-state/National eligibility';
        $classification = 'National';
    } elseif (count($residencyCodes) === 1) {
        if ($classification === 'National') $warnings[] = 'Classification corrected to State/Local based on single-state eligibility';
        $classification = 'State/Local';
    } else $warnings[] = 'Residency does not explicitly establish one-state versus multi-state eligibility; verify classification';
    $clean['National vs. state/local'] = $classification ?? '';
    $gradeKey = 'Eligible grade levels';
    $grade = imp_field($clean, $gradeKey);
    if ($grade === '') $errors[] = 'Eligible grade levels are required';
    else {
        $parsed = imp_grades($grade);
        if ($parsed === null) $errors[] = 'Eligible grades must be within 6–12 (e.g. 9-12)';
        else $clean[$gradeKey] = $parsed;
    }
    foreach (['Award amount', 'Maximum potential value'] as $key) {
        $value = imp_field($clean, $key);
        if ($value !== '') {
            // Support simple amount ranges for award amount.
            if ($key === 'Award amount' && preg_match('/^\s*(\$?[\d,.]+)\s*(?:-|–|to)\s*(\$?[\d,.]+)\s*$/i', $value, $m)) {
                $min = imp_amount($m[1]); $max = imp_amount($m[2]);
                if ($min === null || $max === null || (float)$min > (float)$max) $errors[] = 'Invalid award amount range';
                else $clean[$key] = $min . '-' . $max;
            } else {
                $amount = imp_amount($value);
                if ($amount === null) $errors[] = "Invalid amount: $key";
                else $clean[$key] = $amount;
            }
        } elseif ($key === 'Award amount') $warnings[] = 'Award amount is missing';
    }
    foreach (['Application open date', 'Application deadline', 'Verification date'] as $key) {
        $value = imp_field($clean, $key);
        if ($value === '') {
            if ($key === 'Verification date') $warnings[] = 'Verification date is missing';
            continue;
        }
        if ($key === 'Application deadline' && strtolower($value) === 'rolling') continue;
        $date = imp_date($value);
        if ($date === null) $errors[] = "Invalid date: $key";
        else $clean[$key] = $date;
    }
    foreach (['Renewable', 'Financial need required', 'Essay required', 'Recommendation required', 'Transcript/test score required', 'Community service requirement', 'Leadership requirement'] as $key) {
        $value = imp_yes_no(imp_field($clean, $key));
        if ($value === null) $errors[] = "Invalid Yes/No/Unknown value: $key";
        else $clean[$key] = $value;
    }
    $count = imp_field($clean, 'Number of awards');
    if ($count !== '' && (!ctype_digit($count) || (int)$count < 0)) $errors[] = 'Number of awards must be a nonnegative integer';
    $gpa = imp_field($clean, 'Minimum GPA');
    if ($gpa !== '' && (!is_numeric($gpa) || (float)$gpa < 0 || (float)$gpa > 4)) $errors[] = 'Minimum GPA must be between 0 and 4';
    $status = $errors ? 'Error' : ($warnings ? 'Warning' : 'Ready');
    return ['clean'=>$clean, 'status'=>$status, 'reasons'=>array_merge($errors, $warnings)];
}
