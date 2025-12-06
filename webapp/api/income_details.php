<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$income_id = $input['income_id'] ?? $_GET['income_id'] ?? $_POST['income_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

if (!$income_id) {
    jsonResponse(false, null, 'شناسه درآمد الزامی است');
}

try {
    // دریافت اطلاعات کامل درآمد
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
    }
    
    // محاسبات
    $income['months'] = $income['months_passed'] > 0 ? $income['months_passed'] : 1;
    $income['total_earned'] = $income['monthly_amount'] * $income['months'];
    $income['start_date_fa'] = jdate('j F Y', strtotime($income['start_date']));
    
    // روزهای باقی‌مانده تا پرداخت بعدی
    if ($income['payment_day']) {
        $today = (int)date('d');
        $payment_day = (int)$income['payment_day'];
        
        if ($payment_day >= $today) {
            $income['days_until_payment'] = $payment_day - $today;
        } else {
            $days_in_month = (int)date('t');
            $income['days_until_payment'] = ($days_in_month - $today) + $payment_day;
        }
    } else {
        $income['days_until_payment'] = 0;
    }
    
    // نمودار درآمد ماهانه (12 ماه اخیر)
    $monthly_chart = [];
    for ($i = 11; $i >= 0; $i--) {
        $month_date = date('Y-m-01', strtotime("-$i month"));
        $month_name = jdate('F', strtotime($month_date));
        $month_year = jdate('Y', strtotime($month_date));
        
        // اگر درآمد در این ماه فعال بوده
        $income_start = strtotime($income['start_date']);
        $check_date = strtotime($month_date);
        
        $amount = 0;
        if ($check_date >= $income_start && $income['is_active']) {
            $amount = $income['monthly_amount'];
        } elseif ($check_date >= $income_start && !$income['is_active']) {
            // بررسی آیا در این ماه هنوز فعال بوده یا نه
            $amount = $income['monthly_amount'];
        }
        
        $monthly_chart[] = [
            'month' => $month_name,
            'year' => $month_year,
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
        'days_until_payment' => $income['days_until_payment']
    ];
    
    jsonResponse(true, [
        'income' => $income,
        'stats' => $stats,
        'monthly_chart' => $monthly_chart
    ]);
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا: ' . $e->getMessage());
}
