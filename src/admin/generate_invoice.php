<?php

require_once __DIR__ . '/middleware/admin.php';
require_once __DIR__ . '/includes/db.php';



$flash = '';
$errors = [];


function getBillingConfig(): array {
    return [
        'rates' => [
            'call' => 0.10, 
            'sms'  => 0.02, 
            'data' => 0.005 
        ],
        'tax_percent' => 16.0,   
        'discount_jod' => 0.0,   
    ];
}


function fetchCustomers(PDO $pdo): array {
    $stmt = $pdo->query("SELECT user_id, name, email FROM users WHERE role = 'customer' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function fetchUsageSummary(PDO $pdo, int $userId, string $start, string $end): array {
    $sql = "SELECT type, COALESCE(SUM(amount),0) AS total
            FROM usage_records
            WHERE user_id = :uid
              AND recorded_at >= :start
              AND recorded_at <  :end_plus
            GROUP BY type";
    
    $endPlus = (new DateTime($end))->modify('+1 day')->format('Y-m-d');

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'      => $userId,
        ':start'    => $start,
        ':end_plus' => $endPlus
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    $summary = ['call' => 0.0, 'sms' => 0.0, 'data' => 0.0];
    foreach ($rows as $r) {
        $t = strtolower($r['type']);
        if (isset($summary[$t])) {
            $summary[$t] = (float)$r['total'];
        }
    }
    return $summary;
}


function buildInvoiceLines(array $usage, array $config): array {
    $lines = [];
    $subtotal = 0.0;

    $rates = $config['rates'];
    
    if ($usage['call'] > 0) {
        $amt = round($usage['call'] * $rates['call'], 3);
        $lines[] = ['description' => "Calls: {$usage['call']} min @ " . $rates['call'] . " JOD/min", 'amount' => $amt];
        $subtotal += $amt;
    }
    
    if ($usage['sms'] > 0) {
        $amt = round($usage['sms'] * $rates['sms'], 3);
        $lines[] = ['description' => "SMS: {$usage['sms']} msgs @ " . $rates['sms'] . " JOD/SMS", 'amount' => $amt];
        $subtotal += $amt;
    }
    
    if ($usage['data'] > 0) {
        $amt = round($usage['data'] * $rates['data'], 3);
        $lines[] = ['description' => "Data: {$usage['data']} MB @ " . $rates['data'] . " JOD/MB", 'amount' => $amt];
        $subtotal += $amt;
    }

    
    if (!empty($config['discount_jod'])) {
        $disc = (float)$config['discount_jod'];
        if ($disc > 0) {
            $lines[] = ['description' => "Discount", 'amount' => -$disc];
            $subtotal -= $disc;
        }
    }

    
    $tax = 0.0;
    if (!empty($config['tax_percent'])) {
        $tax = round($subtotal * ((float)$config['tax_percent'] / 100), 3);
        if ($tax > 0) {
            $lines[] = ['description' => "Tax (" . $config['tax_percent'] . "%)", 'amount' => $tax];
            $subtotal += $tax;
        }
    }

    $total = max(0, round($subtotal, 3));
    return [$lines, $total];
}


function insertInvoice(PDO $pdo, int $userId, string $invoiceDate, array $lines, float $total): int {
    $pdo->beginTransaction();
    try {
        $insInv = $pdo->prepare("INSERT INTO invoices (user_id, date, total_amount, status) VALUES (:uid, :d, :t, 'unpaid')");
        $insInv->execute([
            ':uid' => $userId,
            ':d'   => $invoiceDate,
            ':t'   => $total
        ]);
        $invoiceId = (int)$pdo->lastInsertId();

        if (!empty($lines)) {
            $insLine = $pdo->prepare("INSERT INTO invoice_lines (invoice_id, description, amount) VALUES (:iid, :desc, :amt)");
            foreach ($lines as $ln) {
                $insLine->execute([
                    ':iid'  => $invoiceId,
                    ':desc' => $ln['description'],
                    ':amt'  => $ln['amount']
                ]);
            }
        }

        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$customers = fetchCustomers($pdo);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $start  = trim($_POST['start_date'] ?? '');
    $end    = trim($_POST['end_date'] ?? '');

    
    if (!$userId) {
        $errors[] = 'Please choose a customer.';
    }
    if (!$start || !$end) {
        $errors[] = 'Please select both start and end dates.';
    } else {
        
        $ds = DateTime::createFromFormat('Y-m-d', $start);
        $de = DateTime::createFromFormat('Y-m-d', $end);
        if (!$ds || !$de) {
            $errors[] = 'Dates must be in YYYY-MM-DD format.';
        } elseif ($ds > $de) {
            $errors[] = 'Start date must be before or equal to end date.';
        }
    }

    if (!$errors) {
        try {
            $config = getBillingConfig();

            
            $usage = fetchUsageSummary($pdo, $userId, $start, $end);

            
            if ($usage['call'] == 0 && $usage['sms'] == 0 && $usage['data'] == 0) {
                $flash = "No usage found for this user in the selected period. No invoice was created.";
            } else {
                
                [$lines, $total] = buildInvoiceLines($usage, $config);

                
                $invoiceId = insertInvoice($pdo, $userId, $end, $lines, $total);

                $flash = "Invoice #{$invoiceId} generated successfully for user ID {$userId}.";
            }
        } catch (Throwable $ex) {
            $errors[] = "Error while generating invoice: " . htmlspecialchars($ex->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Generate Invoice • Single Customer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --brand:#0d6efd; --brand-2:#0b5ed7;
      --bg:#f6f8fb; --card:#ffffff; --ink:#1f2937; --muted:#6b7280;
      --ring:#e5e7eb; --shadow:0 8px 24px rgba(15,23,42,.06); --radius:14px;
      --success:#10b981; --danger:#ef4444;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
    main{max-width:1100px;margin:0 auto;padding:24px 18px 56px}
    h1{font-size:32px;margin:10px 0 16px}
    p.sub{color:var(--muted);margin:-6px 0 18px}

    .card{background:var(--card);border:1px solid var(--ring);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
    .grid{display:grid;gap:16px}
    @media (min-width:980px){ .grid{grid-template-columns:1fr 1.2fr} }

    label{display:block;margin:10px 0 6px;font-weight:700}
    select,input[type="date"]{
      width:100%;height:42px;padding:0 12px;border:1px solid var(--ring);border-radius:10px;background:#fff;outline:none;
    }
    .btn{
      --bg:var(--brand); --bgh:var(--brand-2);
      display:inline-flex;align-items:center;gap:8px;height:42px;padding:0 16px;border-radius:10px;border:0;color:#fff;
      background:var(--bg);font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(13,110,253,.18);transition:.18s;margin-top:10px
    }
    .btn:hover{background:var(--bgh);transform:translateY(-1px)}

    .alert{border:1px solid var(--ring);border-left-width:6px;border-radius:12px;padding:12px 14px;background:#fff;box-shadow:var(--shadow);margin:12px 0}
    .alert.success{border-left-color:var(--success)}
    .alert.error{border-left-color:var(--danger)}
    .alert ul{margin:0 0 0 18px}

    .table-wrap{overflow:auto;border-radius:12px;border:1px solid var(--ring);background:#fff;box-shadow:var(--shadow)}
    table{width:100%;border-collapse:collapse;min-width:680px}
    th,td{padding:12px 14px;border-bottom:1px solid var(--ring);text-align:left}
    th{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);background:#fafafa}

    .muted{color:var(--muted)}
    .right{text-align:right}
  </style>
</head>
<body>

<?php include __DIR__ . '/admin-navbar.php'; ?>

<main>
  <h1>Generate Invoice (Single Customer)</h1>
  <p class="sub">Create an invoice for one customer within a specific date range. We’ll total usage and add line items automatically.</p>

  <?php if (!empty($flash)): ?>
    <div class="alert success"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert error">
      <strong>We hit a snag:</strong>
      <ul>
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="grid">
    
    <section class="card">
      <h2 style="margin:0 0 8px">Generate for one customer</h2>
      <form method="post">
        <label>Customer</label>
        <select name="user_id" required>
          <option value="">— choose customer —</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['user_id'] ?>"
              <?= isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$c['user_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <label>Start date</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>" required>

        <label>End date</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>" required>

        <button type="submit" class="btn">Generate Invoice</button>
      </form>

      <p class="muted" style="margin-top:10px">
        We sum usage in <code>usage_records</code> between these dates and create an entry in <code>invoices</code> plus rows in <code>invoice_lines</code>.
      </p>
    </section>

    
    <section class="card">
      <h2 style="margin:0 0 8px">What gets billed</h2>
      <div class="table-wrap" style="margin-top:10px">
        <table>
          <thead><tr><th>Type</th><th>Basis</th><th class="right">Example rate</th></tr></thead>
          <tbody>
            <tr><td>Calls</td><td>Minutes</td><td class="right">0.10 JOD / min</td></tr>
            <tr><td>SMS</td><td>Messages</td><td class="right">0.02 JOD / msg</td></tr>
            <tr><td>Data</td><td>MB</td><td class="right">0.005 JOD / MB</td></tr>
          </tbody>
        </table>
      </div>
      <p class="muted" style="margin-top:10px">
        Taxes/discounts configured in your billing settings are applied automatically on top of the subtotal.
      </p>
    </section>
  </div>

  <?php
  

  if (!empty($flash) && isset($usage, $lines, $total)) :
  ?>
    <section class="card" style="margin-top:16px">
      <h2 style="margin:0 0 10px">Invoice Preview</h2>

      <div class="table-wrap" style="margin-top:8px">
        <table>
          <thead><tr><th>Usage Type</th><th class="right">Quantity</th></tr></thead>
          <tbody>
            <tr><td>Calls (min)</td><td class="right"><?= number_format((float)($usage['call'] ?? 0), 3) ?></td></tr>
            <tr><td>SMS (msg)</td><td class="right"><?= number_format((float)($usage['sms'] ?? 0), 3) ?></td></tr>
            <tr><td>Data (MB)</td><td class="right"><?= number_format((float)($usage['data'] ?? 0), 3) ?></td></tr>
          </tbody>
        </table>
      </div>

      <div class="table-wrap" style="margin-top:14px">
        <table>
          <thead><tr><th>Description</th><th class="right">Amount (JOD)</th></tr></thead>
          <tbody>
            <?php foreach ($lines as $ln): ?>
              <tr>
                <td><?= htmlspecialchars($ln['description']) ?></td>
                <td class="right"><?= number_format((float)$ln['amount'], 3) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <th class="right">Total</th>
              <th class="right"><?= number_format((float)$total, 3) ?></th>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

</main>
</body>
</html>
