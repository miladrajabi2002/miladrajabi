<?php
// ════════════════════════════════════════════════════════════════
// Dashboard API - Fixed for Real Database Schema
// ════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    $stats = [];
    
    // Monthly income
    $stmt = $pdo->prepare("SELECT SUM(monthly_amount) as total FROM incomes WHERE is_active = 1");
    $stmt->execute();
    $stats['monthly_income'] = $stmt->fetchColumn() ?? 0;
    
    // Today's reminders
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reminders WHERE is_active = 1 AND DATE(reminder_time) = ?");
    $stmt->execute([$today]);
    $stats['today_reminders'] = $stmt->fetchColumn() ?? 0;
    
    // Completed habits today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE completed_date = ?");
    $stmt->execute([$today]);
    $stats['completed_habits'] = $stmt->fetchColumn() ?? 0;
    
    // Total active habits
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE is_active = 1");
    $stmt->execute();
    $stats['total_habits'] = $stmt->fetchColumn() ?? 0;
    
    // Total notes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE is_active = 1");
    $stmt->execute();
    $stats['total_notes'] = $stmt->fetchColumn() ?? 0;
    
    // Total documents (with fallback if table doesn't exist)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE is_active = 1");
        $stmt->execute();
        $stats['total_documents'] = $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        $stats['total_documents'] = 0;
    }
    
    // Goals stats - NO is_active column!
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE is_completed = 1");
    $stmt->execute();
    $stats['goals_completed'] = $stmt->fetchColumn() ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals");
    $stmt->execute();
    $stats['goals_total'] = $stmt->fetchColumn() ?? 0;
    
    // Income chart (last 6 months)
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
    
    // Habits chart (last 7 days)
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
    
    jsonResponse(true, [
        'stats' => $stats,
        'income_chart' => $income_chart,
        'habits_chart' => $habits_chart
    ]);
    
} catch (Exception $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    jsonResponse(false, null, 'خطا در دریافت اطلاعات: ' . $e->getMessage());
}
