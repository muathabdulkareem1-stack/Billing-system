<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Navbar</title>
<style>
  :root{
    --brand:#0d6efd;
    --brand-hover:#0b5ed7;
    --bg:#ffffff;
    --nav-bg:#f8fafc;
    --text:#1f2937;
    --muted:#6b7280;
    --radius:10px;
    --shadow:0 2px 8px rgba(0,0,0,.06);
  }
  body{
    margin:0;
    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background:var(--bg);
    color:var(--text);
  }
  nav{
    display:flex;
    align-items:center;
    background:var(--nav-bg);
    padding:12px 20px;
    box-shadow:var(--shadow);
    position:sticky;
    top:0;
    z-index:10;
  }
  .nav-links{
    display:flex;
    gap:18px;
  }
  .nav-links a{
    text-decoration:none;
    color:var(--text);
    font-weight:500;
    padding:6px 10px;
    border-radius:var(--radius);
    transition:.2s;
  }
  .nav-links a:hover{
    background:var(--brand);
    color:#fff;
  }
  .spacer{
    flex:1;
  }
  .user-info{
    display:flex;
    align-items:center;
    gap:12px;
    font-size:14px;
    color:var(--muted);
  }
  .logout{
    background:var(--brand);
    color:#fff;
    text-decoration:none;
    padding:6px 14px;
    border-radius:var(--radius);
    font-size:14px;
    font-weight:600;
    transition:.2s;
  }
  .logout:hover{
    background:var(--brand-hover);
  }
</style>
</head>
<body>

<nav>
  <div class="nav-links">
    <a href="admin-dashboard.php">Dashboard</a>
    <a href="manage_users.php">Manage Users</a>
    <a href="generate_invoice.php">Invoices</a>
    <a href="reports.php">Reports</a>
  </div>
  <div class="spacer"></div>
  <div class="user-info">
    <span>Hello, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
    <a href="logout.php" class="logout">Logout</a>
  </div>
</nav>

</body>
</html>
