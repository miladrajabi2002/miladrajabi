<?php
// ════════════════════════════════════════════════════════════════
// Notes API
// ════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    jsonResponse(false, null, 'کاربر یافت نشد');
}

try {
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
    
    foreach ($notes as &$note) {
        $note['preview'] = mb_substr($note['content'], 0, 100) . (mb_strlen($note['content']) > 100 ? '...' : '');
        $note['created_at_fa'] = jdate('j F Y - H:i', strtotime($note['created_at']));
    }
    
    jsonResponse(true, ['notes' => $notes]);
    
} catch (Exception $e) {
    error_log('Notes Error: ' . $e->getMessage());
    jsonResponse(false, null, 'خطا در بارگذاری یادداشت‌ها');
}
