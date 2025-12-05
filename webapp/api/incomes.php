<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    // دریافت لیست درآمدها
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
            DATEDIFF(CURDATE(), start_date) as days_passed
        FROM incomes 
        ORDER BY is_active DESC, monthly_amount DESC
    ");
    $stmt->execute();
    $incomes = $stmt->fetchAll();
    
    // محاسبه آمار
    $total_active = 0;
    $total_inactive = 0;
    $monthly_total = 0;
    
    foreach ($incomes as &$income) {
        if ($income['is_active']) {
            $total_active++;
            $monthly_total += $income['monthly_amount'];
        } else {
            $total_inactive++;
        }
        
        // محاسبه تعداد ماه‌ها
        $income['months'] = ceil($income['days_passed'] / 30);
        $income['total_earned'] = $income['monthly_amount'] * $income['months'];
        
        // فرمت تاریخ فارسی
        $income['start_date_fa'] = jdate('Y/m/d', strtotime($income['start_date']));
    }
    
    jsonResponse(true, [
        'incomes' => $incomes,
        'stats' => [
            'total_active' => $total_active,
            'total_inactive' => $total_inactive,
            'monthly_total' => $monthly_total
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا: ' . $e->getMessage());
}
