<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userName = $_SESSION['user_name'] ?? 'Customer';


if (empty($GLOBALS['__NAVBAR_STYLES__'])): $GLOBALS['__NAVBAR_STYLES__'] = true; ?>
<style>
  :root{
    --brand:#0d6efd; --brand-2:#0b5ed7;
    --ink:#1f2937; --muted:#6b7280; --ring:#e5e7eb;
    --bg:#f6f8fb; --card:#ffffff;
  }
  .navbar{
    position:sticky; top:0; z-index:50;
    background:linear-gradient(180deg, rgba(13,110,253,.10), rgba(13,110,253,0));
    border-bottom:1px solid var(--ring);
    backdrop-filter:saturate(180%) blur(8px);
  }
  .navbar .container{
    max-width:1200px; margin:0 auto; padding:12px 18px;
    display:flex; align-items:center; gap:14px; flex-wrap:wrap;
  }
  .navbar a{
    color:var(--ink); text-decoration:none; font-weight:700;
    padding:8px 10px; border-radius:10px;
    transition:color .15s, background .15s, transform .15s;
  }
  .navbar a:hover{ color:var(--brand-2); background:#eef5ff; transform:translateY(-1px) }
  .navbar .spacer{ flex:1 }
  .navbar .pill{
    background:var(--brand); color:#fff; border:0; border-radius:10px;
    padding:8px 12px; font-weight:800; box-shadow:0 4px 14px rgba(13,110,253,.18);
  }
  @media (max-width:640px){
    .navbar .container{ gap:8px }
  }
</style>
<?php endif; ?>

<header class="navbar">
  <nav class="container">
    <a href="customer-dashboard.php">Dashboard</a>
    <a href="customer-invoices.php">Invoices</a>
    <a href="customer-usage.php">Usage</a>
    <a href="customer-billing-history.php">Billing History</a>
    <span class="spacer"></span>
    <span class="muted" style="color:var(--muted);font-weight:600;">Hello, <?= htmlspecialchars($userName) ?></span>
    <a class="pill" href="logout.php">Logout</a>
  </nav>
</header>
