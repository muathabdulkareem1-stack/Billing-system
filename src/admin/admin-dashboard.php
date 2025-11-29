<?php
require_once __DIR__ . '/middleware/admin.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/billing.php';


$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_last_month'])) {
    $stats = generateInvoicesForPeriod($pdo, lastMonthPeriod());
    $flash = "Created {$stats['created']} invoice(s) for last month.";
}


$totalCustomers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalInvoices    = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$unpaidInvoices   = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status='unpaid'")->fetchColumn();
$totalRevenuePaid = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status='paid'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --brand:#0d6efd;
      --brand-2:#0b5ed7;
      --bg:#f6f8fb;
      --card:#ffffff;
      --ink:#1f2937;
      --muted:#6b7280;
      --ok:#10b981;
      --warn:#f59e0b;
      --danger:#ef4444;
      --ring:#e5e7eb;
      --shadow:0 8px 24px rgba(15,23,42,.06);
      --radius:14px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      color:var(--ink);
      background:var(--bg);
    }

    
    main{
      max-width:1200px;
      margin:0 auto;
      padding:24px 20px 56px;
    }
    h1{
      margin:12px 0 6px;
      font-size:34px;
      letter-spacing:.2px;
    }

    
    .flash{
      background:#f0fff4;
      border:1px solid #bbf7d0;
      color:#065f46;
      padding:12px 14px;
      border-radius:12px;
      margin:10px 0 18px;
      display:flex; align-items:center; gap:10px;
      box-shadow:var(--shadow);
    }

    
    .kpis{
      display:grid;
      grid-template-columns: repeat(12, 1fr);
      gap:16px;
      margin:18px 0 26px;
    }
    .kpi{
      grid-column: span 12;
      background:var(--card);
      border:1px solid var(--ring);
      border-radius:var(--radius);
      padding:18px;
      box-shadow:var(--shadow);
    }
    @media (min-width:720px){
      .kpi{ grid-column: span 3; }
    }
    .kpi h3{
      margin:0 0 6px;
      font-size:14px;
      color:var(--muted);
      font-weight:600;
      letter-spacing:.2px;
    }
    .kpi .value{
      font-size:28px;
      font-weight:800;
      line-height:1.2;
    }

    
    section{
      margin-top:24px;
    }
    .section-title{
      font-size:22px;
      margin:0 0 10px;
      display:flex; align-items:center; gap:10px;
    }


    .card{
      background:var(--card);
      border:1px solid var(--ring);
      border-radius:var(--radius);
      padding:18px;
      box-shadow:var(--shadow);
    }

    
    .actions{ display:flex; gap:10px; flex-wrap:wrap; }
    .btn{
      --bg: var(--brand);
      --bg-h: var(--brand-2);
      color:#fff;
      background:var(--bg);
      border:0;
      padding:10px 14px;
      border-radius:10px;
      font-weight:700;
      text-decoration:none;
      display:inline-flex; align-items:center; gap:8px;
      box-shadow:0 4px 14px rgba(13,110,253,.18);
      transition:.18s;
    }
    .btn:hover{ background:var(--bg-h); transform:translateY(-1px) }
    .btn.secondary{
      --bg:#111827; --bg-h:#000;
      box-shadow:0 4px 14px rgba(17,24,39,.15);
    }
    .btn.ghost{
      background:transparent; color:var(--brand);
      border:1px solid var(--brand);
      box-shadow:none;
    }
    .hint{ color:var(--muted); margin-top:10px; font-size:14px; }


    .icon{
      width:26px; height:26px; border-radius:50%;
      display:inline-grid; place-items:center;
      color:#fff; font-size:14px;
    }
    .i-green{ background:var(--ok) }
    .i-amber{ background:var(--warn) }
    .i-blue{ background:var(--brand) }
  </style>
</head>
<body>

  <?php include __DIR__.'/admin-navbar.php'; ?>

  <main>
    <h1>Dashboard</h1>

    <?php if ($flash): ?>
      <div class="flash">
        <span class="icon i-green">✓</span>
        <strong><?= htmlspecialchars($flash) ?></strong>
      </div>
    <?php endif; ?>

    
    <div class="kpis">
      <div class="kpi">
        <h3>Total Customers</h3>
        <div class="value"><?= number_format($totalCustomers) ?></div>
      </div>
      <div class="kpi">
        <h3>Total Invoices</h3>
        <div class="value"><?= number_format($totalInvoices) ?></div>
      </div>
      <div class="kpi">
        <h3>Unpaid Invoices</h3>
        <div class="value"><?= number_format($unpaidInvoices) ?></div>
      </div>
      <div class="kpi">
        <h3>Total Revenue (Paid)</h3>
        <div class="value"><?= number_format($totalRevenuePaid, 3) ?> JOD</div>
      </div>
    </div>

    
    <section>
      <h2 class="section-title">
        <span class="icon i-blue">+</span> Quick Actions
      </h2>
      <div class="actions">
        <a href="add_usage.php" class="btn">
          ➕ Add Usage Record
        </a>
        <a href="usage_data.php" class="btn ghost">
          📊 View Usage Data
        </a>
      </div>
    </section>

    
    <section>
      <h2 class="section-title">
        <span class="icon i-amber">⚡</span> Billing Actions
      </h2>
      <div class="card">
        <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <button type="submit" name="gen_last_month" class="btn secondary">
            Generate Last Month Invoices (All Customers)
          </button>
          <a href="generate_invoice.php" class="btn ghost">Custom Range…</a>
        </form>
        <p class="hint">
          Uses <code>usage_records</code> and your billing rates to create invoices and line items per customer.
        </p>
      </div>
    </section>
  </main>

</body>
</html>
