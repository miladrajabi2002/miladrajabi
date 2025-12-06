<?php
// ════════════════════════════════════════════════════════════════
// Incomes API
// ════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            client_name,
            client_username,
            service_type,
            monthly_amount,
            payment_day,
            start_date,
            bot_url,
            is_active,
            DATEDIFF(CURDATE(), start_date) as days_passed,
            TIMESTAMPDIFF(MONTH, start_date, CURDATE()) as months_passed
        FROM incomes 
        ORDER BY is_active DESC, monthly_amount DESC
    ");
    $stmt->execute();
    $incomes = $stmt->fetchAll();
    
    $total_active = 0;
    $total_inactive = 0;
    $monthly_total = 0;
    $yearly_total = 0;
    $total_earned_all_time = 0;
    
    foreach ($incomes as &$income) {
        $months = $income['months_passed'] > 0 ? $income['months_passed'] : 1;
        $income['months'] = $months;
        $income['total_earned'] = $income['monthly_amount'] * $months;
        $total_earned_all_time += $income['total_earned'];
        
        if ($income['is_active']) {
            $total_active++;
            $monthly_total += $income['monthly_amount'];
            $yearly_total += $income['monthly_amount'] * 12;
        } else {
            $total_inactive++;
        }
        
        $income['start_date_fa'] = jdate('j F Y', strtotime($income['start_date']));
        
        if ($income['payment_day'] && $income['is_active']) {
            $today = (int)date('d');
            $payment_day = (int)$income['payment_day'];
            
            if ($payment_day >= $today) {
                $income['days_until_payment'] = $payment_day - $today;
            } else {
                $days_in_month = (int)date('t');
                $income['days_until_payment'] = ($days_in_month - $today) + $payment_day;
            }
        }
    }
    
    $average_per_client = $total_active > 0 ? $monthly_total / $total_active : 0;
    
    jsonResponse(true, [
        'incomes' => $incomes,
        'stats' => [
            'total_active' => $total_active,
            'total_inactive' => $total_inactive,
            'monthly_total' => $monthly_total,
            'yearly_total' => $yearly_total,
            'total_earned_all_time' => $total_earned_all_time,
            'average_per_client' => $average_per_client,
            'total_clients' => count($incomes)
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Incomes Error: ' . $e->getMessage());
    jsonResponse(false, null, 'خطا در بارگذاری لیست');
}
