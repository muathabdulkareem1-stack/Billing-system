<?php
session_start();
require_once 'includes/db.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];


$stmt = $pdo->prepare("
    SELECT * FROM invoices 
    WHERE user_id = ? 
      AND date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
");
$stmt->execute([$user_id]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Invoices - Last Month</title>
    <style>
        table { border-collapse: collapse; width: 80%; margin: 20px auto; }
        th, td { border: 1px solid #999; padding: 10px; text-align: center; }
        th { background-color: #f2f2f2; }
        h2, .center { text-align: center; }
        .btn { padding: 8px 16px; text-decoration: none; background-color: green; color: white; border-radius: 5px; }
    </style>
</head>
<body>

    <h2>Your Invoices (Past Month)</h2>

    <?php if (count($invoices) > 0): ?>
        <div class="center">
            <a href="monthly_invoice_report.php" target="_blank" class="btn">Download All as PDF</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?= $invoice['invoice_id'] ?></td>
                        <td><?= $invoice['date'] ?></td>
                        <td><?= $invoice['total_amount'] ?> JOD</td>
                        <td><?= ucfirst($invoice['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="center">No invoices found for the past month.</p>
    <?php endif; ?>

</body>
</html>
