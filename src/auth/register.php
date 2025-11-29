<?php
require_once 'includes/db.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'customer';

    if ($name && $email && $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            $success = "Account created successfully. You can now sign in.";
        } catch (PDOException $e) {
            
            $error = "Registration failed: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Create Your Account</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    --brand:#0d6efd;
    --brand-600:#0b5ed7;
    --bg:#f4f6f9;
    --card:#ffffff;
    --text:#1f2937;
    --muted:#6b7280;
    --error-bg:#fde2e2;
    --error:#b00020;
    --success-bg:#e6f6ea;
    --success:#267a3f;
    --radius:12px;
    --shadow:0 12px 30px rgba(0,0,0,.08);
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    color:var(--text);
    background:var(--bg);
  }

  .auth-wrap{
    min-height:100vh;
    display:grid;
    grid-template-columns:1.1fr 1fr;
  }

  .side{
    background:var(--card);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px;
    border-right:1px solid #eef1f5;
  }
  .side-illustration{
    width: min(640px, 92%);
    max-width:680px;
    aspect-ratio: 4/3;
    background:#eff6ff;
    border:1px solid #e5efff;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
  }
  .side-illustration img{
    width:90%;
    height:auto;
    object-fit:contain;
  }

  .panel{
    display:flex;
    align-items:center;
    justify-content:center;
    padding:48px clamp(24px,4vw,64px);
  }
  .card{
    width:min(520px, 100%);
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    padding:36px;
  }

  .header{
    margin-bottom:22px;
  }
  .title{
    margin:0 0 6px 0;
    font-size:28px;
    font-weight:700;
    letter-spacing:.2px;
  }
  .subtitle{
    margin:0;
    color:var(--muted);
    font-size:14px;
  }

  .alert{
    padding:12px 14px;
    border-radius:10px;
    font-size:14px;
    margin-bottom:16px;
  }
  .alert.error{ background:var(--error-bg); color:var(--error); }
  .alert.success{ background:var(--success-bg); color:var(--success); }

  .form-grid{
    display:grid;
    gap:14px;
    margin-top:10px;
  }
  label{
    font-size:13px;
    font-weight:600;
    margin-bottom:6px;
    display:block;
    color:#374151;
  }
  input,select{
    width:100%;
    padding:12px 14px;
    font-size:15px;
    border:1px solid #e5e7eb;
    border-radius:10px;
    background:#fcfcfd;
    outline:none;
    transition:border-color .2s, box-shadow .2s;
  }
  input:focus, select:focus{
    border-color:var(--brand);
    box-shadow:0 0 0 4px rgba(13,110,253,.12);
  }

  .actions{
    margin-top:8px;
    display:flex;
    gap:12px;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
  }
  .btn{
    appearance:none;
    border:none;
    cursor:pointer;
    padding:12px 16px;
    border-radius:10px;
    font-weight:700;
    font-size:15px;
    letter-spacing:.2px;
  }
  .btn-primary{
    background:var(--brand);
    color:#fff;
  }
  .btn-primary:hover{ background:var(--brand-600); }
  .link{
    color:var(--brand);
    text-decoration:none;
    font-weight:600;
  }
  .link:hover{ text-decoration:underline; }

  .footer{
    margin-top:18px;
    text-align:center;
    color:var(--muted);
    font-size:12px;
  }

  @media (max-width: 980px){
    .auth-wrap{ grid-template-columns:1fr; }
    .side{ display:none; }
    .panel{ padding:32px 20px; }
  }
</style>
</head>
<body>

<div class="auth-wrap">

  
  <aside class="side">
    <div class="side-illustration">
      <img src="https://cdn-icons-png.flaticon.com/512/2950/2950672.png" alt="Register Illustration" />
    </div>
  </aside>


  <main class="panel">
    <section class="card">
      <header class="header">
        <h1 class="title">Create your account</h1>
        <p class="subtitle">Fill in your details to get started.</p>
      </header>

      <?php if (!empty($error)): ?>
        <div class="alert error"><?= $error ?></div>
      <?php elseif (!empty($success)): ?>
        <div class="alert success"><?= $success ?></div>
      <?php endif; ?>

      <form method="POST" class="form-grid" novalidate>
        <div>
          <label for="name">Full Name</label>
          <input id="name" name="name" type="text" placeholder="John Doe" required />
        </div>

        <div>
          <label for="email">Email Address</label>
          <input id="email" name="email" type="email" placeholder="you@example.com" required />
        </div>

        <div>
          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="Create a strong password" required />
        </div>

        <div>
          <label for="role">Role</label>
          <select id="role" name="role">
            <option value="customer" selected>Customer</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="submit">Register</button>
          <a class="link" href="login.php">Already have an account? Sign in</a>
        </div>
      </form>

      <div class="footer">CompanyName © 2025. All rights reserved.</div>
    </section>
  </main>

</div>

</body>
</html>
