<?php

require_once __DIR__ . '/middleware/customer.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int)$_SESSION['user_id'];


$type  = isset($_GET['type'])  ? strtolower(trim($_GET['type'])) : '';
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end   = isset($_GET['end'])   ? trim($_GET['end'])   : '';

$allowedTypes = ['', 'call', 'sms', 'data'];
if (!in_array($type, $allowedTypes, true)) $type = '';


$sql = "SELECT record_id, type, amount, recorded_at
        FROM usage_records
        WHERE user_id = :uid";
$params = [':uid' => $userId];

if ($type !== '') { $sql .= " AND type = :t";      $params[':t'] = $type; }
if ($start)       { $sql .= " AND DATE(recorded_at) >= :s"; $params[':s'] = $start; }
if ($end)         { $sql .= " AND DATE(recorded_at) <= :e"; $params[':e'] = $end; }

$sql .= " ORDER BY recorded_at DESC LIMIT 1000";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


$totals = ['call'=>0.0,'sms'=>0.0,'data'=>0.0];
foreach ($rows as $r) {
  $t = strtolower($r['type']);
  if (isset($totals[$t])) $totals[$t] += (float)$r['amount'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Usage</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#f6f8fb; --card:#fff; --ring:#e5e7eb; --ink:#1f2937; --muted:#6b7280;
      --brand:#0d6efd; --brand-2:#0b5ed7;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,Segoe UI,Arial,sans-serif}
    main.container{max-width:1100px;margin:0 auto;padding:18px}
    h1{margin:8px 0 16px;font-size:28px}
    .muted{color:var(--muted)}
    .card{background:#fff;border:1px solid var(--ring);border-radius:12px;padding:14px;box-shadow:0 3px 10px rgba(0,0,0,.03)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
    .kpi{background:#fff;border:1px solid var(--ring);border-radius:12px;padding:12px}
    .kpi .v{font-weight:800;font-size:20px}
    .btn{display:inline-block;padding:10px 14px;border:1px solid var(--ring);border-radius:10px;background:#fff;text-decoration:none;color:var(--ink);font-weight:700}
    .btn:hover{background:#f9fafb}
    .btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 4px 12px rgba(13,110,253,.18)}
    .btn.primary:hover{background:var(--brand-2)}
    form.filters{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0}
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--ring);border-radius:12px;overflow:hidden}
    th,td{padding:12px;border-bottom:1px solid var(--ring);text-align:left}
    thead th{background:#f8fafc;font-weight:800}
    .right{text-align:right}
  </style>
</head>
<body>
  <?php include __DIR__ . '/customer_navbar.php'; ?>

  <main class="container">
    <h1>My Usage</h1>

    
    <form method="get" class="filters card">
      <div>
        <label class="muted">Type</label><br>
        <select name="type">
          <option value=""       <?= $type===''?'selected':'' ?>>All</option>
          <option value="call"   <?= $type==='call'?'selected':'' ?>>Calls (minutes)</option>
          <option value="sms"    <?= $type==='sms'?'selected':'' ?>>SMS (count)</option>
          <option value="data"   <?= $type==='data'?'selected':'' ?>>Data (MB)</option>
        </select>
      </div>
      <div>
        <label class="muted">Start</label><br>
        <input type="date" name="start" value="<?= htmlspecialchars($start) ?>">
      </div>
      <div>
        <label class="muted">End</label><br>
        <input type="date" name="end" value="<?= htmlspecialchars($end) ?>">
      </div>
      <div style="align-self:end">
        <button class="btn primary" type="submit">Apply</button>
        <a class="btn" href="customer-usage.php">Reset</a>
      </div>
    </form>

    
    <section class="grid">
      <div class="kpi"><div class="muted">Calls (min)</div><div class="v"><?= number_format($totals['call'], 2) ?></div></div>
      <div class="kpi"><div class="muted">SMS (count)</div><div class="v"><?= number_format($totals['sms'], 0) ?></div></div>
      <div class="kpi"><div class="muted">Data (MB)</div><div class="v"><?= number_format($totals['data'], 2) ?></div></div>
      <div class="kpi"><div class="muted">Rows</div><div class="v"><?= number_format(count($rows)) ?></div></div>
    </section>

    
    <div class="card" style="padding:0;margin-top:12px;">
      <table>
        <thead>
          <tr>
            <th style="width:110px;">Record #</th>
            <th style="width:150px;">Type</th>
            <th style="width:160px;">Amount</th>
            <th>Recorded At</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="muted" style="padding:18px">No usage found for the selected filter.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['record_id'] ?></td>
            <td><?= htmlspecialchars(ucfirst($r['type'])) ?></td>
            <td class="right">
              <?php
                $fmt = strtolower($r['type']) === 'sms' ? 0 : 2;
                echo number_format((float)$r['amount'], $fmt);
              ?>
            </td>
            <td><?= htmlspecialchars($r['recorded_at']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
