<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    $today = date('Y-m-d');
    
    // دریافت یادآورهای امروز
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            description,
            reminder_time,
            is_active,
            TIME(reminder_time) as time_only
        FROM reminders 
        WHERE is_active = 1 
        AND DATE(reminder_time) = ?
        ORDER BY reminder_time ASC
    ");
    $stmt->execute([$today]);
    $reminders = $stmt->fetchAll();
    
    // فرمت کردن زمان
    foreach ($reminders as &$reminder) {
        $reminder['time_fa'] = jdate('H:i', strtotime($reminder['reminder_time']));
        $reminder['is_past'] = strtotime($reminder['reminder_time']) < time();
    }
    
    jsonResponse(true, ['reminders' => $reminders]);
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا: ' . $e->getMessage());
}
