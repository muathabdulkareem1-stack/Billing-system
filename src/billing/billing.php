<?php


require_once __DIR__ . '/db.php';      .
$__RATES__ = [
    'call_per_min' => 0.050,     
    'sms_per_msg'  => 0.010,     
    'data_per_mb'  => 0.001,     
    'tax_rate'     => 0.16,      
    'discount'     => 0.00,      
    'late_fee'     => 0.000,     
];
$ratesPath = __DIR__ . '/rates.php';
if (is_file($ratesPath)) {
    require_once $ratesPath;
    //  a variety of ways the rates might be exposed
    if (function_exists('app_rates')) {
        $__RATES__ = array_merge($__RATES__, (array)app_rates());
    } elseif (function_exists('getRates')) {
        $__RATES__ = array_merge($__RATES__, (array)getRates());
    } elseif (isset($RATES) && is_array($RATES)) {
        $__RATES__ = array_merge($__RATES__, $RATES);
    } elseif (defined('RATES') && is_array(RATES)) {
        $__RATES__ = array_merge($__RATES__, RATES);
    }
}

/**
 * return first/last day of previous calendar month 
 */
function lastMonthPeriod(): array {
    $start = date('Y-m-01', strtotime('first day of previous month'));
    $end   = date('Y-m-t',  strtotime('last day of previous month'));
    return ['start' => $start, 'end' => $end];
}


function __billing_rates(): array {
    global $__RATES__;
    return $__RATES__;
}


function generateInvoiceForUser(PDO $pdo, int $userId, string $startDate, string $endDate): ?int
{
    $rates = __billing_rates();

    
    $sql = "
        SELECT type, COALESCE(SUM(amount),0) AS total
        FROM usage_records
        WHERE user_id = :uid
          AND DATE(recorded_at) BETWEEN :s AND :e
        GROUP BY type
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid'=>$userId, ':s'=>$startDate, ':e'=>$endDate]);
    $usage = ['call'=>0,'sms'=>0,'data'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $t = strtolower($row['type']);
        if (isset($usage[$t])) $usage[$t] = (float)$row['total'];
    }

    
    $lines = [];
    $subtotal = 0.0;

    if ($usage['call'] > 0) {
        $amt = round($usage['call'] * $rates['call_per_min'], 3);
        $lines[] = ['description' => "Calls ({$usage['call']} min)", 'amount' => $amt];
        $subtotal += $amt;
    }
    if ($usage['sms'] > 0) {
        $amt = round($usage['sms'] * $rates['sms_per_msg'], 3);
        $lines[] = ['description' => "SMS ({$usage['sms']} msg)", 'amount' => $amt];
        $subtotal += $amt;
    }
    if ($usage['data'] > 0) {
        $amt = round($usage['data'] * $rates['data_per_mb'], 3);
        $lines[] = ['description' => "Data ({$usage['data']} MB)", 'amount' => $amt];
        $subtotal += $amt;
    }

    if ($subtotal <= 0) {
        
        return null;
    }

    
    if (!empty($rates['discount'])) {
        $discAmt = round($subtotal * (float)$rates['discount'], 3);
        if ($discAmt > 0) {
            $lines[] = ['description' => 'Discount', 'amount' => -$discAmt];
            $subtotal -= $discAmt;
        }
    }


    $tax = round($subtotal * (float)$rates['tax_rate'], 3);
    if ($tax > 0) {
        $lines[] = ['description' => 'Tax (VAT)', 'amount' => $tax];
    }

    
    $lateFee = 0.0;
    if (!empty($rates['late_fee'])) {
        $lateFee = round((float)$rates['late_fee'], 3);
        if ($lateFee > 0) {
            $lines[] = ['description' => 'Late Fee', 'amount' => $lateFee];
        }
    }

    $total = round($subtotal + $tax + $lateFee, 3);

    
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("
            INSERT INTO invoices (user_id, date, total_amount, status)
            VALUES (:uid, :d, :tot, 'unpaid')
        ");
        $ins->execute([
            ':uid' => $userId,
            ':d'   => $endDate,
            ':tot' => $total
        ]);
        $invoiceId = (int)$pdo->lastInsertId();

        
        $lineIns = $pdo->prepare("
            INSERT INTO invoice_lines (invoice_id, description, amount)
            VALUES (:iid, :desc, :amt)
        ");
        foreach ($lines as $ln) {
            $lineIns->execute([
                ':iid'  => $invoiceId,
                ':desc' => $ln['description'],
                ':amt'  => $ln['amount'],
            ]);
        }

        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        // Log in real app
        return null;
    }
}


function generateInvoicesForPeriod(PDO $pdo, array $period): array
{
    $start = $period['start'];
    $end   = $period['end'];

    
    $sql = "
        SELECT DISTINCT u.user_id
        FROM users u
        JOIN usage_records ur ON ur.user_id = u.user_id
        WHERE u.role = 'customer'
          AND DATE(ur.recorded_at) BETWEEN :s AND :e
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':s'=>$start, ':e'=>$end]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $created = 0;

    foreach ($ids as $uid) {
        
        $exists = $pdo->prepare("
            SELECT invoice_id FROM invoices
            WHERE user_id = ? AND date BETWEEN ? AND ?
            LIMIT 1
        ");
        $exists->execute([(int)$uid, $start, $end]);
        if ($exists->fetch()) {
            continue;
        }

        $id = generateInvoiceForUser($pdo, (int)$uid, $start, $end);
        if ($id) $created++;
    }

    return ['created' => $created];
}
