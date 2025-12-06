<?php
// ════════════════════════════════════════════════════════════════
// Goals API - Fixed for Real Database Schema (No is_active)
// ════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    // Get all goals - NO is_active column
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            description,
            target_date,
            progress,
            is_completed,
            created_at
        FROM goals 
        ORDER BY 
            is_completed ASC,
            target_date ASC,
            created_at DESC
    ");
    $stmt->execute();
    $goals = $stmt->fetchAll();
    
    // Process each goal
    $processed_goals = [];
    $now = time();
    
    foreach ($goals as $goal) {
        $target_date = $goal['target_date'] ? strtotime($goal['target_date']) : null;
        $created_at = strtotime($goal['created_at']);
        
        // Calculate status based on is_completed and target_date
        if ($goal['is_completed']) {
            $status = 'completed';
            $status_text = 'تکمیل شده';
        } elseif ($target_date && $target_date < $now) {
            $status = 'pending';
            $status_text = 'منقضی شده';
        } elseif ($goal['progress'] > 0) {
            $status = 'in-progress';
            $status_text = 'در حال اجرا';
        } else {
            $status = 'pending';
            $status_text = 'در انتظار';
        }
        
        // Calculate days remaining
        $days_remaining = null;
        if ($target_date && $target_date > $now && !$goal['is_completed']) {
            $days_remaining = ceil(($target_date - $now) / 86400);
        }
        
        $processed_goals[] = [
            'id' => $goal['id'],
            'title' => $goal['title'],
            'description' => $goal['description'],
            'target_date' => $goal['target_date'],
            'target_date_fa' => $target_date ? jdate('j F Y', $target_date) : null,
            'created_date_fa' => jdate('j F Y', $created_at),
            'progress' => (int)$goal['progress'],
            'is_completed' => (bool)$goal['is_completed'],
            'status' => $status,
            'status_text' => $status_text,
            'days_remaining' => $days_remaining
        ];
    }
    
    // Calculate statistics
    $stats = [
        'total' => count($processed_goals),
        'completed' => count(array_filter($processed_goals, fn($g) => $g['is_completed'])),
        'active' => count(array_filter($processed_goals, fn($g) => $g['status'] === 'in-progress')),
        'pending' => count(array_filter($processed_goals, fn($g) => $g['status'] === 'pending' && !$g['is_completed']))
    ];
    
    // Calculate overall progress
    if ($stats['total'] > 0) {
        $total_progress = array_sum(array_column($processed_goals, 'progress'));
        $stats['overall_progress'] = round($total_progress / $stats['total']);
    } else {
        $stats['overall_progress'] = 0;
    }
    
    jsonResponse(true, [
        'goals' => $processed_goals,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log('Goals Error: ' . $e->getMessage());
    jsonResponse(false, null, 'خطا در دریافت هدف‌ها: ' . $e->getMessage());
}
