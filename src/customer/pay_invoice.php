<?php

require_once __DIR__ . '/middleware/customer.php';
require_once __DIR__ . '/includes/db.php';

$userId   = (int)($_SESSION['user_id'] ?? 0);
$message  = '';
$error    = '';
$invoice  = null;


if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function check_csrf() {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}


function mock_gateway_charge(float $amount, string $method): array {
    
    $ok = (mt_rand(1,100) <= 95);
    return [
        'ok'      => $ok,
        'ref'     => strtoupper(bin2hex(random_bytes(6))),
        'message' => $ok ? 'Payment authorized' : 'Gateway error (mock)'
    ];
}


if (isset($_GET['invoice_id'])) {
    $invoiceId = (int)$_GET['invoice_id'];
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ? AND user_id = ? AND status = 'unpaid'");
    $stmt->execute([$invoiceId, $userId]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        $error = "Invoice not found or already paid.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf()) {
        $error = "Security check failed.";
    } else {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $method    = trim($_POST['method'] ?? 'card');

        try {
            $pdo->beginTransaction();

            
            $lock = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ? AND user_id = ? FOR UPDATE");
            $lock->execute([$invoiceId, $userId]);
            $inv = $lock->fetch();

            if (!$inv) {
                throw new Exception("Invoice not found.");
            }
            if ($inv['status'] !== 'unpaid') {
                throw new Exception("Invoice already paid.");
            }

            $amount = (float)$inv['total_amount'];

            
            $gw = mock_gateway_charge($amount, $method);

            
            $pmt = $pdo->prepare("
                INSERT INTO payments (user_id, invoice_id, amount_paid, method, status, paid_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $pmt->execute([
                $userId,
                $invoiceId,
                $amount,
                $method,
                $gw['ok'] ? 'success' : 'failed'
            ]);

            
            if ($gw['ok']) {
                $upd = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE invoice_id = ?");
                $upd->execute([$invoiceId]);
                $pdo->commit();
                $message = "Payment successful. Ref: {$gw['ref']}";
                
                $invoice = null;
            } else {
                $pdo->rollBack();
                $error = "Payment failed: {$gw['message']}";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}


$unpaid = [];
if (!$invoice) {
    $q = $pdo->prepare("SELECT invoice_id, date, total_amount FROM invoices WHERE user_id = ? AND status = 'unpaid' ORDER BY date DESC");
    $q->execute([$userId]);
    $unpaid = $q->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pay Invoice</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font: 15px/1.5 system-ui, Arial, sans-serif; margin: 20px; }
        .alert { padding: 10px 12px; border-radius: 6px; margin: 10px 0; }
        .alert.ok { background: #ecfff0; border: 1px solid #9ad7a7; color:#225e2e; }
        .alert.err { background: #fff2f2; border: 1px solid #e3a4a4; color:#7b1e1e; }
        table { border-collapse: collapse; width: 100%; max-width: 800px; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; }
        th { background: #f7f7f7; text-align: left; }
        .btn { display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none; background: #1a73e8; color: #fff; }
        .btn:hover { opacity: .9; }
        .muted { color: #777; font-size: 12px; }
        .form { max-width: 520px; }
        label { display:block; margin: 12px 0 4px; }
        input[type=text], select { width: 100%; padding: 8px; border:1px solid #ccc; border-radius:6px; }
        .row { margin: 16px 0; }
    </style>
</head>
<body>

<?php if (is_file(__DIR__.'/customer_navbar.php')) include __DIR__.'/customer_navbar.php'; ?>

<h1>Pay Invoice</h1>

<?php if ($message): ?>
    <div class="alert ok"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($invoice): ?>
   
    <div class="form">
        <p><strong>Invoice #<?= (int)$invoice['invoice_id'] ?></strong></p>
        <p>Date: <?= htmlspecialchars($invoice['date']) ?></p>
        <p>Amount Due: <strong><?= number_format((float)$invoice['total_amount'], 2) ?> JOD</strong></p>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="invoice_id" value="<?= (int)$invoice['invoice_id'] ?>">

            <label for="method">Payment Method</label>
            <select id="method" name="method" required>
                <option value="card">Card</option>
                <option value="wallet">Wallet</option>
                <option value="bank">Bank Transfer</option>
                <option value="cash">Cash</option>
            </select>

            <div class="row">
                <button class="btn" type="submit">Pay <?= number_format((float)$invoice['total_amount'], 2) ?> JOD</button>
                <span class="muted">This is a mock payment (no real gateway).</span>
            </div>
        </form>

        <p><a href="pay_invoice.php" class="muted">&larr; Back to unpaid invoices</a></p>
    </div>

<?php else: ?>
    
    <h2>Your Unpaid Invoices</h2>

    <?php if (!$unpaid): ?>
        <p>You have no unpaid invoices.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th style="text-align:right;">Amount (JOD)</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($unpaid as $row): ?>
                <tr>
                    <td><?= (int)$row['invoice_id'] ?></td>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td style="text-align:right;"><?= number_format((float)$row['total_amount'], 2) ?></td>
                    <td>
                        <a class="btn" href="pay_invoice.php?invoice_id=<?= (int)$row['invoice_id'] ?>">Pay</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>
