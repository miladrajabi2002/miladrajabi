<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? 'list';

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    if ($action === 'toggle') {
        // تغییر وضعیت عادت
        $habit_id = $input['habit_id'] ?? $_GET['habit_id'] ?? $_POST['habit_id'] ?? null;
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
            
            // کاهش شمارنده در جدول habits
            $stmt = $pdo->prepare("UPDATE habits SET total_completed = total_completed - 1 WHERE id = ?");
            $stmt->execute([$habit_id]);
            
            jsonResponse(true, ['completed' => false], 'عادت لغو شد');
        } else {
            // اضافه کردن log
            $stmt = $pdo->prepare("INSERT INTO habit_logs (habit_id, completed_date) VALUES (?, ?)");
            $stmt->execute([$habit_id, $today]);
            
            // افزایش شمارنده در جدول habits
            $stmt = $pdo->prepare("UPDATE habits SET total_completed = total_completed + 1 WHERE id = ?");
            $stmt->execute([$habit_id]);
            
            jsonResponse(true, ['completed' => true], 'عادت ثبت شد');
        }
    } else {
        // دریافت لیست عادت‌ها
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT 
                h.id,
                h.name,
                h.total_completed,
                h.is_active,
                h.created_at,
                (SELECT COUNT(*) FROM habit_logs WHERE habit_id = h.id AND completed_date = ?) as is_completed_today
            FROM habits h
            WHERE h.is_active = 1
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([$today]);
        $habits = $stmt->fetchAll();
        
        // محاسبه آمار برای هر عادت
        foreach ($habits as &$habit) {
            $habit['is_completed_today'] = $habit['is_completed_today'] > 0;
            
            // محاسبه تعداد روزهای از زمان ایجاد
            $created_date = new DateTime($habit['created_at']);
            $current_date = new DateTime();
            $days_since_creation = $created_date->diff($current_date)->days + 1;
            
            // نرخ موفقیت = (تعداد انجام شده / کل روزها) * 100
            $habit['total_days'] = $days_since_creation;
            $habit['success_rate'] = $days_since_creation > 0 
                ? round(($habit['total_completed'] / $days_since_creation) * 100, 1) 
                : 0;
            
            // وضعیت فعلی
            if ($habit['success_rate'] >= 80) {
                $habit['status'] = 'عالی';
                $habit['status_color'] = 'green';
            } elseif ($habit['success_rate'] >= 50) {
                $habit['status'] = 'خوب';
                $habit['status_color'] = 'blue';
            } elseif ($habit['success_rate'] >= 30) {
                $habit['status'] = 'متوسط';
                $habit['status_color'] = 'orange';
            } else {
                $habit['status'] = 'نیاز به تلاش';
                $habit['status_color'] = 'red';
            }
        }
        
        jsonResponse(true, ['habits' => $habits]);
    }
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا: ' . $e->getMessage());
}
