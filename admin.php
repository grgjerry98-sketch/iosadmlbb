<?php
// admin.php - simple protected viewer for clicks.json
header('Content-Type: text/html; charset=utf-8'); // ensure browser interprets as UTF-8

$SECRET_TOKEN = 'REPLACE_WITH_STRONG_TOKEN'; // same token as track.php
$DATA_FILE = __DIR__ . '/../data/clicks.json'; // same as track.php

$token = $_GET['token'] ?? '';
if ($token !== $SECRET_TOKEN) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$data = ['total' => 0, 'actions' => [], 'logs' => []];
if (file_exists($DATA_FILE)) {
    $txt = @file_get_contents($DATA_FILE);
    $tmp = json_decode($txt, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $data = $tmp;
    }
}

// helper for safe escaping
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Click tracker admin</title>
<style>
  body{font-family:system-ui,Arial;background:#071018;color:#dff6ef;padding:18px}
  .card{background:#041018;border:1px solid rgba(255,255,255,0.03);padding:12px;border-radius:8px;margin-bottom:12px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.03);text-align:left}
  .mono{font-family:monospace;font-size:13px;color:#9aa7b2}
</style>
</head>
<body>
  <h2>TaskStation â€” Click stats</h2>
  <div class="card">
    <div><strong>Total events:</strong> <?php echo h($data['total'] ?? 0); ?></div>
    <div style="margin-top:6px"><strong>By action:</strong></div>
    <ul>
      <?php foreach (($data['actions'] ?? []) as $act => $c): ?>
        <li><?php echo h($act); ?> â€” <?php echo (int)$c; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card">
    <h3>Recent events (<?php echo count($data['logs'] ?? []); ?>)</h3>
    <table>
      <thead><tr><th>#</th><th>Time</th><th>Action</th><th>IP</th><th>UA</th><th>Meta</th></tr></thead>
      <tbody>
      <?php
        $logs = array_reverse($data['logs'] ?? []); // show newest first
        $i = 0;
        foreach ($logs as $row) {
          $i++;
          if ($i > 200) break;
          echo "<tr>";
          echo "<td>" . $i . "</td>";
          echo "<td class='mono'>" . h($row['ts'] ?? '') . "</td>";
          echo "<td>" . h($row['action'] ?? '') . "</td>";
          echo "<td>" . h($row['ip'] ?? '') . "</td>";
          echo "<td title=\"" . h($row['ua'] ?? '') . "\">" . h(mb_substr($row['ua'] ?? '', 0, 30, 'UTF-8')) . "</td>";
          // meta: print compact JSON with unescaped unicode for readability
          $metaJson = json_encode($row['meta'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          echo "<td class='mono'>" . h($metaJson) . "</td>";
          echo "</tr>";
        }
      ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <form method="post" onsubmit="return confirm('Reset log file? This is permanent.')">
      <input type="hidden" name="token" value="<?php echo h($SECRET_TOKEN); ?>">
      <button type="submit" name="action" value="reset">Reset logs</button>
    </form>
    <?php
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['token'] ?? '') === $SECRET_TOKEN && ($_POST['action'] ?? '') === 'reset') {
        @unlink($DATA_FILE);
        echo "<p style='color:salmon'>Logs reset. Reload page.</p>";
      }
    ?>
  </div>
</body>
</html>
