<?php

require_once __DIR__ . '/middleware/admin.php';
require_once __DIR__ . '/includes/db.php';

$users = $pdo->query("SELECT user_id, name, email FROM users WHERE role='customer' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$start  = $_GET['start'] ?? '';
$end    = $_GET['end'] ?? '';

if (isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $pdo->prepare("DELETE FROM usage_records WHERE record_id=?")->execute([$delId]);
    header("Location: usage_data.php?user_id=$userId&start=$start&end=$end");
    exit;
}


$params = [];
$sql = "SELECT ur.*, u.name 
        FROM usage_records ur 
        JOIN users u ON ur.user_id=u.user_id 
        WHERE 1=1";
if ($userId) { $sql .= " AND ur.user_id = :uid"; $params[':uid'] = $userId; }
if ($start)  { $sql .= " AND DATE(ur.recorded_at) >= :s"; $params[':s'] = $start; }
if ($end)    { $sql .= " AND DATE(ur.recorded_at) <= :e"; $params[':e'] = $end; }
$sql .= " ORDER BY ur.recorded_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


$totals = ['call'=>0,'sms'=>0,'data'=>0];
foreach ($rows as $r) {
  $t = strtolower($r['type']);
  if (isset($totals[$t])) $totals[$t] += (float)$r['amount'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Usage Data</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    :root{
      --ring:#e5e7eb; --ink:#1f2937; --muted:#6b7280;
      --card:#ffffff; --bg:#f6f8fb; --brand:#0d6efd; --brand-2:#0b5ed7;
      --danger:#dc2626; --danger-2:#b91c1c;
      --call:#2563eb; --sms:#059669; --data:#7c3aed;
    }
    body{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
    main.container{max-width:1200px;margin:0 auto;padding:18px}
    h1{margin:8px 0 16px}
    .card{
      background:var(--card); border:1px solid var(--ring); border-radius:12px;
      padding:16px; box-shadow:0 3px 10px rgba(0,0,0,.03);
    }
    .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:end}
    .filters label{display:block;font-weight:700;margin-bottom:6px}
    .filters select,.filters input[type=date]{padding:10px 12px;border:1px solid var(--ring);border-radius:10px;outline:none}
    .filters select:focus,.filters input[type=date]:focus{border-color:#9cc5ff;box-shadow:0 0 0 3px rgba(13,110,253,.15)}
    .btn{
      display:inline-block;border:1px solid var(--ring);background:#f9fafb;padding:10px 14px;border-radius:10px;
      text-decoration:none;color:var(--ink);font-weight:700
    }
    .btn:hover{background:#f1f5f9}
    .btn.primary{background:var(--brand);color:#fff;border-color:var(--brand);box-shadow:0 4px 12px rgba(13,110,253,.18)}
    .btn.primary:hover{background:var(--brand-2)}
    .btn.danger{background:var(--danger);border-color:var(--danger);color:#fff}
    .btn.danger:hover{background:var(--danger-2)}
    .chips{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}
    .chip{
      display:inline-flex;gap:8px;align-items:center;padding:8px 12px;border:1px solid var(--ring);
      border-radius:999px;background:#fff;font-weight:700
    }
    .chip small{color:var(--muted);font-weight:600}
    .chip .dot{width:8px;height:8px;border-radius:999px}
    .dot.call{background:var(--call)} .dot.sms{background:var(--sms)} .dot.data{background:var(--data)}
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--ring);border-radius:12px;overflow:hidden}
    th,td{padding:12px;border-bottom:1px solid var(--ring);text-align:left}
    thead th{background:#f8fafc;font-weight:800}
    tbody tr:hover{background:#fafafa}
    .badge{
      display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:6px 10px;font-weight:700;color:#fff
    }
    .badge.call{background:var(--call)} .badge.sms{background:var(--sms)} .badge.data{background:var(--data)}
    .muted{color:var(--muted)}
    .table-wrap{overflow:auto;border-radius:12px}
    form.inline{display:inline}
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <main class="container">
    <h1>Usage Data</h1>

    <!-- Filters -->
    <form method="get" class="card filters" style="margin-bottom:12px;">
      <div>
        <label for="user_id">User</label>
        <select id="user_id" name="user_id">
          <option value="0">All customers</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['user_id'] ?>" <?= ($userId === (int)$u['user_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name'] . " ({$u['email']})") ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="start">Start</label>
        <input id="start" type="date" name="start" value="<?= htmlspecialchars($start) ?>">
      </div>
      <div>
        <label for="end">End</label>
        <input id="end" type="date" name="end" value="<?= htmlspecialchars($end) ?>">
      </div>
      <div>
        <button type="submit" class="btn primary">Filter</button>
        <a class="btn" href="add_usage.php">Add Usage</a>
      </div>
    </form>

    
    <div class="chips">
      <span class="chip"><span class="dot call"></span>Calls&nbsp;<small><?= number_format($totals['call'], 3) ?></small></span>
      <span class="chip"><span class="dot sms"></span>SMS&nbsp;<small><?= number_format($totals['sms'], 3) ?></small></span>
      <span class="chip"><span class="dot data"></span>Data&nbsp;<small><?= number_format($totals['data'], 3) ?></small></span>
      <span class="chip"><small class="muted">Rows:</small> <?= count($rows) ?></span>
    </div>

    
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th>User</th>
            <th style="width:160px;">Type</th>
            <th style="width:160px;">Amount</th>
            <th style="width:220px;">Recorded At</th>
            <th style="width:120px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="muted">No usage found for the applied filter.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): 
            $type = strtolower($r['type']);
            $badgeClass = in_array($type, ['call','sms','data'], true) ? $type : 'call';
          ?>
            <tr>
              <td>#<?= (int)$r['record_id'] ?></td>
              <td><?= htmlspecialchars($r['name']) ?> <span class="muted">(ID: <?= (int)$r['user_id'] ?>)</span></td>
              <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($r['type']) ?></span></td>
              <td><?= number_format((float)$r['amount'], 3) ?></td>
              <td><?= htmlspecialchars($r['recorded_at']) ?></td>
              <td>
                <form method="post" class="inline" onsubmit="return confirm('Delete this record?')">
                  <input type="hidden" name="delete_id" value="<?= (int)$r['record_id'] ?>">
                  <button type="submit" class="btn danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
