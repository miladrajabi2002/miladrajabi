<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/jdf.php';


try {
   checkAndRunTask('reminders', 'processReminders');
   checkAndRunTask('financial_due_reminders', 'processFinancialDueReminders');
   checkAndRunTask('financial_overdue_checks', 'processOverdueChecks');
   checkAndRunTask('document_expiry', 'processDocumentExpiry');
   checkAndRunTask('document_expired_daily', 'processExpiredDocumentsDaily');
   checkAndRunTask('motivational_messages', 'sendMotivationalMessages');
   checkAndRunTask('habit_evening_reminder', 'processHabitEveningReminders');
   // checkAndRunTask('habit_reset_daily', 'resetDailyHabits');
   checkAndRunTask('birthday_reminders', 'processBirthdayReminders');
   checkAndRunTask('birthday_upcoming', 'processUpcomingBirthdays');
   checkAndRunTask('contact_reminders', 'processContactReminders');
} catch (Exception $th) {
   sendMessage($ADMINS[0], "BUG CRON\n\n" . $th->getMessage());
}

define('SINGLE_USER_ID', 1253939828);


/**
 * بررسی و اجرای تسک در صورت رسیدن زمان
 */
function checkAndRunTask($taskName, $functionName)
{
   global $pdo;

   try {
      // دریافت اطلاعات تسک
      $stmt = $pdo->prepare("SELECT * FROM cron_control WHERE task_name = ? AND is_active = 1");
      $stmt->execute([$taskName]);
      $task = $stmt->fetch();

      if (!$task) {
         return false;
      }

      // بررسی زمان آخرین اجرا
      $now = new DateTime();
      $lastRun = $task['last_run'] ? new DateTime($task['last_run']) : null;

      // محاسبه تفاوت زمان به دقیقه
      if ($lastRun) {
         $diffMinutes = ($now->getTimestamp() - $lastRun->getTimestamp()) / 60;
         if ($diffMinutes < $task['frequency_minutes']) {
            return false; // هنوز زمان اجرا نرسیده
         }
      }

      // اجرای تابع
      if (function_exists($functionName)) {
         error_log("Running task: $taskName");
         $functionName();

         // بروزرسانی زمان آخرین اجرا
         $stmt = $pdo->prepare("UPDATE cron_control SET last_run = NOW() WHERE task_name = ?");
         $stmt->execute([$taskName]);

         return true;
      } else {
         return false;
      }
   } catch (Exception $e) {
      error_log("Error in task $taskName: " . $e->getMessage());
      return false;
   }
}


/**
 * پردازش یادآورهای عادی
 */
function processReminders()
{
   global $pdo;
   $stmt = $pdo->prepare("
        SELECT r.*
        FROM reminders r
        WHERE r.is_active = 1
        AND r.reminder_time <= NOW()
        AND r.reminder_time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
   $stmt->execute();
   $reminders = $stmt->fetchAll();

   foreach ($reminders as $reminder) {
      $text = "🔔 یادآوری\n\n";
      $text .= "📝 " . htmlspecialchars($reminder['title']) . "\n\n";
      $text .= "⏰ زمان: " . jdate('Y/m/d H:i', strtotime($reminder['reminder_time']));

      if ($reminder['description']) {
         $text .= "\n\n📋 " . htmlspecialchars($reminder['description']);
      }

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '⏰ یک ساعت دیگر', 'callback_data' => 'reminder_snooze_' . $reminder['id']]
            ]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);

      if ($reminder['repeat_type'] == 'once') {
         // غیرفعال کردن یادآور یکبار
         $stmt = $pdo->prepare("UPDATE reminders SET is_active = 0 WHERE id = ?");
         $stmt->execute([$reminder['id']]);
      } else {
         // محاسبه زمان بعدی
         $next_time = calculateNextReminderTime($reminder['reminder_time'], $reminder['repeat_type']);
         $stmt = $pdo->prepare("UPDATE reminders SET reminder_time = ? WHERE id = ?");
         $stmt->execute([$next_time, $reminder['id']]);
      }

      usleep(100000); // 0.1 ثانیه تاخیر
   }
}



/**
 * پردازش یادآوریهای سررسید مالی (روزانه)
 */
function processFinancialDueReminders()
{
   global $pdo;

   // فقط در ساعت 9 صبح اجرا شود
   if ((int)date('H') !== 9) {
      return;
   }

   // بدهیها و طلبهای 3 روز مانده
   $stmt = $pdo->prepare("
        SELECT dc.*
        FROM finances dc
        WHERE dc.is_paid = 0
        AND dc.due_date IS NOT NULL
        AND DATE(dc.due_date) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ");
   $stmt->execute();
   $finances = $stmt->fetchAll();

   foreach ($finances as $item) {
      $type_title = $item['type'] === 'debt' ? 'بدهی' : 'طلب';
      $type_icon = $item['type'] === 'debt' ? '🔴' : '🟢';
      $amount = number_format($item['amount']);
      $due_date = jdate('Y/m/d', strtotime($item['due_date']));

      $text = "⚠️ یادآوری سررسید\n\n";
      $text .= "$type_icon نوع: $type_title\n";
      $text .= "📋 عنوان: " . htmlspecialchars($item['title']) . "\n";
      $text .= "👤 طرف حساب: " . htmlspecialchars($item['person_name']) . "\n";
      $text .= "💰 مبلغ: $amount تومان\n";
      $text .= "📅 سررسید: $due_date (3 روز دیگر)\n\n";
      $text .= "💡 لطفاً برای تسویه اقدام کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '✅ تسویه شد', 'callback_data' => "finance_pay_{$item['type']}_{$item['id']}"],
               ['text' => '👁 مشاهده جزئیات', 'callback_data' => "finance_view_{$item['type']}_{$item['id']}"]
            ]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);
      usleep(100000);
   }

   // چکهای فردا سررسید
   $stmt = $pdo->prepare("
        SELECT c.*
        FROM checks c
        WHERE c.status = 'pending'
        AND DATE(c.due_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ");
   $stmt->execute();
   $checks = $stmt->fetchAll();

   foreach ($checks as $check) {
      $type_title = $check['type'] === 'received' ? 'دریافتی' : 'صادره';
      $type_icon = $check['type'] === 'received' ? '📥' : '📤';
      $amount = number_format($check['amount']);
      $due_date = jdate('Y/m/d', strtotime($check['due_date']));

      $text = "🚨 هشدار سررسید چک\n\n";
      $text .= "$type_icon نوع: چک $type_title\n";
      $text .= "👤 صاحب حساب: " . htmlspecialchars($check['account_holder']) . "\n";
      $text .= "💰 مبلغ: $amount تومان\n";

      if ($check['check_number']) {
         $text .= "🔢 شماره چک: " . htmlspecialchars($check['check_number']) . "\n";
      }

      if ($check['bank_name']) {
         $text .= "🏦 بانک: " . htmlspecialchars($check['bank_name']) . "\n";
      }

      $text .= "📅 سررسید: $due_date (فردا!)\n\n";

      if ($check['type'] === 'received') {
         $text .= "💡 فردا باید این چک را به بانک ببرید.";
      } else {
         $text .= "💡 مطمئن شوید حساب شما موجودی کافی دارد.";
      }

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '👁 مشاهده جزئیات', 'callback_data' => "finance_view_check_{$check['type']}_{$check['id']}"]]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);
      usleep(100000);
   }
}


/**
 * پردازش چکهای سررسید گذشته (روزانه)
 */
function processOverdueChecks()
{
   global $pdo;

   // فقط در ساعت 10 صبح اجرا شود
   if ((int)date('H') !== 10) {
      return;
   }

   $today = date('Y-m-d');
   $stmt = $pdo->prepare("
        SELECT c.*
        FROM checks c
        WHERE c.status = 'pending'
        AND c.due_date < CURDATE()
        AND (c.last_overdue_reminder IS NULL OR c.last_overdue_reminder < ?)
        ORDER BY c.due_date
    ");
   $stmt->execute([$today]);
   $overdue_checks = $stmt->fetchAll();

   if (count($overdue_checks) > 0) {
      $count = count($overdue_checks);
      $total_amount = 0;
      $details = "";
      $check_ids = [];

      foreach ($overdue_checks as $check) {
         $check_ids[] = $check['id'];
         $total_amount += $check['amount'];
         $type_icon = $check['type'] === 'received' ? '📥' : '📤';
         $type_title = $check['type'] === 'received' ? 'دریافتی' : 'صادره';
         $amount = number_format($check['amount']);
         $days_overdue = ceil((time() - strtotime($check['due_date'])) / (24 * 3600));

         $details .= "$type_icon چک $type_title - " . htmlspecialchars($check['account_holder']) . "\n";
         $details .= "  💰 $amount تومان - ⏰ $days_overdue روز تاخیر\n\n";
      }

      $total_formatted = number_format($total_amount);
      $text = "❌ یادآوری روزانه چکهای سررسید گذشته\n\n";
      $text .= "سلام،\n";
      $text .= "شما $count چک سررسید گذشته به مبلغ کل $total_formatted تومان دارید:\n\n";
      $text .= $details;
      $text .= "🔄 لطفاً هرچه سریعتر نسبت به پیگیری و بروزرسانی وضعیت چکها اقدام کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '📋 مشاهده لیست چکها', 'callback_data' => 'finance_checks']],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);

      // آپدیت کردن تاریخ آخرین یادآوری
      if (!empty($check_ids)) {
         $placeholders = implode(',', array_fill(0, count($check_ids), '?'));
         $stmt = $pdo->prepare("UPDATE checks SET last_overdue_reminder = ? WHERE id IN ($placeholders)");
         $params = array_merge([$today], $check_ids);
         $stmt->execute($params);
      }

      usleep(100000);
   }
}


/**
 * پردازش انقضای اسناد امروز
 */
function processDocumentExpiry()
{
   global $pdo;

   // فقط در ساعت 8 صبح اجرا شود
   if ((int)date('H') !== 8) {
      return;
   }

   // اسناد منقضی شده امروز
   $stmt = $pdo->prepare("
        SELECT d.*
        FROM documents d
        WHERE d.expire_date = CURDATE()
        AND (d.last_reminder_sent IS NULL OR d.last_reminder_sent < CURDATE())
    ");
   $stmt->execute();
   $expiring_today = $stmt->fetchAll();

   foreach ($expiring_today as $doc) {
      $text = "🚨 هشدار انقضای سند\n\n";
      $text .= "📄 سند «" . htmlspecialchars($doc['name']) . "» امروز منقضی میشود!\n\n";
      $text .= "📅 تاریخ انقضا: " . jdate('Y/m/d', strtotime($doc['expire_date'])) . "\n\n";
      $text .= "⚠️ لطفاً هرچه سریعتر برای تمدید اقدام کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '👁 مشاهده سند', 'callback_data' => 'doc_view_' . $doc['id']]],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);

      // بروزرسانی تاریخ ارسال
      $pdo->prepare("UPDATE documents SET last_reminder_sent = CURDATE() WHERE id = ?")
         ->execute([$doc['id']]);

      usleep(100000);
   }

   // اسناد نزدیک انقضا (7 روز مانده)
   $stmt = $pdo->prepare("
        SELECT d.*
        FROM documents d
        WHERE d.expire_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
   $stmt->execute();
   $expiring_soon = $stmt->fetchAll();

   foreach ($expiring_soon as $doc) {
      $text = "⚠️ یادآوری انقضای سند\n\n";
      $text .= "📄 سند «" . htmlspecialchars($doc['name']) . "» تا 7 روز دیگر منقضی میشود.\n\n";
      $text .= "📅 تاریخ انقضا: " . jdate('Y/m/d', strtotime($doc['expire_date'])) . "\n\n";
      $text .= "💡 برای تمدید به موقع اقدام کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '👁 مشاهده سند', 'callback_data' => 'doc_view_' . $doc['id']]],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);
      usleep(100000);
   }
}


/**
 * گزارش روزانه اسناد منقضی شده
 */
function processExpiredDocumentsDaily()
{
   global $pdo;

   // فقط در ساعت 11 صبح اجرا شود
   if ((int)date('H') !== 11) {
      return;
   }

   $stmt = $pdo->prepare("
        SELECT d.*,
        DATEDIFF(CURDATE(), d.expire_date) as days_expired
        FROM documents d
        WHERE d.expire_date < CURDATE()
        AND (d.last_reminder_sent IS NULL OR d.last_reminder_sent < CURDATE())
        ORDER BY d.expire_date
    ");
   $stmt->execute();
   $expired_docs = $stmt->fetchAll();

   if (count($expired_docs) > 0) {
      if (count($expired_docs) > 5) {
         // خلاصه برای تعداد زیاد
         $text = "🔴 گزارش اسناد منقضی شده\n\n";
         $text .= "شما " . count($expired_docs) . " سند منقضی شده دارید:\n\n";

         $critical = 0;
         foreach ($expired_docs as $doc) {
            if ($doc['days_expired'] > 30) {
               $critical++;
            }
         }

         if ($critical > 0) {
            $text .= "⚠️ $critical سند بیش از 30 روز از انقضا گذشته!\n";
         }

         $text .= "\nبرای مشاهده جزئیات:";

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '📋 مشاهده لیست کامل', 'callback_data' => 'doc_list_1']]
            ]
         ];
      } else {
         // نمایش جزئیات
         $text = "🔴 اسناد منقضی شده\n\n";
         foreach ($expired_docs as $doc) {
            $text .= "📄 " . htmlspecialchars($doc['name']);
            if ($doc['type']) {
               $text .= " ({$doc['type']})";
            }
            $text .= "\n⏰ " . $doc['days_expired'] . " روز از انقضا گذشته\n\n";
         }

         $text .= "⚠️ لطفاً برای تمدید اقدام کنید.";

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '📑 مدیریت اسناد', 'callback_data' => 'documents']],
               [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
            ]
         ];
      }

      sendMessage(SINGLE_USER_ID, $text, $keyboard);

      // بروزرسانی تاریخ ارسال
      $doc_ids = array_column($expired_docs, 'id');
      $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
      $stmt = $pdo->prepare("UPDATE documents SET last_reminder_sent = CURDATE() WHERE id IN ($placeholders)");
      $stmt->execute($doc_ids);

      usleep(100000);
   }
}


/**
 * ارسال پیامهای انگیزشی
 */
function sendMotivationalMessages()
{
   global $pdo;

   // فقط در ساعت 8 صبح اجرا شود
   if ((int)date('H') !== 8) {
      return;
   }

   $message = getMotivationalMessage();
   sendMessage(SINGLE_USER_ID, "🌟 " . $message);
   usleep(100000);
}


/**
 * یادآوری عادتها در عصر
 */
function processHabitEveningReminders()
{
   global $pdo;

   // فقط بین ساعت 18 تا 20 اجرا شود
   $current_hour = (int)date('H');
   if ($current_hour < 18 || $current_hour > 20) {
      return;
   }

   $stmt = $pdo->prepare("
        SELECT COUNT(h.id) as pending_habits,
        GROUP_CONCAT(h.name SEPARATOR ', ') as habit_names
        FROM habits h
        LEFT JOIN habit_logs hl ON h.id = hl.habit_id AND hl.completed_date = CURDATE()
        WHERE h.is_active = 1
        AND hl.id IS NULL
    ");
   $stmt->execute();
   $result = $stmt->fetch();

   if ($result && $result['pending_habits'] > 0) {
      $pending_count = $result['pending_habits'];
      $habit_names = $result['habit_names'];

      $text = "⏰ یادآوری عادتها\n\n";
      $text .= "سلام!\n";
      $text .= "شما $pending_count عادت انجام نشده دارید:\n\n";

      // نمایش حداکثر 3 عادت اول
      $habits_array = explode(', ', $habit_names);
      $display_habits = array_slice($habits_array, 0, 3);

      foreach ($display_habits as $habit_name) {
         $text .= "• " . htmlspecialchars($habit_name) . "\n";
      }

      if (count($habits_array) > 3) {
         $text .= "... و " . (count($habits_array) - 3) . " عادت دیگر\n";
      }

      $text .= "\n💪 هنوز وقت دارید! امروز را از دست ندهید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '✅ تیک زدن عادتها', 'callback_data' => 'habit_today']],
            [['text' => '📊 مشاهده آمار', 'callback_data' => 'habit_stats']],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);
      usleep(100000);
   }
}


/**
 * یادآوری عادتهای باقیمانده - ساعت 23:00
 */
function sendHabitReminders()
{
   global $pdo;

   $current_hour = (int)date('H');
   $current_minute = (int)date('i');

   // فقط ساعت 23:00 اجرا شود
   if ($current_hour !== 23 || $current_minute > 5) {
      return;
   }

   try {
      // پیدا کردن عادتهای انجام نشده
      $stmt = $pdo->prepare("
            SELECT COUNT(h.id) as pending_habits,
            GROUP_CONCAT(h.name SEPARATOR '، ') as habit_names
            FROM habits h
            LEFT JOIN habit_logs hl ON h.id = hl.habit_id AND hl.completed_date = CURDATE()
            WHERE h.is_active = 1
            AND hl.id IS NULL
        ");
      $stmt->execute();
      $result = $stmt->fetch();

      if ($result && $result['pending_habits'] > 0) {
         $pending_count = $result['pending_habits'];
         $habit_names = $result['habit_names'];

         // محدود کردن نام عادتها
         if (mb_strlen($habit_names) > 100) {
            $habit_names = mb_substr($habit_names, 0, 100) . '...';
         }

         $text = "⏰ یادآوری عادتهای امروز\n\n";
         $text .= "سلام!\n\n";
         $text .= "🕚 ساعت 23:00 است و هنوز $pending_count عادت باقی مانده:\n\n";
         $text .= "📋 عادتهای انجام نشده:\n";
         $text .= "• $habit_names\n\n";
         $text .= "⏰ فقط 1 ساعت تا نیمهشب باقی مانده!\n";
         $text .= "💪 هنوز وقت دارید تا عادتهایتان را انجام دهید.";

         $keyboard = [
            'inline_keyboard' => [
               [
                  ['text' => '✅ تیک زدن عادتها', 'callback_data' => 'habit_today']
               ],
               [
                  ['text' => '📊 مشاهده آمار', 'callback_data' => 'habit_stats']
               ]
            ]
         ];

         sendMessage(SINGLE_USER_ID, $text, $keyboard);
         error_log("Habit reminder sent at " . date('Y-m-d H:i:s'));
      }
   } catch (Exception $e) {
      error_log("Error in sendHabitReminders: " . $e->getMessage());
   }
}


/**
 * ریست روزانه عادتها - ساعت 00:05
 */
function resetDailyHabits()
{
   global $pdo;

   $current_hour = (int)date('H');
   $current_minute = (int)date('i');

   // فقط ساعت 00:05 اجرا شود
   if ($current_hour !== 0 || $current_minute < 5 || $current_minute > 10) {
      return;
   }

   try {
      // پیدا کردن عادتهای انجام نشده دیروز
      $stmt = $pdo->prepare("
            SELECT COUNT(h.id) as missed_habits,
            GROUP_CONCAT(h.name SEPARATOR '، ') as missed_names
            FROM habits h
            LEFT JOIN habit_logs hl ON h.id = hl.habit_id AND hl.completed_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            WHERE h.is_active = 1
            AND hl.id IS NULL
        ");
      $stmt->execute();
      $result = $stmt->fetch();

      if ($result && $result['missed_habits'] >= 2) {
         $missed_count = $result['missed_habits'];
         $missed_names = $result['missed_names'];

         // محدود کردن نام عادتها
         if (mb_strlen($missed_names) > 80) {
            $missed_names = mb_substr($missed_names, 0, 80) . '...';
         }

         $text = "🌅 صبح بخیر و شروع روز جدید!\n\n";
         $text .= "سلام!\n\n";
         $text .= "📅 امروز " . jdate('l j F Y') . " است.\n\n";
         $text .= "⚠️ دیروز $missed_count عادت انجام نشد:\n";
         $text .= "• $missed_names\n\n";
         $text .= "💪 امروز روز جدیدی است!\n";
         $text .= "🎯 بیایید با انگیزه بیشتری شروع کنیم.";

         $keyboard = [
            'inline_keyboard' => [
               [
                  ['text' => '✅ عادتهای امروز', 'callback_data' => 'habit_today']
               ],
               [
                  ['text' => '📊 آمار عملکرد', 'callback_data' => 'habit_stats']
               ]
            ]
         ];

         sendMessage(SINGLE_USER_ID, $text, $keyboard);
      }
   } catch (Exception $e) {
      error_log("Error in resetDailyHabits: " . $e->getMessage());
   }
}


/**
 * یادآوری تولدها
 */
function processBirthdayReminders()
{
   global $pdo;

   // فقط در ساعت 9 صبح اجرا شود
   if ((int)date('H') !== 9) {
      return;
   }

   $stmt = $pdo->prepare("
        SELECT c.*
        FROM contacts c
        WHERE c.birthday IS NOT NULL
        AND DATE_FORMAT(c.birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
        AND (c.last_birthday_reminder IS NULL OR c.last_birthday_reminder != CURDATE())
    ");
   $stmt->execute();
   $birthdays = $stmt->fetchAll();

   foreach ($birthdays as $contact) {
      $age = calculateAge($contact['birthday']) + 1;

      $text = "🎂 یادآوری تولد\n\n";
      $text .= "امروز تولد " . htmlspecialchars($contact['name']) . " است!\n";
      $text .= "🎉 $age ساله شد\n\n";

      if ($contact['phone']) {
         $text .= "📱 تلفن: " . htmlspecialchars($contact['phone']) . "\n";
      }

      if ($contact['relationship']) {
         $text .= "👥 نسبت: " . htmlspecialchars($contact['relationship']) . "\n";
      }

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '👁 مشاهده جزئیات', 'callback_data' => 'social_view_' . $contact['id']]],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);

      // ثبت ارسال یادآوری
      $update_stmt = $pdo->prepare("UPDATE contacts SET last_birthday_reminder = CURDATE() WHERE id = ?");
      $update_stmt->execute([$contact['id']]);

      usleep(100000);
   }
}


/**
 * تولدهای نزدیک
 */
function processUpcomingBirthdays()
{
   global $pdo;

   // فقط در ساعت 10 صبح اجرا شود
   if ((int)date('H') !== 10) {
      return;
   }

   $stmt = $pdo->prepare("
        SELECT c.*
        FROM contacts c
        WHERE c.birthday IS NOT NULL
        AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '%m-%d') = DATE_FORMAT(c.birthday, '%m-%d')
    ");
   $stmt->execute();
   $upcoming_birthdays = $stmt->fetchAll();

   foreach ($upcoming_birthdays as $contact) {
      $text = "🎁 یادآوری تولد\n\n";
      $text .= "3 روز دیگر تولد " . htmlspecialchars($contact['name']) . " است!\n\n";

      if ($contact['relationship']) {
         $text .= "👥 نسبت: " . htmlspecialchars($contact['relationship']) . "\n";
      }

      $text .= "\n💡 فراموش نکنید برای تبریک آماده شوید!";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '👁 مشاهده جزئیات', 'callback_data' => 'social_view_' . $contact['id']]],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);
      usleep(100000);
   }
}


/**
 * یادآوری تماسها
 */
function processContactReminders()
{
   global $pdo;

   // فقط در ساعت 11 صبح اجرا شود
   if ((int)date('H') !== 11) {
      return;
   }

   $stmt = $pdo->prepare("
        SELECT c.*
        FROM contacts c
        WHERE c.contact_frequency > 0
        AND c.last_contact_date IS NOT NULL
        AND DATE_ADD(c.last_contact_date, INTERVAL c.contact_frequency DAY) <= CURDATE()
    ");
   $stmt->execute();
   $contact_reminders = $stmt->fetchAll();

   foreach ($contact_reminders as $contact) {
      $days_overdue = ceil((time() - strtotime($contact['last_contact_date'])) / (24 * 3600)) - $contact['contact_frequency'];

      $text = "📞 یادآوری تماس\n\n";
      $text .= "زمان تماس با " . htmlspecialchars($contact['name']) . " فرا رسیده!\n\n";

      if ($contact['phone']) {
         $text .= "📱 تلفن: " . htmlspecialchars($contact['phone']) . "\n";
      }

      if ($contact['relationship']) {
         $text .= "👥 نسبت: " . htmlspecialchars($contact['relationship']) . "\n";
      }

      $last_contact = jdate('Y/m/d', strtotime($contact['last_contact_date']));
      $text .= "\n📅 آخرین تماس: $last_contact";

      if ($days_overdue > 0) {
         $text .= "\n⚠️ $days_overdue روز از موعد گذشته!";
      }

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '✅ تماس گرفتم', 'callback_data' => 'social_contacted_' . $contact['id']],
               ['text' => '👁 مشاهده جزئیات', 'callback_data' => 'social_view_' . $contact['id']]
            ],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage(SINGLE_USER_ID, $text, $keyboard);
      usleep(100000);
   }
}

/**
 * محاسبه زمان یادآوری بعدی
 */
function calculateNextReminderTime($current_time, $repeat_type)
{
   switch ($repeat_type) {
      case 'daily':
         return date('Y-m-d H:i:s', strtotime($current_time . ' +1 day'));
      case 'weekly':
         return date('Y-m-d H:i:s', strtotime($current_time . ' +1 week'));
      case 'monthly':
         return date('Y-m-d H:i:s', strtotime($current_time . ' +1 month'));
      case 'yearly':
         return date('Y-m-d H:i:s', strtotime($current_time . ' +1 year'));
      default:
         return $current_time;
   }
}
