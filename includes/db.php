<?php
// اتصال به دیتابیس
try {
   $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
   $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
   error_log("Database connection failed: " . $e->getMessage());
   exit("Database connection failed");
}

function getUser($user_id = 1253939828)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([1253939828]);
    return $stmt->fetch();
}

function updateUser($user_id = 1253939828, $data)
{
    global $pdo;
    $fields = [];
    $values = [];
    foreach ($data as $key => $value) {
        $fields[] = "$key = ?";
        $values[] = $value;
    }
    $values[] = 1253939828;
    $sql = "UPDATE user_settings SET " . implode(', ', $fields) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}
