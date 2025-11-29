<?php

require_once __DIR__ . '/middleware/admin.php';
require_once __DIR__ . '/includes/db.php';


function d($key, $default = null) { return $_REQUEST[$key] ?? $default; }

function periodFromRequest(): array {
   
    $start = d('start', date('Y-m-01'));
    $end   = d('end',   date('Y-m-t'));
    $ds = DateTime::createFromFormat('Y-m-d', $start);
    $de = DateTime::createFromFormat('Y-m-d', $end);
    if (!$ds || !$de || $ds > $de) {
        $start = date('Y-m-01');
        $end   = date('Y-m-t');
    }
   
    return [$start, $end];
}

list($START, $END) = periodFromRequest();
$END_PLUS = (new DateTime($END))->modify('+1 day')->format('Y-m-d');

$action = d('action', '');


if ($action === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_' . $START . '_to_' . $END . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Metric', 'Value', 'Period Start', 'Period End']);

   
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount),0) 
        FROM invoices 
        WHERE status='paid' AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$START, $END]);
    $rev = (float)$stmt->fetchColumn();
    fputcsv($out, ['Revenue (paid invoices)', number_format($rev, 3), $START, $END]);

    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount),0) 
        FROM invoices 
        WHERE status='unpaid' AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$START, $END]);
    $outstanding = (float)$stmt->fetchColumn();
    fputcsv($out, ['Outstanding (unpaid invoices)', number_format($outstanding, 3), $START, $END]);

    
    $stmt = $pdo->prepare("
        SELECT 
          SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_cnt,
          SUM(CASE WHEN status='unpaid' THEN 1 ELSE 0 END) AS unpaid_cnt
        FROM invoices
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->execute([$START, $END]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    fputcsv($out, ['# Paid invoices', (int)$c['paid_cnt'], $START, $END]);
    fputcsv($out, ['# Unpaid invoices', (int)$c['unpaid_cnt'], $START, $END]);

    
    $stmt = $pdo->prepare("
        SELECT type, COALESCE(SUM(amount),0) AS total
        FROM usage_records
        WHERE recorded_at >= ? AND recorded_at < ?
        GROUP BY type
        ORDER BY type
    ");
    $stmt->execute([$START, $END_PLUS]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, ["Usage - {$row['type']}", $row['total'], $START, $END]);
    }

    fclose($out);
    exit;
}

$kpis = [];


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount),0) 
    FROM invoices 
    WHERE status='paid' AND date BETWEEN ? AND ?
");
$stmt->execute([$START, $END]);
$kpis['revenue_paid'] = (float)$stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount),0) 
    FROM invoices 
    WHERE status='unpaid' AND date BETWEEN ? AND ?
");
$stmt->execute([$START, $END]);
$kpis['outstanding_unpaid'] = (float)$stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT 
      SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_cnt,
      SUM(CASE WHEN status='unpaid' THEN 1 ELSE 0 END) AS unpaid_cnt
    FROM invoices
    WHERE date BETWEEN ? AND ?
");
$stmt->execute([$START, $END]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
$kpis['paid_cnt']   = (int)$c['paid_cnt'];
$kpis['unpaid_cnt'] = (int)$c['unpaid_cnt'];


$kpis['customers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();


$stmt = $pdo->prepare("
    SELECT type, COALESCE(SUM(amount),0) AS total
    FROM usage_records
    WHERE recorded_at >= ? AND recorded_at < ?
    GROUP BY type
    ORDER BY type
");
$stmt->execute([$START, $END_PLUS]);
$usage_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT date AS day, COALESCE(SUM(total_amount),0) AS total
    FROM invoices
    WHERE status='paid' AND date BETWEEN ? AND ?
    GROUP BY date
    ORDER BY date
");
$stmt->execute([$START, $END]);
$rev_by_day = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT u.user_id, u.name, u.email, COALESCE(SUM(i.total_amount),0) AS total
    FROM invoices i
    JOIN users u ON u.user_id = i.user_id
    WHERE i.status='paid' AND i.date BETWEEN ? AND ?
    GROUP BY u.user_id, u.name, u.email
    ORDER BY total DESC
    LIMIT 10
");
$stmt->execute([$START, $END]);
$top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);


$aging = [
    '0-30'  => 0.0,
    '31-60' => 0.0,
    '61-90' => 0.0,
    '90+'   => 0.0
];
$stmt = $pdo->prepare("
    SELECT total_amount, DATEDIFF(CURDATE(), date) AS age_days
    FROM invoices
    WHERE status='unpaid' AND date <= ?
");
$stmt->execute([$END]); 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $amt = (float)$row['total_amount'];
    $age = (int)$row['age_days'];
    if ($age <= 30)       $aging['0-30']  += $amt;
    elseif ($age <= 60)   $aging['31-60'] += $amt;
    elseif ($age <= 90)   $aging['61-90'] += $amt;
    else                  $aging['90+']   += $amt;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Reports</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root{
      --brand:#0d6efd; --brand-2:#0b5ed7;
      --bg:#f6f8fb; --card:#ffffff; --ink:#1f2937; --muted:#6b7280;
      --ring:#e5e7eb; --shadow:0 8px 24px rgba(15,23,42,.06); --radius:14px;
      --success:#10b981; --danger:#ef4444; --warn:#f59e0b;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
    main{max-width:1200px;margin:0 auto;padding:22px 18px 64px}
    h1{font-size:32px;margin:8px 0 16px}
    .muted{color:var(--muted)}
    .card{background:var(--card);border:1px solid var(--ring);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
    .kpis{display:grid;gap:12px;margin:14px 0}
    @media(min-width:960px){ .kpis{grid-template-columns:repeat(6,1fr)} }
    .kpi .label{font-size:12px;color:var(--muted);letter-spacing:.06em;text-transform:uppercase}
    .kpi .value{font-weight:800;font-size:22px;margin-top:4px}
    .filters{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0}
    .filters .field{display:flex;flex-direction:column;gap:6px;min-width:180px}
    input[type="date"]{height:42px;padding:0 12px;border:1px solid var(--ring);border-radius:10px;background:#fff}
    .btn{
      --bg:var(--brand); --bgh:var(--brand-2);
      display:inline-flex;align-items:center;gap:8px;height:42px;padding:0 14px;border-radius:10px;border:0;color:#fff;
      background:var(--bg);font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(13,110,253,.18);transition:.18s
    }
    .btn:hover{background:var(--bgh);transform:translateY(-1px)}
    .btn.ghost{background:#fff;color:var(--ink);border:1px solid var(--ring);box-shadow:none}
    .btn.warn{background:var(--warn)}
    .btn-row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}

    .table-wrap{overflow:auto;border-radius:12px;border:1px solid var(--ring);background:#fff;box-shadow:var(--shadow)}
    table{width:100%;border-collapse:collapse;min-width:720px}
    th,td{padding:12px 14px;border-bottom:1px solid var(--ring);text-align:left}
    th{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);background:#fafafa}
    .right{text-align:right}

   
    .bar{height:8px;border-radius:6px;background:#eef2ff;position:relative;overflow:hidden}
    .bar > i{position:absolute;left:0;top:0;bottom:0;background:linear-gradient(90deg,#60a5fa,#2563eb)}
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <main>
    <h1>Reports</h1>
    <p class="muted" style="margin-top:-6px">Explore revenue, usage, unpaid aging, and your top customers for a chosen period.</p>

    
    <form method="get" class="card filters" style="align-items:end">
      <div class="field">
        <label>Start</label>
        <input type="date" name="start" value="<?= htmlspecialchars($START) ?>">
      </div>
      <div class="field">
        <label>End</label>
        <input type="date" name="end" value="<?= htmlspecialchars($END) ?>">
      </div>
      <div class="btn-row">
        <button class="btn" type="submit">Apply</button>
        <a class="btn ghost" href="?start=<?= urlencode(date('Y-m-01')) ?>&end=<?= urlencode(date('Y-m-t')) ?>">This Month</a>
        <a class="btn ghost" href="?start=<?= urlencode(date('Y-m-01', strtotime('first day of last month'))) ?>&end=<?= urlencode(date('Y-m-t', strtotime('last day of last month'))) ?>">Last Month</a>
        <a class="btn warn" href="?action=export_csv&start=<?= urlencode($START) ?>&end=<?= urlencode($END) ?>">Export CSV</a>
      </div>
    </form>

    
    <section class="kpis">
      <div class="card kpi"><div class="label">Revenue (Paid)</div><div class="value"><?= number_format($kpis['revenue_paid'], 3) ?> JOD</div></div>
      <div class="card kpi"><div class="label">Outstanding (Unpaid)</div><div class="value"><?= number_format($kpis['outstanding_unpaid'], 3) ?> JOD</div></div>
      <div class="card kpi"><div class="label"># Paid Invoices</div><div class="value"><?= (int)$kpis['paid_cnt'] ?></div></div>
      <div class="card kpi"><div class="label"># Unpaid Invoices</div><div class="value"><?= (int)$kpis['unpaid_cnt'] ?></div></div>
      <div class="card kpi"><div class="label">Customers</div><div class="value"><?= (int)$kpis['customers'] ?></div></div>
      <div class="card kpi">
        <div class="label">Period</div>
        <div class="value" style="font-size:16px"><?= htmlspecialchars($START) ?> → <?= htmlspecialchars($END) ?></div>
      </div>
    </section>

    
    <section class="card" style="margin-top:14px">
      <h2 style="margin:0 0 8px">Usage Summary</h2>
      <div class="table-wrap" style="margin-top:8px">
        <table>
          <thead><tr><th>Type</th><th class="right">Total Amount</th></tr></thead>
          <tbody>
            <?php if (!$usage_summary): ?>
              <tr><td colspan="2">No usage in this period.</td></tr>
            <?php else: foreach ($usage_summary as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['type']) ?></td>
                <td class="right"><?= htmlspecialchars($u['total']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <p class="muted" style="margin-top:10px">Units stored in <code>usage_records</code>: calls (minutes), sms (count), data (MB).</p>
    </section>

    
    <section class="card" style="margin-top:14px">
      <h2 style="margin:0 0 8px">Revenue by Day (Paid)</h2>
      <div class="table-wrap" style="margin-top:8px">
        <table>
          <thead><tr><th>Date</th><th class="right">Total (JOD)</th></tr></thead>
          <tbody>
            <?php if (!$rev_by_day): ?>
              <tr><td colspan="2">No paid invoices in this period.</td></tr>
            <?php else: foreach ($rev_by_day as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['day']) ?></td>
                <td class="right"><?= number_format($r['total'], 3) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    
    <section class="card" style="margin-top:14px">
      <h2 style="margin:0 0 8px">Top Customers (by Paid Revenue)</h2>
      <div class="table-wrap" style="margin-top:8px">
        <table>
          <thead><tr><th>Customer</th><th>Email</th><th class="right">Total (JOD)</th></tr></thead>
          <tbody>
            <?php if (!$top_customers): ?>
              <tr><td colspan="3">No paid revenue in this period.</td></tr>
            <?php else: foreach ($top_customers as $t): ?>
              <tr>
                <td><?= htmlspecialchars($t['name']) ?> <span class="muted">(ID: <?= (int)$t['user_id'] ?>)</span></td>
                <td><?= htmlspecialchars($t['email']) ?></td>
                <td class="right"><?= number_format($t['total'], 3) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    
    <section class="card" style="margin-top:14px">
      <h2 style="margin:0 0 8px">Aging (Unpaid Invoices)</h2>
      <div class="table-wrap" style="margin-top:8px">
        <table>
          <thead><tr><th>Bucket</th><th class="right">Amount (JOD)</th></tr></thead>
          <tbody>
            <tr><td>0–30 days</td><td class="right"><?= number_format($aging['0-30'], 3) ?></td></tr>
            <tr><td>31–60 days</td><td class="right"><?= number_format($aging['31-60'], 3) ?></td></tr>
            <tr><td>61–90 days</td><td class="right"><?= number_format($aging['61-90'], 3) ?></td></tr>
            <tr><td>90+ days</td><td class="right"><?= number_format($aging['90+'], 3) ?></td></tr>
          </tbody>
        </table>
      </div>
      <p class="muted" style="margin-top:10px">Aging calculated as of today for invoices up to the selected end date.</p>
    </section>
  </main>
</body>
</html>
