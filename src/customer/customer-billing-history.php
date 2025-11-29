<?php

require_once __DIR__ . '/middleware/customer.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int)$_SESSION['user_id'];


$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all'; // all|paid|unpaid
$start  = isset($_GET['start'])  ? trim($_GET['start']) : '';
$end    = isset($_GET['end'])    ? trim($_GET['end'])   : '';


$sql = "SELECT invoice_id, date, total_amount, status
        FROM invoices
        WHERE user_id = :uid";
$params = [':uid' => $userId];

if ($status === 'paid' || $status === 'unpaid') {
  $sql .= " AND status = :st"; $params[':st'] = $status;
}
if ($start) { $sql .= " AND date >= :s"; $params[':s'] = $start; }
if ($end)   { $sql .= " AND date <= :e"; $params[':e'] = $end; }

$sql .= " ORDER BY date DESC, invoice_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);


$kpi = ['paid'=>0.0,'unpaid'=>0.0,'count'=>count($invoices)];
foreach ($invoices as $inv) {
  if ($inv['status']==='paid')   $kpi['paid']   += (float)$inv['total_amount'];
  if ($inv['status']==='unpaid') $kpi['unpaid'] += (float)$inv['total_amount'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Billing History</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <style>
    :root{
      --bg:#f6f8fb; --card:#fff; --ring:#e5e7eb; --ink:#1f2937; --muted:#6b7280;
      --brand:#0d6efd; --brand-2:#0b5ed7; --success:#16a34a; --warn:#f59e0b;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,Segoe UI,Arial,sans-serif}
    main.container{max-width:1100px;margin:0 auto;padding:18px}
    h1{margin:8px 0 16px;font-size:28px}
    .muted{color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
    .kpi{background:#fff;border:1px solid var(--ring);border-radius:12px;padding:12px}
    .kpi .v{font-weight:800;font-size:20px}
    .card{background:var(--card);border:1px solid var(--ring);border-radius:12px;padding:14px;box-shadow:0 3px 10px rgba(0,0,0,.03)}
    form.filters{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0}
    .btn{display:inline-block;padding:10px 14px;border:1px solid var(--ring);border-radius:10px;background:#fff;text-decoration:none;color:var(--ink);font-weight:700}
    .btn:hover{background:#f9fafb}
    .btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 4px 12px rgba(13,110,253,.18)}
    .btn.primary:hover{background:var(--brand-2)}
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--ring);border-radius:12px;overflow:hidden}
    th,td{padding:12px;border-bottom:1px solid var(--ring);text-align:left}
    thead th{background:#f8fafc;font-weight:800}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:800}
    .badge.success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
    .badge.warn{background:#fff7ed;color:#9a3412;border:1px solid #ffedd5}
    .right{text-align:right}
    .row{display:flex;gap:8px;flex-wrap:wrap}
  </style>
</head>
<body>
  <?php include __DIR__ . '/customer_navbar.php'; ?>

  <main class="container">
    <h1>Billing History</h1>

    
    <section class="grid">
      <div class="kpi"><div class="muted">Total Paid (filtered)</div><div class="v"><?= number_format($kpi['paid'], 3) ?> JOD</div></div>
      <div class="kpi"><div class="muted">Outstanding (filtered)</div><div class="v"><?= number_format($kpi['unpaid'], 3) ?> JOD</div></div>
      <div class="kpi"><div class="muted">Invoices (filtered)</div><div class="v"><?= number_format($kpi['count']) ?></div></div>
    </section>

    
    <form method="get" class="filters card">
      <div>
        <label class="muted">Status</label><br>
        <select name="status">
          <option value="all"    <?= $status==='all'?'selected':'' ?>>All</option>
          <option value="paid"   <?= $status==='paid'?'selected':'' ?>>Paid</option>
          <option value="unpaid" <?= $status==='unpaid'?'selected':'' ?>>Unpaid</option>
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
        <a class="btn" href="customer-billing-history.php">Reset</a>
      </div>
    </form>

    
    <div class="card" style="padding:0">
      <table>
        <thead>
          <tr>
            <th style="width:120px;">Invoice #</th>
            <th style="width:140px;">Date</th>
            <th class="right" style="width:160px;">Total (JOD)</th>
            <th style="width:120px;">Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$invoices): ?>
            <tr><td colspan="5" class="muted" style="padding:18px">No invoices found for the selected filter.</td></tr>
          <?php else: foreach ($invoices as $inv): ?>
            <tr>
              <td>#<?= (int)$inv['invoice_id'] ?></td>
              <td><?= htmlspecialchars($inv['date']) ?></td>
              <td class="right"><?= number_format((float)$inv['total_amount'], 3) ?></td>
              <td>
                <?php if ($inv['status']==='paid'): ?>
                  <span class="badge success">paid</span>
                <?php else: ?>
                  <span class="badge warn">unpaid</span>
                <?php endif; ?>
              </td>
              <td class="row">
                <a class="btn" href="customer-invoice-details.php?id=<?= (int)$inv['invoice_id'] ?>">View</a>
                <?php if ($inv['status']==='unpaid'): ?>
                  <form method="post" action="pay_invoice.php" onsubmit="return confirm('Proceed to pay this invoice?');">
                    <input type="hidden" name="invoice_id" value="<?= (int)$inv['invoice_id'] ?>">
                    <button class="btn primary" type="submit">Pay</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
