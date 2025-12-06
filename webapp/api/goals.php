<?php
// ════════════════════════════════════════════════════════════════
// Goals API - Complete CRUD Operations
// ════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    // Get all goals with progress calculation
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            description,
            start_date,
            end_date,
            progress,
            status,
            created_at
        FROM goals 
        WHERE is_active = 1
        ORDER BY 
            CASE status
                WHEN 'in-progress' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'completed' THEN 3
                ELSE 4
            END,
            created_at DESC
    ");
    $stmt->execute();
    $goals = $stmt->fetchAll();
    
    // Process each goal
    $processed_goals = [];
    foreach ($goals as $goal) {
        $start_date = strtotime($goal['start_date']);
        $end_date = $goal['end_date'] ? strtotime($goal['end_date']) : null;
        $now = time();
        
        // Calculate days remaining
        $days_remaining = null;
        if ($end_date && $end_date > $now) {
            $days_remaining = ceil(($end_date - $now) / 86400);
        }
        
        // Auto-update status based on dates and progress
        $status = $goal['status'];
        if ($goal['progress'] >= 100) {
            $status = 'completed';
        } elseif ($end_date && $end_date < $now && $status !== 'completed') {
            $status = 'pending';
        } elseif ($start_date <= $now && $status === 'pending') {
            $status = 'in-progress';
        }
        
        $processed_goals[] = [
            'id' => $goal['id'],
            'title' => $goal['title'],
            'description' => $goal['description'],
            'start_date' => $goal['start_date'],
            'end_date' => $goal['end_date'],
            'start_date_fa' => jdate('j F Y', $start_date),
            'end_date_fa' => $end_date ? jdate('j F Y', $end_date) : null,
            'progress' => (int)$goal['progress'],
            'status' => $status,
            'days_remaining' => $days_remaining
        ];
    }
    
    // Calculate statistics
    $stats = [
        'total' => count($processed_goals),
        'completed' => count(array_filter($processed_goals, fn($g) => $g['status'] === 'completed')),
        'active' => count(array_filter($processed_goals, fn($g) => $g['status'] === 'in-progress')),
        'pending' => count(array_filter($processed_goals, fn($g) => $g['status'] === 'pending'))
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
    jsonResponse(false, null, 'خطا در دریافت هدف‌ها');
}
