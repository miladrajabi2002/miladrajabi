<?php
require_once '../config.php';

// دریافت user_id
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'];

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    // آمار کلی
    $stats = [];
    
    // درآمد ماه جاری
    $stmt = $pdo->prepare("SELECT SUM(monthly_amount) as total FROM incomes WHERE is_active = 1");
    $stmt->execute();
    $stats['monthly_income'] = $stmt->fetchColumn() ?? 0;
    
    // یادآورهای امروز
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reminders WHERE is_active = 1 AND DATE(reminder_time) = ?");
    $stmt->execute([$today]);
    $stats['today_reminders'] = $stmt->fetchColumn() ?? 0;
    
    // عادت‌های تکمیل شده امروز
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE completed_date = ?");
    $stmt->execute([$today]);
    $stats['completed_habits'] = $stmt->fetchColumn() ?? 0;
    
    // کل عادت‌های فعال
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE is_active = 1");
    $stmt->execute();
    $stats['total_habits'] = $stmt->fetchColumn() ?? 0;
    
    // یادداشت‌ها
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes");
    $stmt->execute();
    $stats['total_notes'] = $stmt->fetchColumn() ?? 0;
    
    // وظایف فعال
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE is_completed = 0");
    $stmt->execute();
    $stats['active_tasks'] = $stmt->fetchColumn() ?? 0;
    
    // نمودار درآمد 6 ماه اخیر
    $income_chart = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_date = date('Y-m-01', strtotime("-$i month"));
        $month_name = jdate('F', strtotime($month_date));
        
        $stmt = $pdo->prepare("SELECT SUM(monthly_amount) as total FROM incomes WHERE is_active = 1 AND start_date <= ?");
        $stmt->execute([$month_date]);
        $total = $stmt->fetchColumn() ?? 0;
        
        $income_chart[] = [
            'month' => $month_name,
            'amount' => $total
        ];
    }
    
    // نمودار عادت‌ها 7 روز اخیر
    $habits_chart = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $day_name = jdate('l', strtotime($date));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE completed_date = ?");
        $stmt->execute([$date]);
        $count = $stmt->fetchColumn() ?? 0;
        
        $habits_chart[] = [
            'day' => mb_substr($day_name, 0, 1),
            'count' => $count
        ];
    }
    
    // فعالیت‌های اخیر
    $recent_activities = [];
    
    // آخرین عادت انجام شده
    $stmt = $pdo->prepare("
        SELECT h.name, hl.completed_date 
        FROM habit_logs hl 
        JOIN habits h ON hl.habit_id = h.id 
        ORDER BY hl.completed_date DESC, hl.id DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $habit_logs = $stmt->fetchAll();
    
    foreach ($habit_logs as $log) {
        $recent_activities[] = [
            'icon' => 'check_circle',
            'color' => 'green',
            'title' => 'عادت ' . $log['name'] . ' انجام شد',
            'time' => jdate('j F', strtotime($log['completed_date']))
        ];
    }
    
    jsonResponse(true, [
        'stats' => $stats,
        'income_chart' => $income_chart,
        'habits_chart' => $habits_chart,
        'recent_activities' => array_slice($recent_activities, 0, 5)
    ]);
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا در دریافت اطلاعات: ' . $e->getMessage());
}
