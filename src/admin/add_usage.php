<?php

require_once __DIR__ . '/middleware/admin.php';
require_once __DIR__ . '/includes/db.php';

$users = $pdo->query("SELECT user_id, name FROM users WHERE role='customer' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$msg = '';
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $type   = $_POST['type'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $when   = $_POST['when'] ?? date('Y-m-d H:i:s');

    if (!$userId || !in_array($type, ['call','sms','data'], true) || $amount <= 0) {
        $msg = 'Please fill all fields correctly.';
        $isError = true;
    } else {
        $ins = $pdo->prepare("
            INSERT INTO usage_records (user_id, type, amount, recorded_at)
            VALUES (:uid, :t, :a, :w)
        ");
        $ins->execute([
            ':uid' => $userId,
            ':t'   => $type,
            ':a'   => $amount,
            ':w'   => $when,
        ]);
        $msg = 'Usage record inserted.';
        
        $_POST = [];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Add Usage</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    :root{
      --ring:#e5e7eb; --ink:#1f2937; --muted:#6b7280;
      --card:#ffffff; --bg:#f6f8fb; --brand:#0d6efd; --brand-2:#0b5ed7;
    }
    body{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
    main.container{max-width:1100px;margin:0 auto;padding:18px}
    h1{margin:8px 0 16px}
    .card{
      background:var(--card); border:1px solid var(--ring); border-radius:12px;
      padding:18px; box-shadow:0 3px 10px rgba(0,0,0,.03); max-width:640px;
    }
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .grid .full{grid-column:1 / -1}
    label{display:block;font-weight:700;margin:2px 0 6px}
    select,input{width:100%;padding:10px 12px;border:1px solid var(--ring);border-radius:10px;outline:none}
    input:focus,select:focus{border-color:#9cc5ff;box-shadow:0 0 0 3px rgba(13,110,253,.15)}
    .help{color:var(--muted);font-size:.9rem}
    .actions{display:flex;gap:10px;align-items:center;margin-top:10px}
    .btn{
      display:inline-block;border:1px solid var(--ring);background:#f9fafb;padding:10px 14px;border-radius:10px;
      text-decoration:none;color:var(--ink);font-weight:700
    }
    .btn:hover{background:#f1f5f9}
    .btn.primary{
      background:var(--brand);color:#fff;border-color:var(--brand);
      box-shadow:0 4px 12px rgba(13,110,253,.18)
    }
    .btn.primary:hover{background:var(--brand-2)}
    .alert{
      border-radius:12px;padding:10px 14px;margin:0 0 14px;border:1px solid transparent;font-weight:600
    }
    .alert.success{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
    .alert.error{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <main class="container">
    <h1>Add Usage</h1>

    <?php if ($msg): ?>
      <div class="alert <?= $isError ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post" class="card" novalidate>
      <div class="grid">
        <div class="full">
          <label for="user_id">User</label>
          <select id="user_id" name="user_id" required autofocus>
            <option value="">Choose…</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['user_id']; ?>"
                <?= (isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$u['user_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label for="type">Type</label>
          <select id="type" name="type" required>
            <?php
              $t = $_POST['type'] ?? 'call';
              $opts = ['call' => 'call (minutes)','sms' => 'sms (count)','data' => 'data (MB)'];
              foreach ($opts as $k=>$v){
                $sel = $t === $k ? 'selected' : '';
                echo "<option value=\"$k\" $sel>$v</option>";
              }
            ?>
          </select>
        </div>

        <div>
          <label for="amount">Amount</label>
          <input id="amount" type="number" step="0.001" name="amount"
                 placeholder="e.g., 12.5" required
                 value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
          <div class="help">Calls in minutes, SMS in count, Data in MB.</div>
        </div>

        <div class="full">
          <label for="when">Recorded at</label>
          <input id="when" type="datetime-local" name="when"
                 value="<?= htmlspecialchars($_POST['when'] ?? date('Y-m-d\TH:i')) ?>">
        </div>
      </div>

      <div class="actions">
        <button type="submit" class="btn primary">Insert</button>
        <a href="usage_data.php" class="btn">Back</a>
      </div>
    </form>
  </main>
</body>
</html>
