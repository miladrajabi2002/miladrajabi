<?php
require_once '../config.php';

// دریافت پارامترها
$input = json_decode(file_get_contents('php://input'), true);
$income_id = $input['income_id'] ?? $_GET['income_id'] ?? $_POST['income_id'] ?? null;

// بررسی income_id
if (!$income_id) {
    jsonResponse(false, null, 'شناسه درآمد الزامی است');
    exit;
}

try {
    // دریافت اطلاعات کامل درامد
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
            notes,
            DATEDIFF(CURDATE(), start_date) as days_passed,
            TIMESTAMPDIFF(MONTH, start_date, CURDATE()) as months_passed
        FROM incomes 
        WHERE id = ?
    ");
    $stmt->execute([$income_id]);
    $income = $stmt->fetch();
    
    if (!$income) {
        jsonResponse(false, null, 'درآمد یافت نشد');
        exit;
    }
    
    // محاسبات
    $income['months'] = max(1, $income['months_passed']);
    $income['total_earned'] = $income['monthly_amount'] * $income['months'];
    $income['start_date_fa'] = jdate('j F Y', strtotime($income['start_date']));
    
    // روزهای باقی‌مانده تا پرداخت بعدی
    $days_until_payment = 0;
    if ($income['payment_day'] && $income['is_active']) {
        $today = (int)date('d');
        $payment_day = (int)$income['payment_day'];
        
        if ($payment_day >= $today) {
            $days_until_payment = $payment_day - $today;
        } else {
            $days_in_month = (int)date('t');
            $days_until_payment = ($days_in_month - $today) + $payment_day;
        }
    }
    
    // نمودار درآمد ماهانه (12 ماه اخیر)
    $monthly_chart = [];
    $income_start = strtotime($income['start_date']);
    
    for ($i = 11; $i >= 0; $i--) {
        $month_date = strtotime("-$i month");
        $month_name = jdate('F', $month_date);
        
        $amount = 0;
        if ($month_date >= $income_start) {
            $amount = $income['monthly_amount'];
        }
        
        $monthly_chart[] = [
            'month' => $month_name,
            'amount' => $amount
        ];
    }
    
    // آمار کلی
    $stats = [
        'total_earned' => $income['total_earned'],
        'months_active' => $income['months'],
        'average_monthly' => $income['monthly_amount'],
        'days_passed' => $income['days_passed'],
        'is_active' => $income['is_active'],
        'days_until_payment' => $days_until_payment
    ];
    
    jsonResponse(true, [
        'income' => $income,
        'stats' => $stats,
        'monthly_chart' => $monthly_chart
    ]);
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا: ' . $e->getMessage());
}