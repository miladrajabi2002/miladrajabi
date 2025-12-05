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

// توابع دیتابیس کاربران
function getUser($user_id)
{
   global $pdo;
   $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
   $stmt->execute([$user_id]);
   return $stmt->fetch();
}

function insertUser($user_id, $username, $first_name, $last_name, $step = 'completed')
{
   global $pdo;
   $stmt = $pdo->prepare("INSERT IGNORE INTO users (user_id, username, first_name, last_name, step, registration_date) VALUES (?, ?, ?, ?, ?, NOW())");
   return $stmt->execute([$user_id, $username, $first_name, $last_name, $step]);
}

function updateUser($user_id, $data)
{
   global $pdo;
   $fields = [];
   $values = [];

   foreach ($data as $key => $value) {
      $fields[] = "$key = ?";
      $values[] = $value;
   }

   $values[] = $user_id;
   $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";
   $stmt = $pdo->prepare($sql);
   return $stmt->execute($values);
}

function getUserCount()
{
   global $pdo;
   return $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

function getPremiumUserCount()
{
   global $pdo;
   return $pdo->query("SELECT COUNT(*) FROM users WHERE is_premium = 1")->fetchColumn();
}

function getTodayActiveUsers()
{
   global $pdo;
   return $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(last_activity) = CURDATE()")->fetchColumn();
}

function getAllUsers($premium_only = false)
{
   global $pdo;
   $where = $premium_only ? "WHERE is_premium = 1" : "";
   return $pdo->query("SELECT user_id FROM users $where")->fetchAll(PDO::FETCH_COLUMN);
}
