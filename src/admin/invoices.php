<?php
require_once __DIR__ . '/middleware/admin.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/billing.php';

$flash = null;




if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_last_month'])) {
    $stats = generateInvoicesForPeriod($pdo, lastMonthPeriod());
    $flash = "Created {$stats['created']} invoice(s) for last month.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalc_invoice_id'])) {
    $invoiceId = (int)$_POST['recalc_invoice_id'];
    $stmt = $pdo->prepare("SELECT user_id, date FROM invoices WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    if ($inv = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userId = (int)$inv['user_id'];
        $date   = $inv['date'];
        $period = [
            'start' => date('Y-m-01', strtotime($date)),
            'end'   => date('Y-m-t',  strtotime($date)),
        ];
        $pdo->prepare("DELETE FROM invoice_lines WHERE invoice_id=?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM invoices WHERE invoice_id=?")->execute([$invoiceId]);
        $newId = generateInvoiceForUser($pdo, $userId, $period['start'], $period['end']);
        $flash = $newId ? "Invoice #{$invoiceId} recalculated → new #{$newId}." : "No usage to recalc.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice_id'])) {
    $invoiceId = (int)$_POST['delete_invoice_id'];
    $pdo->prepare("DELETE FROM invoice_lines WHERE invoice_id=?")->execute([$invoiceId]);
    $pdo->prepare("DELETE FROM invoices WHERE invoice_id=?")->execute([$invoiceId]);
    $flash = "Invoice #{$invoiceId} deleted.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status_id'])) {
    $invoiceId = (int)$_POST['toggle_status_id'];
    $cur = $pdo->prepare("SELECT status FROM invoices WHERE invoice_id=?");
    $cur->execute([$invoiceId]);
    if ($s = $cur->fetchColumn()) {
        $new = ($s === 'paid') ? 'unpaid' : 'paid';
        $pdo->prepare("UPDATE invoices SET status=? WHERE invoice_id=?")->execute([$new, $invoiceId]);
        $flash = "Invoice #{$invoiceId} marked {$new}.";
    }
}


$q = $pdo->query("
  SELECT i.invoice_id, i.user_id, u.name AS customer, i.date, i.total_amount, i.status
  FROM invoices i
  JOIN users u ON u.user_id = i.user_id
  ORDER BY i.date DESC, i.invoice_id DESC
");
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Invoices</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: system-ui, Arial, sans-serif; margin:0; }
    main { padding: 16px; }
    table { width:100%; border-collapse: collapse; }
    th, td { border:1px solid #ddd; padding:8px; }
    th { background:#f7f7f7; text-align:left; }
    .actions form { display:inline; }
    .flash { margin:12px 0; padding:10px; border:1px solid #cde; background:#f6fbff; }
  </style>
</head>
<body>
  <?php include __DIR__.'/admin-navbar.php'; ?>

  <main>
    <h1>Invoices</h1>

    <?php if ($flash): ?>
      <div class="flash"><strong><?= htmlspecialchars($flash) ?></strong></div>
    <?php endif; ?>

    <form method="post" style="margin:10px 0;">
      <button type="submit" name="generate_last_month">Generate Last Month (All Customers)</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Total (JOD)</th>
          <th>Status</th>
          <th style="width:360px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6">No invoices yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['invoice_id'] ?></td>
            <td><?= htmlspecialchars($r['customer']) ?> (ID: <?= (int)$r['user_id'] ?>)</td>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td><?= number_format($r['total_amount'], 3) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td class="actions">
              
              <a href="customer-invoice-details.php?invoice_id=<?= (int)$r['invoice_id'] ?>" target="_blank">View</a>

              
              <form method="post">
                <input type="hidden" name="recalc_invoice_id" value="<?= (int)$r['invoice_id'] ?>">
                <button type="submit">Recalculate</button>
              </form>

             
              <form method="post">
                <input type="hidden" name="toggle_status_id" value="<?= (int)$r['invoice_id'] ?>">
                <button type="submit"><?= $r['status']==='paid'?'Mark Unpaid':'Mark Paid' ?></button>
              </form>

             
              <form method="post" onsubmit="return confirm('Delete invoice #<?= (int)$r['invoice_id'] ?>?');">
                <input type="hidden" name="delete_invoice_id" value="<?= (int)$r['invoice_id'] ?>">
                <button type="submit" style="color:#b00;">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </main>
</body>
</html>
