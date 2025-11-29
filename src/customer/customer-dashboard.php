<?php
// customer-dashboard.php
require_once __DIR__ . '/middleware/customer.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int)$_SESSION['user_id'];

// Unpaid summary
$stmt = $pdo->prepare("
  SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total
  FROM invoices WHERE user_id=? AND status='unpaid'
");
$stmt->execute([$userId]);
$unpaid = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt'=>0,'total'=>0];

// Last invoice
$stmt = $pdo->prepare("
  SELECT invoice_id, date, total_amount, status
  FROM invoices WHERE user_id=? ORDER BY date DESC, invoice_id DESC LIMIT 1
");
$stmt->execute([$userId]);
$lastInvoice = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent (5)
$stmt = $pdo->prepare("
  SELECT invoice_id, date, total_amount, status
  FROM invoices WHERE user_id=? ORDER BY date DESC, invoice_id DESC LIMIT 5
");
$stmt->execute([$userId]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);


$startMonth = date('Y-m-01');
$endMonth   = date('Y-m-t');
$endPlus    = (new DateTime($endMonth))->modify('+1 day')->format('Y-m-d');
$stmt = $pdo->prepare("
  SELECT type, COALESCE(SUM(amount),0) AS total
  FROM usage_records
  WHERE user_id=? AND recorded_at>=? AND recorded_at<?
  GROUP BY type
");
$stmt->execute([$userId, $startMonth, $endPlus]);
$usage = ['call'=>0,'sms'=>0,'data'=>0];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $t = strtolower($r['type']); if(isset($usage[$t])) $usage[$t]=(float)$r['total'];
}


$lastStart = date('Y-m-01', strtotime('first day of last month'));
$lastEnd   = date('Y-m-t',  strtotime('last day of last month'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Customer Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    :root{
      --bg:#f6f8fb; --card:#fff; --ring:#e5e7eb; --ink:#1f2937; --muted:#6b7280;
      --brand:#0d6efd; --brand-2:#0b5ed7; --ok:#059669; --warn:#f59e0b;
      --chip:#eef2ff; --tableHead:#f8fafc;
    }
    body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,Segoe UI,Arial,sans-serif}
    main.container{max-width:1200px;margin:0 auto;padding:18px}
    h1{margin:8px 0 16px;font-size:32px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
    .card{background:var(--card);border:1px solid var(--ring);border-radius:12px;padding:14px;box-shadow:0 3px 10px rgba(0,0,0,.03)}
    .muted{color:var(--muted)}
    .kpi{font-size:28px;font-weight:800}
    .btn{display:inline-block;padding:10px 14px;border:1px solid var(--ring);border-radius:10px;background:#fff;text-decoration:none;color:var(--ink);font-weight:700}
    .btn:hover{background:#f9fafb}
    .btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 4px 12px rgba(13,110,253,.18)}
    .btn.primary:hover{background:var(--brand-2)}
    .btn.ghost{background:#f9fafb}
    .row{display:flex;gap:8px;flex-wrap:wrap}
    .badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-weight:800}
    .badge.paid{background:#ecfdf5;color:var(--ok);border:1px solid #bbf7d0}
    .badge.unpaid{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
    .chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
    .chip{background:var(--chip);border:1px solid var(--ring);border-radius:999px;padding:8px 12px;font-weight:700}
    .table-wrap{overflow:auto;border-radius:12px}
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--ring)}
    th,td{padding:12px;border-bottom:1px solid var(--ring);text-align:left}
    thead th{background:var(--tableHead);font-weight:800}
  </style>
</head>
<body>
  <?php include __DIR__ . '/customer_navbar.php'; ?>

  <main class="container">
    <h1>Welcome<?= isset($_SESSION['user_name']) ? ', '.htmlspecialchars($_SESSION['user_name']) : '' ?></h1>

    
    <section class="grid" style="margin-bottom:12px;">
      <div class="card">
        <div class="muted">Unpaid Invoices</div>
        <div class="kpi"><?= (int)$unpaid['cnt'] ?></div>
        <div class="muted">Total due: <strong><?= number_format((float)$unpaid['total'], 3) ?> JOD</strong></div>
        <?php if ((int)$unpaid['cnt']>0): ?>
          <div class="row" style="margin-top:10px">
            <a class="btn primary" href="customer-invoices.php?status=unpaid">Pay Now</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="muted">Last Invoice</div>
        <?php if ($lastInvoice): ?>
          <div><strong>#<?= (int)$lastInvoice['invoice_id'] ?></strong> — <?= htmlspecialchars($lastInvoice['date']) ?></div>
          <div style="margin:6px 0">
            <?php if ($lastInvoice['status']==='paid'): ?>
              <span class="badge paid">paid</span>
            <?php else: ?>
              <span class="badge unpaid">unpaid</span>
            <?php endif; ?>
          </div>
          <div class="kpi"><?= number_format((float)$lastInvoice['total_amount'], 3) ?> JOD</div>
          <div class="row" style="margin-top:10px">
            <a class="btn" href="customer-invoice-details.php?id=<?= (int)$lastInvoice['invoice_id'] ?>">View</a>
            <?php if ($lastInvoice['status']==='unpaid'): ?>
              <form method="post" action="pay_invoice.php">
                <input type="hidden" name="invoice_id" value="<?= (int)$lastInvoice['invoice_id'] ?>">
                <button class="btn primary" type="submit">Pay Now</button>
              </form>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="muted">No invoices yet.</div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="muted">This Month’s Usage</div>
        <div class="chips">
          <span class="chip">Calls: <?= number_format($usage['call'], 2) ?> min</span>
          <span class="chip">SMS: <?= number_format($usage['sms'], 0) ?> msgs</span>
          <span class="chip">Data: <?= number_format($usage['data'], 2) ?> MB</span>
        </div>
        <div class="row" style="margin-top:10px">
          <a class="btn ghost" href="customer-usage.php">View Details</a>
        </div>
      </div>

      <div class="card">
        <div class="muted">Quick Actions</div>
        <div class="row" style="margin-top:8px">
          <a class="btn" href="customer-invoices.php">All My Invoices</a>
          <a class="btn" href="customer-billing-history.php">Billing History</a>
        </div>
        <div class="row" style="margin-top:8px">
          <a class="btn ghost" href="monthly_invoice_report.php?start=<?= urlencode($lastStart) ?>&end=<?= urlencode($lastEnd) ?>">Download Last Month (PDF)</a>
        </div>
      </div>
    </section>

    
    <section class="card">
      <h2 style="margin:0 0 10px">Recent Invoices</h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:90px;">#</th>
              <th style="width:160px;">Date</th>
              <th style="width:180px;">Total (JOD)</th>
              <th style="width:120px;">Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$recent): ?>
            <tr><td colspan="5" class="muted">No invoices found.</td></tr>
          <?php else: foreach ($recent as $r): ?>
            <tr>
              <td>#<?= (int)$r['invoice_id'] ?></td>
              <td><?= htmlspecialchars($r['date']) ?></td>
              <td><?= number_format((float)$r['total_amount'], 3) ?></td>
              <td>
                <?php if ($r['status']==='paid'): ?>
                  <span class="badge paid">paid</span>
                <?php else: ?>
                  <span class="badge unpaid">unpaid</span>
                <?php endif; ?>
              </td>
              <td class="row">
                <a class="btn" href="customer-invoice-details.php?id=<?= (int)$r['invoice_id'] ?>">View</a>
                <?php if ($r['status']==='unpaid'): ?>
                  <form method="post" action="pay_invoice.php">
                    <input type="hidden" name="invoice_id" value="<?= (int)$r['invoice_id'] ?>">
                    <button class="btn primary" type="submit">Pay</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
