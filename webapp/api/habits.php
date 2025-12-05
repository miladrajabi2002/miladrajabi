<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$action = $input['action'] ?? 'list';

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    if ($action === 'toggle') {
        // تغییر وضعیت عادت
        $habit_id = $input['habit_id'] ?? null;
        $today = date('Y-m-d');
        
        if (!$habit_id) {
            jsonResponse(false, null, 'شناسه عادت الزامی است');
        }
        
        // چک کردن وجود log
        $stmt = $pdo->prepare("SELECT id FROM habit_logs WHERE habit_id = ? AND completed_date = ?");
        $stmt->execute([$habit_id, $today]);
        $log = $stmt->fetch();
        
        if ($log) {
            // حذف log
            $stmt = $pdo->prepare("DELETE FROM habit_logs WHERE id = ?");
            $stmt->execute([$log['id']]);
            jsonResponse(true, ['completed' => false], 'عادت لغو شد');
        } else {
            // اضافه کردن log
            $stmt = $pdo->prepare("INSERT INTO habit_logs (habit_id, completed_date) VALUES (?, ?)");
            $stmt->execute([$habit_id, $today]);
            jsonResponse(true, ['completed' => true], 'عادت ثبت شد');
        }
    } else {
        // دریافت لیست عادت‌ها
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT 
                h.id,
                h.name,
                h.icon,
                h.target_days,
                h.is_active,
                (SELECT COUNT(*) FROM habit_logs WHERE habit_id = h.id) as total_completed,
                (SELECT COUNT(*) FROM habit_logs WHERE habit_id = h.id AND completed_date = ?) as is_completed_today
            FROM habits h
            WHERE h.is_active = 1
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([$today]);
        $habits = $stmt->fetchAll();
        
        // محاسبه درصد پیشرفت
        foreach ($habits as &$habit) {
            $habit['is_completed_today'] = $habit['is_completed_today'] > 0;
            $habit['progress'] = $habit['target_days'] > 0 ? round(($habit['total_completed'] / $habit['target_days']) * 100) : 0;
        }
        
        jsonResponse(true, ['habits' => $habits]);
    }
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا: ' . $e->getMessage());
}
