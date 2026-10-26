<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/security.php';
require_role('Administrator');
require_once __DIR__ . '/../includes/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('db.php must define $pdo.');
$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batchId || $batchId < 1) { http_response_code(400); exit('A valid batch_id is required.'); }
$batchStmt = $pdo->prepare('SELECT * FROM import_batches WHERE import_batch_id = ?');
$batchStmt->execute([$batchId]);
$batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) { http_response_code(404); exit('Import batch not found.'); }
$rowsStmt = $pdo->prepare('SELECT staging_row_id,row_number,raw_data,validation_status,validation_message,duplicate_scholarship_id FROM import_staging_rows WHERE import_batch_id = ? ORDER BY row_number,staging_row_id');
$rowsStmt->execute([$batchId]);
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Import Validation Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5">
<a href="import.php">&larr; Back to import</a><h1 class="mt-3">Scholarship validation report</h1>
<p><strong>Batch #<?= (int)$batchId ?></strong> &middot; File: <?= e($batch['file_name']) ?> &middot; Contributor: <?= e((string)$batch['contributing_student']) ?> &middot; Uploaded: <?= e((string)$batch['imported_at']) ?></p>
<div class="row g-2 mb-4">
<?php foreach (['total_rows'=>'Total','ready_rows'=>'Ready','warning_rows'=>'Warning','duplicate_rows'=>'Duplicate','error_rows'=>'Error'] as $key=>$label): ?>
<div class="col-6 col-md"><div class="card"><div class="card-body"><div class="small text-muted"><?= e($label) ?></div><div class="fs-4 fw-bold"><?= (int)$batch[$key] ?></div></div></div></div>
<?php endforeach; ?></div>
<div class="alert alert-info">This is a staging review only. No scholarships have been published. Batch commit and rollback (FR-IMP-08/10) must be implemented separately.</div>
<div class="table-responsive"><table class="table table-bordered table-striped align-middle"><thead><tr><th>Source row</th><th>Scholarship</th><th>Official URL</th><th>Status</th><th>Reason</th></tr></thead><tbody>
<?php foreach ($rows as $row): $data = json_decode((string)$row['raw_data'], true); $data = is_array($data) ? $data : []; $status = (string)$row['validation_status']; ?>
<tr><td><?= (int)$row['row_number'] ?></td><td><?= e((string)($data['Scholarship name'] ?? '')) ?></td><td class="text-break"><?= e((string)($data['Official scholarship URL'] ?? '')) ?></td>
<td><span class="badge <?= $status==='Ready'?'bg-success':($status==='Warning'?'bg-warning text-dark':($status==='Duplicate'?'bg-secondary':'bg-danger')) ?>"><?= e($status) ?></span></td>
<td><?= e((string)$row['validation_message']) ?><?php if ($row['duplicate_scholarship_id']): ?> (Live ID <?= (int)$row['duplicate_scholarship_id'] ?>)<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
</main></body></html>
