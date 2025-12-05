<?php
// ═══════════════════════════════════════════════════════════════
// تنظیمات اتصال به دیتابیس برای وب‌اپ
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// اطلاعات دیتابیس (از فایل اصلی ربات)
require '../includes/config.php';
require '../includes/db.php';
require '../includes/jdf.php';

// تابع برای احراز هویت کاربر تلگرام
function validateTelegramUser($initData) {
    global $BOT_TOKEN;
    
    parse_str($initData, $data);
    
    if (!isset($data['hash'])) {
        return false;
    }
    
    $checkHash = $data['hash'];
    unset($data['hash']);
    
    $dataCheckArr = [];
    foreach ($data as $key => $value) {
        $dataCheckArr[] = $key . '=' . $value;
    }
    sort($dataCheckArr);
    
    $dataCheckString = implode("\n", $dataCheckArr);
    $secretKey = hash_hmac('sha256', $BOT_TOKEN, 'WebAppData', true);
    $hash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));
    
    if (strcmp($hash, $checkHash) === 0) {
        return json_decode($data['user'], true);
    }
    
    return false;
}

// تابع پاسخ JSON
function jsonResponse($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
