<?php
/**
 * TOE Debug – verify Tesseract is reachable and Hebrew tessdata is available via PHP exec()
 */
header('Content-Type: text/html; charset=utf-8');

$cfg_path = __DIR__ . '/toe_config.json';
$cfg      = json_decode(file_get_contents($cfg_path), true);

$tess_path   = $cfg['tesseract_path'];
$tessdata    = $cfg['tessdata_dir'] ?? '';
$lang        = $cfg['tesseract_lang'] ?? 'heb+eng';
$oem         = isset($cfg['tesseract_oem']) ? (int)$cfg['tesseract_oem'] : null;
$crops_dir   = realpath(__DIR__ . '/' . $cfg['crops_dir_test']);

function run(string $cmd): array {
    exec($cmd . ' 2>&1', $out, $code);
    return ['cmd' => $cmd, 'output' => $out, 'code' => $code];
}

$tests = [];

// 1. tesseract --version
$tests[] = ['title' => 'tesseract --version', 'result' => run(escapeshellarg($tess_path) . ' --version')];

// 2. tesseract --list-langs  (no tessdata-dir override)
$tests[] = ['title' => 'tesseract --list-langs  (no --tessdata-dir)',
            'result' => run(escapeshellarg($tess_path) . ' --list-langs')];

// 3. Does heb.traineddata actually exist on disk?
$heb_file = rtrim($tessdata, '/\\') . '/heb.traineddata';
$tests[] = ['title' => 'heb.traineddata exists on disk?',
            'result' => ['cmd' => 'file_exists(' . $heb_file . ')', 'output' => [file_exists($heb_file) ? '✅ YES – ' . $heb_file : '❌ NOT FOUND – ' . $heb_file], 'code' => 0]];

// 4. PHP environment: TESSDATA_PREFIX env var
$env_val = getenv('TESSDATA_PREFIX');
$tests[] = ['title' => 'TESSDATA_PREFIX env var (as seen by PHP)',
            'result' => ['cmd' => 'getenv(TESSDATA_PREFIX)', 'output' => [$env_val !== false ? $env_val : '(not set)'], 'code' => 0]];

// 5. Current working directory of PHP
$tests[] = ['title' => 'PHP cwd', 'result' => ['cmd' => 'getcwd()', 'output' => [getcwd()], 'code' => 0]];

// ── LIVE OCR TEST ─────────────────────────────────────────────────────────────
// Find first Table PNG in crops dir and run Tesseract on it via PHP exec()
// using stdout (exactly like the working CMD test), then show raw TSV lines.

$live_test = null;
if ($crops_dir && is_dir($crops_dir)) {
    foreach (scandir($crops_dir) as $f) {
        if (preg_match('/_Table\d+\.png$/i', $f) && !preg_match('/\s*\(\d+\)\.png$/i', $f)) {
            $img = $crops_dir . '/' . $f;

            $lang_safe = preg_replace('/[^A-Za-z0-9+_]/', '', $lang);
            $oem_flag  = ($oem !== null) ? ' --oem ' . $oem : '';
            $td_flag   = $tessdata ? ' --tessdata-dir ' . escapeshellarg($tessdata) : '';

            // Mirror the working CMD style: stdout + same flags
            $cmd = escapeshellarg($tess_path)
                 . ' ' . escapeshellarg($img)
                 . ' stdout'
                 . ' -l ' . $lang_safe
                 . ' --psm 6'
                 . $oem_flag
                 . $td_flag
                 . ' tsv 2>&1';

            exec($cmd, $raw_out, $code);

            // Split header + first 10 data lines for display
            $preview = array_slice($raw_out, 0, 12);

            $live_test = [
                'image' => $f,
                'cmd'   => $cmd,
                'code'  => $code,
                'lines' => $preview,
                'total' => count($raw_out),
            ];
            break;
        }
    }
}

?><!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>TOE Debug</title>
<style>
  body { font-family: Arial, sans-serif; font-size:14px; background:#f5f5f5; padding:20px; }
  h1   { font-size:1.4em; }
  h2   { font-size:1.1em; margin:24px 0 8px; border-bottom:2px solid #007bff; padding-bottom:4px; color:#007bff; }
  .card{ background:#fff; border:1px solid #ddd; border-radius:6px; padding:14px; margin-bottom:14px; }
  .cmd { background:#1e1e1e; color:#9cdcfe; font-family:monospace; font-size:12px; padding:6px 10px; border-radius:3px; margin-bottom:6px; word-break:break-all; }
  .out { background:#111; color:#ccc; font-family:monospace; font-size:12px; padding:8px 10px; border-radius:3px; white-space:pre-wrap; }
  .ok  { color:#28a745; font-weight:bold; }
  .err { color:#dc3545; font-weight:bold; }
  h3   { margin:0 0 8px; font-size:1em; }
  .heb { background:#111; color:#ffe; font-family:monospace; font-size:13px; padding:10px; border-radius:3px; white-space:pre-wrap; direction:ltr; }
</style>
</head>
<body>
<h1>TOE Debug – Tesseract from PHP exec()</h1>
<p style="color:#555">This page runs Tesseract commands via PHP <code>exec()</code> exactly as the TOE engine does.</p>

<h2>Environment checks</h2>
<?php foreach ($tests as $t): ?>
<div class="card">
  <h3><?= htmlspecialchars($t['title']) ?></h3>
  <div class="cmd"><?= htmlspecialchars($t['result']['cmd']) ?></div>
  <div class="out"><?= htmlspecialchars(implode("\n", $t['result']['output'])) ?></div>
  <p style="margin:6px 0 0">Exit code:
    <span class="<?= $t['result']['code'] === 0 ? 'ok' : 'err' ?>">
      <?= $t['result']['code'] ?>
    </span>
  </p>
</div>
<?php endforeach; ?>

<h2>Live OCR test – stdout mode (mirrors your working CMD command)</h2>
<?php if ($live_test): ?>
<div class="card">
  <h3>Image: <code><?= htmlspecialchars($live_test['image']) ?></code></h3>
  <div class="cmd"><?= htmlspecialchars($live_test['cmd']) ?></div>
  <p style="margin:4px 0">Exit code:
    <span class="<?= $live_test['code'] === 0 ? 'ok' : 'err' ?>"><?= $live_test['code'] ?></span>
    &nbsp;|&nbsp; Total output lines: <?= $live_test['total'] ?>
  </p>
  <p style="margin:4px 0 6px;color:#555;font-size:12px">First <?= count($live_test['lines']) ?> lines of raw TSV output — if Hebrew works from PHP, you should see Hebrew characters in the last column:</p>
  <div class="heb"><?= htmlspecialchars(implode("\n", $live_test['lines'])) ?></div>
</div>
<?php else: ?>
<div class="card" style="color:#888">
  No Table crop found in: <code><?= htmlspecialchars($crops_dir ?: 'directory not resolved') ?></code>
</div>
<?php endif; ?>

<p><a href="toe_start.php">← Back to TOE</a></p>
</body>
</html>
