<?php
require_once 'includes/db.php';
require_once 'middleware/admin.php';
require_once 'admin-navbar.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $password, $role]);
}


if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$delete_id]);
    header("Location: manage_users.php");
    exit;
}


$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ?");
    $stmt->execute([$name, $email, $role, $id]);
    header("Location: manage_users.php");
    exit;
}


$search = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_role)) {
    $query .= " AND role = ?";
    $params[] = $filter_role;
}

$query .= " ORDER BY user_id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Manage Users</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --brand:#0d6efd;
      --brand-2:#0b5ed7;
      --bg:#f6f8fb;
      --card:#ffffff;
      --ink:#1f2937;
      --muted:#6b7280;
      --ring:#e5e7eb;
      --shadow:0 8px 24px rgba(15,23,42,.06);
      --radius:14px;
      --success:#10b981;
      --danger:#ef4444;
      --warning:#f59e0b;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      background:var(--bg);
      color:var(--ink);
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    }
    main{max-width:1200px;margin:0 auto;padding:24px 20px 56px}
    h1{font-size:32px;margin:12px 0 14px}
    .sub{color:var(--muted);margin:-6px 0 18px}

    .grid{display:grid;gap:16px}
    @media (min-width:960px){ .grid{grid-template-columns: 1.1fr 2fr} }

    .card{
      background:var(--card);
      border:1px solid var(--ring);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      padding:16px;
    }
    .card h2{margin:0 0 12px;font-size:20px}

    
    .filters{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0 6px}
    .input, select{
      height:40px; padding:0 12px; border:1px solid var(--ring);
      border-radius:10px; background:#fff; min-width:220px; outline:none;
    }
    .btn{
      --bg:var(--brand); --bgh:var(--brand-2);
      border:0; color:#fff; background:var(--bg); height:40px; padding:0 14px;
      border-radius:10px; font-weight:700; box-shadow:0 4px 14px rgba(13,110,253,.18);
      cursor:pointer; transition:.18s;
    }
    .btn:hover{background:var(--bgh);transform:translateY(-1px)}
    .btn.ghost{background:transparent;color:var(--brand);border:1px solid var(--brand);box-shadow:none}
    .btn.danger{--bg:var(--danger);--bgh:#d62828}
    .btn.small{height:34px;padding:0 10px;border-radius:8px;font-weight:700}

    
    .table-wrap{overflow:auto;border-radius:12px;border:1px solid var(--ring);background:#fff;box-shadow:var(--shadow)}
    table{width:100%;border-collapse:collapse;min-width:720px}
    th,td{padding:12px 14px;border-bottom:1px solid var(--ring);text-align:left}
    th{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);background:#fafafa}
    tr:hover td{background:#fcfcff}
    .tag{
      display:inline-flex;align-items:center;gap:6px;
      padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700
    }
    .tag.admin{background:#eef2ff;color:#3730a3}
    .tag.customer{background:#ecfeff;color:#155e75}

    
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .row .input, .row select{flex:1 1 180px}
    .help{color:var(--muted);font-size:13px;margin-top:6px}

    
    .actions{display:flex;gap:8px}
    a.link{color:var(--brand);text-decoration:none;font-weight:700}
    a.link:hover{color:var(--brand-2);text-decoration:underline}

    
    .edit-card{border-left:4px solid var(--warning)}
  </style>
</head>
<body>

  <main>
    <h1>Manage Users</h1>
    <p class="sub">Search, add, edit, and remove users. Use filters to narrow down by role.</p>

    
    <div class="card">
      <h2>Search &amp; Filter</h2>
      <form class="filters" method="GET">
        <input class="input" type="text" name="search" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>">
        <select name="role">
          <option value="">All Roles</option>
          <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
          <option value="customer" <?= $filter_role === 'customer' ? 'selected' : '' ?>>Customer</option>
        </select>
        <button class="btn" type="submit">Search</button>
        <a class="btn ghost" href="manage_users.php">Reset</a>
      </form>
    </div>

    <div class="grid" style="margin-top:16px">
      
      <div class="card">
        <h2><?= $edit_user ? 'Edit User' : 'Add User' ?></h2>

        <?php if (!$edit_user): ?>
          
          <form method="POST">
            <div class="row" style="margin-bottom:10px">
              <input class="input" type="text" name="name" placeholder="Name" required>
              <input class="input" type="email" name="email" placeholder="Email" required>
            </div>
            <div class="row" style="margin-bottom:10px">
              <input class="input" type="password" name="password" placeholder="Password" required>
              <select name="role" class="input">
                <option value="customer">Customer</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <button class="btn" type="submit" name="add_user">Add User</button>
            <p class="help">Password is securely hashed before storing.</p>
          </form>
        <?php else: ?>
          
          <div class="card edit-card">
            <form method="POST">
              <input type="hidden" name="id" value="<?= $edit_user['user_id'] ?>">
              <div class="row" style="margin-bottom:10px">
                <input class="input" type="text" name="name" value="<?= htmlspecialchars($edit_user['name']) ?>" required>
                <input class="input" type="email" name="email" value="<?= htmlspecialchars($edit_user['email']) ?>" required>
              </div>
              <div class="row" style="margin-bottom:10px">
                <select class="input" name="role">
                  <option value="customer" <?= $edit_user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                  <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
              <div class="row">
                <button class="btn" type="submit" name="update_user">Update</button>
                <a class="btn ghost" href="manage_users.php">Cancel</a>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>

      
      <div class="card">
        <h2>User List</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Name</th>
                <th>Email</th>
                <th style="width:140px">Role</th>
                <th style="width:160px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$users): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px">No users found.</td></tr>
              <?php else: ?>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td><?= $u['user_id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                      <span class="tag <?= $u['role']==='admin' ? 'admin' : 'customer' ?>">
                        <?= htmlspecialchars(ucfirst($u['role'])) ?>
                      </span>
                    </td>
                    <td>
                      <div class="actions">
                        <a class="link" href="?edit=<?= $u['user_id'] ?>">Edit</a>
                        <a class="link" style="color:var(--danger)" href="?delete=<?= $u['user_id'] ?>" onclick="return confirm('Delete user?')">Delete</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

</body>
</html>
