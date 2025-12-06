<?php
// ════════════════════════════════════════════════════════════════
// Habits API
// ════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? 'list';

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT 
                h.id,
                h.name,
                h.is_active,
                COUNT(DISTINCT hl.completed_date) as total_completed,
                DATEDIFF(CURDATE(), h.created_at) + 1 as total_days,
                CASE 
                    WHEN EXISTS(SELECT 1 FROM habit_logs WHERE habit_id = h.id AND completed_date = CURDATE())
                    THEN 1 ELSE 0 
                END as is_completed_today
            FROM habits h
            LEFT JOIN habit_logs hl ON h.id = hl.habit_id
            WHERE h.is_active = 1
            GROUP BY h.id
            ORDER BY h.created_at DESC
        ");
        $stmt->execute();
        $habits = $stmt->fetchAll();
        
        foreach ($habits as &$habit) {
            $success_rate = $habit['total_days'] > 0 
                ? round(($habit['total_completed'] / $habit['total_days']) * 100) 
                : 0;
            
            $habit['success_rate'] = $success_rate;
            
            if ($success_rate >= 70) {
                $habit['status'] = 'عالی';
                $habit['status_color'] = 'green';
            } elseif ($success_rate >= 40) {
                $habit['status'] = 'خوب';
                $habit['status_color'] = 'orange';
            } else {
                $habit['status'] = 'نیاز به بهبود';
                $habit['status_color'] = 'grey';
            }
        }
        
        jsonResponse(true, ['habits' => $habits]);
        
    } elseif ($action === 'toggle') {
        $habit_id = $input['habit_id'] ?? null;
        
        if (!$habit_id || !is_numeric($habit_id)) {
            jsonResponse(false, null, 'شناسه عادت نامعتبر است');
        }
        
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("SELECT id FROM habit_logs WHERE habit_id = ? AND completed_date = ?");
        $stmt->execute([$habit_id, $today]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $pdo->prepare("DELETE FROM habit_logs WHERE habit_id = ? AND completed_date = ?");
            $stmt->execute([$habit_id, $today]);
            $message = 'عادت لغو شد';
        } else {
            $stmt = $pdo->prepare("INSERT INTO habit_logs (habit_id, completed_date) VALUES (?, ?)");
            $stmt->execute([$habit_id, $today]);
            $message = 'عادت ثبت شد 🎉';
        }
        
        jsonResponse(true, ['toggled' => !$existing], $message);
    }
    
} catch (Exception $e) {
    error_log('Habits Error: ' . $e->getMessage());
    jsonResponse(false, null, 'خطا در پردازش');
}
