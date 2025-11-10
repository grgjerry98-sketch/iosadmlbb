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

// get counts
$downloadClicks = (int)($data['actions']['complete_download_click'] ?? 0);
$cornerClicks = (int)($data['actions']['corner_tracker_click'] ?? 0);
$total = (int)($data['total'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>TaskStation Admin — Click Stats</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#0f1720;color:#e8f4f8;padding:30px;}
  .card{background:#081018;border:1px solid rgba(255,255,255,0.08);padding:20px;border-radius:10px;margin-bottom:16px;}
  h1{color:#00c2a8;}
  h2{color:#ffaa2b;}
  p{font-size:16px;}
</style>
</head>
<body>
  <h1>TaskStation Admin Panel</h1>
  
  <div class="card">
    <h2>Summary</h2>
    <p>✅ Users have clicked the <strong>“Complete &amp; Download”</strong> button <strong><?php echo $downloadClicks; ?></strong> times.</p>
    <p>📊 The hidden corner button was clicked <strong><?php echo $cornerClicks; ?></strong> times.</p>
    <p>📁 Total recorded actions: <strong><?php echo $total; ?></strong></p>
  </div>

  <div class="card">
    <h2>By Action</h2>
    <ul>
      <?php foreach (($data['actions'] ?? []) as $act => $c): ?>
        <li><strong><?php echo h($act); ?></strong> — <?php echo (int)$c; ?> times</li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card">
    <h2>Recent Events</h2>
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;color:#cde;">
      <thead><tr><th>#</th><th>Time</th><th>Action</th><th>IP</th><th>Meta</th></tr></thead>
      <tbody>
      <?php
        $logs = array_reverse($data['logs'] ?? []); // newest first
        $i=0;
        foreach ($logs as $row) {
          $i++;
          if ($i>100) break;
          echo "<tr>";
          echo "<td>{$i}</td>";
          echo "<td>".h($row['ts'] ?? '')."</td>";
          echo "<td>".h($row['action'] ?? '')."</td>";
          echo "<td>".h($row['ip'] ?? '')."</td>";
          $meta = json_encode($row['meta'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          echo "<td>".h($meta)."</td>";
          echo "</tr>";
        }
      ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <form method="post" onsubmit="return confirm('Do you really want to reset all logs?')">
      <input type="hidden" name="token" value="<?php echo h($SECRET_TOKEN); ?>">
      <button type="submit" name="action" value="reset" style="padding:10px 16px;background:#ff6b6b;color:#fff;border:0;border-radius:6px;cursor:pointer;">Reset Logs</button>
    </form>
    <?php
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['token'] ?? '') === $SECRET_TOKEN && ($_POST['action'] ?? '') === 'reset') {
        @unlink($DATA_FILE);
        echo "<p style='color:salmon;margin-top:10px;'>Logs reset successfully. Reload this page.</p>";
      }
    ?>
  </div>
</body>
</html>
