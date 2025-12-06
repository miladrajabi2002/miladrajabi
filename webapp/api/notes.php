<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
    // دریافت یادداشت‌ها
    $stmt = $pdo->prepare("
        SELECT 
            id,
            content,
            created_at
        FROM notes 
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $notes = $stmt->fetchAll();
    
    // فرمت کردن تاریخ
    foreach ($notes as &$note) {
        $note['created_at_fa'] = jdate('j F Y', strtotime($note['created_at']));
        $note['preview'] = mb_substr($note['content'], 0, 100) . (mb_strlen($note['content']) > 100 ? '...' : '');
    }
    
    jsonResponse(true, ['notes' => $notes]);
    
} catch (Exception $e) {
    jsonResponse(false, null, 'خطا: ' . $e->getMessage());
}
