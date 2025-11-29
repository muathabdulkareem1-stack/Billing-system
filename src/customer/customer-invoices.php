<?php

require_once __DIR__ . '/middleware/customer.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int)$_SESSION['user_id'];


$status = isset($_GET['status']) ? trim($_GET['status']) : ''; // '', 'paid', 'unpaid'
$start  = isset($_GET['start'])  ? trim($_GET['start'])  : '';
$end    = isset($_GET['end'])    ? trim($_GET['end'])    : '';


$allowedStatus = ['', 'paid', 'unpaid'];
if (!in_array($status, $allowedStatus, true)) $status = '';


$sql = "SELECT invoice_id, date, total_amount, status
        FROM invoices
        WHERE user_id = :uid";
$params = [':uid' => $userId];

if ($status !== '') {
  $sql .= " AND status = :st";
  $params[':st'] = $status;
}
if ($start) {
  $sql .= " AND date >= :s";
  $params[':s'] = $start;
}
if ($end) {
  $sql .= " AND date <= :e";
  $params[':e'] = $end;
}

$sql .= " ORDER BY date DESC, invoice_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);


$cntAll    = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE user_id={$userId}")->fetchColumn();
$cntPaid   = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE user_id={$userId} AND status='paid'")->fetchColumn();
$cntUnpaid = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE user_id={$userId} AND status='unpaid'")->fetchColumn();


$totals = [
  'paid'   => (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE user_id={$userId} AND status='paid'")->fetchColumn(),
  'unpaid' => (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE user_id={$userId} AND status='unpaid'")->fetchColumn(),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Invoices</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    :root{
      --bg:#f6f8fb; --card:#fff; --ring:#e5e7eb; --ink:#1f2937; --muted:#6b7280;
      --brand:#0d6efd; --brand-2:#0b5ed7; --ok:#059669;
    }
    body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,Segoe UI,Arial,sans-serif}
    main.container{max-width:1100px;margin:0 auto;padding:18px}
    h1{margin:8px 0 16px;font-size:28px}
    .card{background:#fff;border:1px solid var(--ring);border-radius:12px;padding:14px;box-shadow:0 3px 10px rgba(0,0,0,.03)}
    .muted{color:var(--muted)}
    .row{display:flex;gap:8px;flex-wrap:wrap}
    .btn{display:inline-block;padding:10px 14px;border:1px solid var(--ring);border-radius:10px;background:#fff;text-decoration:none;color:var(--ink);font-weight:700}
    .btn:hover{background:#f9fafb}
    .btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 4px 12px rgba(13,110,253,.18)}
    .btn.primary:hover{background:var(--brand-2)}
    .pillbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .pill{padding:8px 12px;border:1px solid var(--ring);border-radius:999px;background:#fff;text-decoration:none;color:var(--ink);font-weight:700}
    .pill.active{background:#eef6ff;border-color:#9cc5ff}
    form.filters{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0}
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--ring);border-radius:12px;overflow:hidden}
    th,td{padding:12px;border-bottom:1px solid var(--ring);text-align:left}
    thead th{background:#f8fafc;font-weight:800}
    .badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-weight:800}
    .badge.paid{background:#ecfdf5;color:#065f46;border:1px solid #bbf7d0}
    .badge.unpaid{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
    .totals{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:10px 0}
    .kpi{background:#fff;border:1px solid var(--ring);border-radius:12px;padding:12px}
    .kpi .val{font-weight:800;font-size:20px}
  </style>
</head>
<body>
  <?php include __DIR__ . '/customer_navbar.php'; ?>

  <main class="container">
    <h1>My Invoices</h1>


    <div class="totals">
      <div class="kpi"><div class="muted">All</div><div class="val"><?= number_format($cntAll) ?></div></div>
      <div class="kpi"><div class="muted">Paid</div><div class="val"><?= number_format($cntPaid) ?> — <?= number_format($totals['paid'], 3) ?> JOD</div></div>
      <div class="kpi"><div class="muted">Unpaid</div><div class="val"><?= number_format($cntUnpaid) ?> — <?= number_format($totals['unpaid'], 3) ?> JOD</div></div>
    </div>

    
    <div class="pillbar">
      <a class="pill <?= $status==='' ? 'active':'' ?>" href="customer-invoices.php">All</a>
      <a class="pill <?= $status==='paid' ? 'active':'' ?>" href="customer-invoices.php?status=paid">Paid</a>
      <a class="pill <?= $status==='unpaid' ? 'active':'' ?>" href="customer-invoices.php?status=unpaid">Unpaid</a>
    </div>

   
    <form method="get" class="filters card">
      <div>
        <label class="muted">Status</label><br>
        <select name="status">
          <option value="" <?= $status===''?'selected':'' ?>>All</option>
          <option value="paid" <?= $status==='paid'?'selected':'' ?>>Paid</option>
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
        <a class="btn" href="customer-invoices.php">Reset</a>
      </div>
    </form>

    
    <div class="card" style="padding:0">
      <table>
        <thead>
          <tr>
            <th style="width:90px;">#</th>
            <th style="width:160px;">Date</th>
            <th style="width:160px;">Total (JOD)</th>
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
            <td><?= number_format((float)$inv['total_amount'], 3) ?></td>
            <td>
              <?php if ($inv['status']==='paid'): ?>
                <span class="badge paid">paid</span>
              <?php else: ?>
                <span class="badge unpaid">unpaid</span>
              <?php endif; ?>
            </td>
            <td class="row">
              <a class="btn" href="customer-invoice-details.php?id=<?= (int)$inv['invoice_id'] ?>">View</a>
              <?php if ($inv['status']==='unpaid'): ?>
                <form method="post" action="pay_invoice.php">
                  <input type="hidden" name="invoice_id" value="<?= (int)$inv['invoice_id'] ?>">
                  <button class="btn primary" type="submit">Pay</button>
                </form>
              <?php endif; ?>
              <!-- Optional: per-invoice PDF -->
              <a class="btn" href="monthly_invoice_report.php?single=<?= (int)$inv['invoice_id'] ?>">PDF</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
