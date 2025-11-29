<?php
session_start();
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];

        if ($user['role'] === 'admin') {
            header("Location: admin-dashboard.php");
        } else {
            header("Location: customer-dashboard.php");
        }
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f5f7fb;
    --card:#ffffff;
    --primary:#3b82f6;
    --primary-700:#2563eb;
    --accent:#10b981;
    --text:#0f172a;
    --muted:#6b7280;
    --danger:#ef4444;
    --ring: rgba(59,130,246,.35);
    --shadow: 0 20px 45px rgba(2,6,23,.08);
    --radius: 16px;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
    color:var(--text);
    background:linear-gradient(180deg,#f8fafc 0%, #eef2ff 100%) no-repeat fixed;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px;
  }
  .auth{
    width: min(1120px, 100%);
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    display:grid;
    grid-template-columns: 1.05fr 1fr;
    overflow:hidden;
  }
  
  .art{
    background:linear-gradient(135deg,#e0e7ff 0%, #f0f9ff 100%);
    padding:42px;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .art .frame{
    width:100%;
    max-width:520px;
    background:#fff;
    border-radius:24px;
    box-shadow:0 12px 30px rgba(2,6,23,.08);
    padding:18px;
  }
  .art img{
    width:100%;
    height:auto;
    display:block;
    border-radius:16px;
  }

 
  .pane{
    padding:48px 56px;
  }
  .heading{
    margin:0 0 28px;
  }
  .heading h1{
    font-size:28px;
    line-height:1.2;
    margin:0 0 6px;
    font-weight:700;
    letter-spacing:.2px;
  }
  .heading p{
    margin:0;
    color:var(--muted);
    font-size:14px;
  }
  .field{
    margin-bottom:18px;
  }
  label{
    display:block;
    font-size:13px;
    font-weight:600;
    color:#334155;
    margin:0 0 8px;
  }
  .control{
    width:100%;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:14px 14px;
    font-size:15px;
    outline:none;
    transition:border-color .15s, box-shadow .15s;
  }
  .control:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 4px var(--ring);
  }
  .row{
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
    margin-top:10px;
  }
  .btn{
    appearance:none;
    border:0;
    padding:12px 18px;
    font-weight:600;
    border-radius:12px;
    cursor:pointer;
    transition:transform .04s ease, background-color .15s ease, box-shadow .15s ease;
    letter-spacing:.2px;
  }
  .btn:active{ transform: translateY(1px); }
  .btn-primary{
    background:var(--primary);
    color:#fff;
    box-shadow:0 10px 18px rgba(37,99,235,.18);
  }
  .btn-primary:hover{ background:var(--primary-700); }
  .btn-ghost{
    background:#f1f5f9;
    color:#0f172a;
  }
  .btn-ghost:hover{ background:#e2e8f0; }
  .btn-accent{
    background:var(--accent);
    color:#fff;
    margin-top:6px;
  }
  .divider{
    height:1px;
    background:#eef2f7;
    margin:22px 0;
  }
  .error{
    background: #fee2e2;
    color:#b91c1c;
    border:1px solid #fecaca;
    padding:10px 12px;
    border-radius:10px;
    font-size:14px;
    margin-bottom:16px;
  }
  .foot{
    padding:14px 18px;
    text-align:center;
    color:#64748b;
    font-size:12.5px;
  }
  .foot span{ color:#0f172a; font-weight:600; }

  
  @media (max-width: 980px){
    .auth{ grid-template-columns: 1fr; }
    .art{ display:none; }
    .pane{ padding:36px 28px; }
  }
</style>
</head>
<body>

  <main class="auth">
   
    <aside class="art">
      <div class="frame">
       
        <img src="https://images.unsplash.com/photo-1556745753-b2904692b3cd?q=80&w=1200&auto=format&fit=crop" alt="Online payment illustration">
      </div>
    </aside>

    
    <section class="pane">
      <header class="heading">
        <h1>Login To Your Account</h1>
        <p>Welcome back! Please enter your details.</p>
      </header>

      <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="field">
          <label for="email">Email Address</label>
          <input class="control" id="email" type="email" name="email" placeholder="you@example.com" required />
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input class="control" id="password" type="password" name="password" placeholder="••••••••" required />
        </div>

        <div class="row">
          <button type="submit" class="btn btn-primary">Sign In</button>
        </div>

        <div class="divider"></div>

        <button type="button" class="btn btn-accent" onclick="window.location.href='register.php'">
          Register
        </button>
      </form>
    </section>
  </main>

</body>
</html>
