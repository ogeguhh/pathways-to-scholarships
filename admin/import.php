<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/import_reader.php';
$messages=[]; $results=[];
$maxFile=5*1024*1024; $maxFiles=10;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verify_csrf($_POST['csrf_token']??null)) {
        http_response_code(403); $messages[]='Invalid security token. Reload the page and try again.';
    } elseif (empty($_FILES['scholarship_files']['name']) || !is_array($_FILES['scholarship_files']['name'])) {
        $messages[]='Choose one or more files.';
    } else {
        $files=$_FILES['scholarship_files'];
        if (count($files['name'])>$maxFiles) $messages[]='Maximum 10 files per upload.';
        else {
            // Local connection is intentionally excluded from Git. See SETUP_ITERATION3.md.
            if (!is_file(__DIR__.'/includes/db.php')) {
                $messages[]='Local database connection missing: includes/db.php';
            } else {
                require_once __DIR__.'/includes/db.php';
                foreach ($files['name'] as $i=>$name) {
                    $name=basename((string)$name);
                    $result=['file'=>$name,'status'=>'Rejected','count'=>0,'student'=>'','detail'=>''];
                    try {
                        if (($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (PHP error code '.($files['error'][$i]??'?').').');
                        $size=(int)$files['size'][$i];
                        if ($size<1 || $size>$maxFile) throw new RuntimeException('File must be between 1 byte and 5 MB.');
                        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
                        if (!in_array($ext,['csv','xlsx'],true)) throw new RuntimeException('Only .csv and .xlsx files are accepted.');
                        $path=(string)$files['tmp_name'][$i];
                        if (!is_uploaded_file($path)) throw new RuntimeException('Invalid uploaded file.');
                        if ($ext==='xlsx') {
                            $fh=fopen($path,'rb'); $signature=fread($fh,4); fclose($fh);
                            if ($signature!=="PK\x03\x04") throw new RuntimeException('Invalid .xlsx file type.');
                        } else {
                            $fh=fopen($path,'rb'); $sample=fread($fh,2048); fclose($fh);
                            if (str_contains($sample,"\0")) throw new RuntimeException('Invalid CSV file type.');
                        }
                        $rows=import_read_rows($path,$ext);
                        if (!$rows) throw new RuntimeException('No rows found.');
                        import_check_headers(array_shift($rows));
                        $student=import_student_from_filename($name);
                        if ($student===null) throw new RuntimeException('Rename file LastName_FirstName.csv or LastName_FirstName.xlsx.');
                        $count=0;
                        foreach ($rows as $row) {
                            if (count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0) continue;
                            if (count($row)!==29) throw new RuntimeException('A data row does not have 29 columns.');
                            $count++;
                        }
                        if ($count===0) throw new RuntimeException('No scholarship records found.');
                        $pdo->beginTransaction();
                        try {
                            $stmt=$pdo->prepare("INSERT INTO import_batches (uploaded_by,file_name,contributing_student,batch_status,total_rows,ready_rows,warning_rows,duplicate_rows,error_rows) VALUES (NULL,?,?, 'staged',?,0,0,0,0)");
                            $stmt->execute([$name,$student,$count]);
                            $batchId=(int)$pdo->lastInsertId();
                            $stmt=$pdo->prepare("INSERT INTO import_staging_rows (import_batch_id,row_number,raw_data,validation_status,validation_message) VALUES (?,?,?,'Pending','Awaiting cleaning and validation')");
                            $line=1;
                            foreach ($rows as $row) {
                                $line++;
                                if (count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0) continue;
                                $data=array_combine(import_expected_headers(),$row);
                                $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
                                $stmt->execute([$batchId,$line,$json]);
                            }
                            $pdo->commit();
                        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                        $result=['file'=>$name,'status'=>'Staged','count'=>$count,'student'=>$student,'detail'=>$count===20?'20 scholarships received.':'Expected 20 scholarships; received '.$count.'. Review student submission.'];
                    } catch (Throwable $e) {
                        $result['detail']=$e->getMessage();
                    }
                    $results[]=$result;
                }
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Scholarship Import | Pathways to Scholarships</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:950px">
<a href="index.php" class="text-decoration-none">&larr; Home</a>
<h1 class="mt-3">Import scholarship files</h1>
<p class="text-muted">Iteration 3: template downloads, upload, and 20-scholarship count check. Uploaded rows are staged for the team's validation pipeline; they are not yet published as scholarships.</p>
<section class="card mb-4"><div class="card-body"><h2 class="h5">1. Download a template</h2>
<a class="btn btn-outline-primary me-2" href="templates/scholarship_import_template.xlsx" download>Download Excel template</a>
<a class="btn btn-outline-secondary" href="templates/scholarship_import_template.csv" download>Download CSV template</a>
<p class="small text-muted mt-2 mb-0">Keep all 29 column headings unchanged. Use a filename like LastName_FirstName.csv.</p></div></section>
<section class="card mb-4"><div class="card-body"><h2 class="h5">2. Upload files</h2>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label class="form-label" for="scholarship_files">CSV or Excel files (up to 10, max 5 MB each)</label>
<input class="form-control mb-3" type="file" name="scholarship_files[]" id="scholarship_files" accept=".csv,.xlsx" multiple required>
<button class="btn btn-primary" type="submit">Upload to staging</button>
</form></div></section>
<?php foreach ($messages as $message): ?><div class="alert alert-warning"><?= e($message) ?></div><?php endforeach; ?>
<?php if ($results): ?><section class="card"><div class="card-body"><h2 class="h5">3. Upload results</h2>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>File</th><th>Student</th><th>Rows</th><th>Status</th><th>Details</th></tr></thead><tbody>
<?php foreach ($results as $r): ?><tr><td><?= e($r['file']) ?></td><td><?= e($r['student']) ?></td><td><?= e($r['count']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['detail']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></section><?php endif; ?>
</main></body></html>
