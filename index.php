<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/jdf.php';

try {
   function generateSmartSummary()
   {
      global $pdo;

      $summary = [];
      $today = date('Y-m-d');
      $persian_today = jdate('Y/n/j');

      // 🔔 یادآورهای امروز
      $stmt = $pdo->prepare(
         "SELECT COUNT(*) FROM reminders 
               WHERE is_active = 1 
               AND DATE(reminder_time) = ?"
      );
      $stmt->execute([$today]);
      $today_reminders = $stmt->fetchColumn();

      if ($today_reminders > 0) {
         $summary[] = "├ $today_reminders یادآور امروز";
      }

      // ✅ عادت‌های امروز
      $stmt = $pdo->prepare(
         "SELECT h.name FROM habits h
               LEFT JOIN habit_logs hl ON h.id = hl.habit_id AND hl.completed_date = ?
               WHERE h.is_active = 1 AND hl.id IS NULL"
      );
      $stmt->execute([$today]);
      $pending_habits = $stmt->fetchAll();

      $stmt = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE is_active = 1");
      $stmt->execute();
      $total_habits = $stmt->fetchColumn();

      if ($total_habits > 0) {
         if (count($pending_habits) > 0) {
            $summary[] = "├ " . count($pending_habits) . " عادت باقی‌مانده امروز";
         } else {
            $summary[] = "├ همه عادت‌های امروز انجام شد!";
         }
      }

      // 🎯 اهداف نزدیک به سررسید (30 روز)
      $stmt = $pdo->prepare(
         "SELECT title, target_date, progress FROM goals 
               WHERE is_completed = 0 
               AND target_date IS NOT NULL 
               AND target_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               ORDER BY target_date ASC LIMIT 2"
      );
      $stmt->execute();
      $upcoming_goals = $stmt->fetchAll();

      foreach ($upcoming_goals as $goal) {
         $days_left = ceil((strtotime($goal['target_date']) - strtotime($today)) / 86400);
         $summary[] = "├ {$goal['title']} ({$goal['progress']}% - $days_left روز مانده)";
      }

      // 📄 اسناد منقضی‌شونده (30 روز)
      $stmt = $pdo->prepare(
         "SELECT name, expire_date FROM documents 
               WHERE expire_date IS NOT NULL 
               AND expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               ORDER BY expire_date ASC LIMIT 2"
      );
      $stmt->execute();
      $expiring_docs = $stmt->fetchAll();

      foreach ($expiring_docs as $doc) {
         $days_left = ceil((strtotime($doc['expire_date']) - strtotime($today)) / 86400);
         $summary[] = "├ {$doc['name']} ($days_left روز تا انقضا)";
      }

      // 💰 چک‌های سررسید (7 روز)
      $stmt = $pdo->prepare(
         "SELECT type, account_holder, amount, due_date FROM checks 
               WHERE status = 'pending' 
               AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               ORDER BY due_date ASC LIMIT 2"
      );
      $stmt->execute();
      $due_checks = $stmt->fetchAll();

      foreach ($due_checks as $check) {
         $days_left = ceil((strtotime($check['due_date']) - strtotime($today)) / 86400);
         $type_text = $check['type'] == 'received' ? 'دریافتی از' : 'پرداختی به';
         $amount = number_format($check['amount']);
         $summary[] = "├ چک $type_text {$check['account_holder']} ({$amount} تومان - $days_left روز)";
      }

      // 💳 بدهی/طلب سررسید
      $stmt = $pdo->prepare(
         "SELECT type, title, person_name, amount, due_date FROM finances 
               WHERE is_paid = 0 AND due_date IS NOT NULL
               AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               ORDER BY due_date ASC LIMIT 2"
      );
      $stmt->execute();
      $due_debts = $stmt->fetchAll();

      foreach ($due_debts as $debt) {
         $days_left = ceil((strtotime($debt['due_date']) - strtotime($today)) / 86400);
         $type_text = $debt['type'] == 'debt' ? 'بدهی به' : 'طلب از';
         $amount = number_format($debt['amount']);
         $summary[] = "├ $type_text {$debt['person_name']} ({$amount} تومان - $days_left روز)";
      }

      // 🎂 تولدهای امروز
      $stmt = $pdo->prepare("SELECT name FROM contacts 
        WHERE birthday IS NOT NULL
        AND DATE_FORMAT(birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')");
      $stmt->execute();
      $today_birthdays = $stmt->fetchAll();

      if (!empty($today_birthdays)) {
         foreach ($today_birthdays as $birthday) {
            $summary[] = "├ امروز تولد " . $birthday['name'] . " است!";
         }
      }

      // 🎁 تولدهای نزدیک (7 روز آینده - بدون امروز)
      $stmt = $pdo->prepare("SELECT name, birthday, 
        CASE 
            WHEN DATE_FORMAT(birthday, '%m-%d') > DATE_FORMAT(CURDATE(), '%m-%d') THEN
                DATEDIFF(
                    CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(birthday, '%m-%d')),
                    CURDATE()
                )
            ELSE
                DATEDIFF(
                    CONCAT(YEAR(CURDATE()) + 1, '-', DATE_FORMAT(birthday, '%m-%d')),
                    CURDATE()
                )
        END as days_until
        FROM contacts 
        WHERE birthday IS NOT NULL
        HAVING days_until > 0 AND days_until <= 7
        ORDER BY days_until ASC
        LIMIT 3");
      $stmt->execute();
      $upcoming_birthdays = $stmt->fetchAll();

      foreach ($upcoming_birthdays as $birthday) {
         $days = $birthday['days_until'];
         if ($days == 1) {
            $summary[] = "├ فردا تولد " . $birthday['name'];
         } else {
            $summary[] = "├ $days روز دیگر تولد " . $birthday['name'];
         }
      }

      $summary[count($summary) - 1] = str_replace('├', '└', end($summary));

      // اگر هیچ موردی نبود
      if (empty($summary)) {
         return "✨ همه چیز مرتب است! امروز روز عالی‌ای خواهد بود.";
      }

      // return implode("\n", array_slice($summary, 0, 5)); // حداکثر 5 مورد
      return implode("\n", $summary); // حداکثر 5 مورد
   }

   function askToSaveAsNote($chat_id, $user_id, $text)
   {
      // ذخیره موقت متن
      updateUser($user_id, ['temp_note' => $text]);

      $response = "📝 متن شما به عنوان یادداشت ذخیره شود؟\n\n";
      $response .= "💬 <b>متن:</b> " . htmlspecialchars(mb_substr($text, 0, 100)) . (mb_strlen($text) > 100 ? '...' : '');

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '✅ بله، ذخیره کن', 'callback_data' => 'save_note_yes'],
               ['text' => '❌ خیر', 'callback_data' => 'save_note_no']
            ],
            [
               ['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   }

   function updatePreviewTitle($chat_id, $user_id, $new_title)
   {
      $user = getUser($user_id);

      if ($user['temp_reminder']) {
         $reminder_data = json_decode($user['temp_reminder'], true);
         $reminder_data['title'] = trim($new_title);

         updateUser($user_id, [
            'temp_reminder' => json_encode($reminder_data),
            'step' => 'completed'
         ]);

         sendMessage($chat_id, "✅ عنوان یادآور بروزرسانی شد.");

         // نمایش مجدد پیش‌نمایش
         require_once 'modules/reminder.php';
         showReminderPreview($chat_id, $user_id, $reminder_data);
      }
   }

   function updateExistingReminderTitle($chat_id, $user_id, $reminder_id, $new_title)
   {
      global $pdo;
      $new_title = trim($new_title);

      $stmt = $pdo->prepare("UPDATE reminders SET title = :title WHERE id = :id");
      $stmt->bindParam(':title', $new_title, PDO::PARAM_STR);
      $stmt->bindParam(':id', $reminder_id, PDO::PARAM_INT);

      if ($stmt->execute()) {
         updateUser($user_id, ['step' => 'completed']);
         sendMessage($chat_id, "✅ عنوان یادآور بروزرسانی شد.");

         // نمایش مجدد جزئیات
         require_once 'modules/reminder.php';
         showReminderDetails($chat_id, $user_id, $reminder_id, 0);
      } else {
         sendMessage($chat_id, "❌ خطا در بروزرسانی عنوان.");
      }
   }


   function requestRegistration($chat_id, $user_id, $return_to)
   {
      $text = "✅ برای استفاده از ربات باید اول یه ثبت‌نام سریع انجام بدی کلا هم دو مرحله هستش.\n\n";
      $text .= "نام کامل خود را وارد کنید:";

      // ذخیره مقصد برگشت
      updateUser($user_id, ['step' => 'waiting_name', 'return_to' => $return_to]);

      sendMessage($chat_id, $text);
   }


   function handleStart($chat_id, $user_id, $first_name, $last_name, $username)
   {
      showMainMenu($chat_id, $user_id);
   }

   // منوی اصلی (بدون نمایش اشتراک پریمیوم)
   function showMainMenu($chat_id, $user_id, $text = null)
   {
      $user = getUser($user_id);
      $name = $user['full_name'] ?? $user['first_name'];

      $summary = generateSmartSummary();

      if ($text == null) {
         $menu_text = "🏠 سلام $name عزیز!\n\n";
         $menu_text .= "📋 <b>خلاصه وضعیت:</b>\n";
         $menu_text .= $summary . "\n\n";
         $menu_text .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
      } else {
         $menu_text = $text;
      }

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '🕑 یادآورها', 'callback_data' => 'reminders'],
               ['text' => '📒 یادداشت‌ها', 'callback_data' => 'notes']
            ],
            [
               ['text' => '💰 مالی', 'callback_data' => 'finance'],
               ['text' => '✅ عادت‌ها', 'callback_data' => 'habits']
            ],
            [
               ['text' => '📑 اسناد', 'callback_data' => 'documents'],
               ['text' => '🗂 وظایف', 'callback_data' => 'tasks']
            ],
            [
               ['text' => '🎯 اهداف', 'callback_data' => 'goals'],
               ['text' => '👥 مخاطبین', 'callback_data' => 'social']
            ]
         ]
      ];

      sendMessage($chat_id, $menu_text, $keyboard);

      updateUser($user_id, ['step' => 'completed']);
   }

   // مدیریت دستورات اصلی
   function handleMainCommands($chat_id, $user_id, $text, $user)
   {
      if ($user['step'] && $user['step'] !== 'completed') {
         handleSteps($chat_id, $user_id, $text, $user);
         return;
      }

      // تشخیص هوشمند نوع پیام
      elseif (detectReminderText($text)) {
         require_once 'modules/reminder.php';
         processReminderText($chat_id, $user_id, $text);
      } else {
         // پرسش برای ذخیره به عنوان یادداشت
         askToSaveAsNote($chat_id, $user_id, $text);
      }
   }

   // مدیریت مراحل ثبت نام و ویرایش
   function handleSteps($chat_id, $user_id, $text, $user)
   {
      global $message, $update;

      $step = $user['step'];

      // مراحل ثبت نام
      if ($step === 'waiting_name') {
         updateUser($user_id, ['full_name' => $text, 'step' => 'waiting_phone']);
         sendMessage(
            $chat_id,
            "📱 شماره تلفن خود را وارد یا از دکمه زیر به اشتراک بگذارید:",
            [
               'keyboard' => [[['text' => 'ارسال شماره', 'request_contact' => true]]],
               'resize_keyboard' => true,
               'one_time_keyboard' => true
            ]
         );
         return;
      }

      if ($step === 'waiting_phone') {

         if (isset($update['message']['contact'])) {
            $normalized = normalizeIranPhone($update['message']['contact']['phone_number']);
         } else {
            $normalized = normalizeIranPhone($text);
         }

         if ($normalized === false) {
            sendMessage($chat_id, "❌ لطفا یک شماره موبایل معتبر ایران وارد کنید.\nفرمت‌های معتبر: 09123456789 یا +989123456789");
            return;
         }

         updateUser($user_id, ['phone' => $normalized, 'step' => 'completed']);
         sendMessage(
            $chat_id,
            "✅ ثبت نام شما تکمیل شد",
            ['remove_keyboard' => true]
         );
         showMainMenu($chat_id, $user_id);
         return;
      }

      // مراحل یادآور
      if ($step === 'changing_preview_title') {
         updatePreviewTitle($chat_id, $user_id, $text);
         return;
      }

      // مراحل مالی
      if (strpos($step, 'finance_') === 0) {
         require_once 'modules/finance.php';
         processFinancialForm($chat_id, $user_id, $text, $step);
         return;
      }

      if (strpos($step, 'habit_') === 0) {
         require_once 'modules/habits.php';
         processHabitForm($chat_id, $user_id, $text, $step);
         return;
      }

      if (strpos($step, 'social_') === 0) {
         require_once 'modules/social.php';
         processSocialForm($chat_id, $user_id, $text, $step);
         return;
      }

      if (strpos($step, 'goal_') === 0) {
         require_once 'modules/goals.php';
         processGoalForm($chat_id, $user_id, $text, $step);
         return;
      }

      // مراحل مالی
      if (strpos($step, 'task_') === 0) {
         require_once 'modules/tasks.php';
         processTaskForm($chat_id, $user_id, $text, $step);
         return;
      }

      // مراحل ویرایش
      if (strpos($step, 'editing_note_') === 0) {
         $note_id = str_replace('editing_note_', '', $step);
         require_once 'modules/notes.php';
         updateNote($chat_id, $user_id, $note_id, $text);
         return;
      }

      if ($step == 'searching_notes') {
         require_once 'modules/notes.php';
         searchNotes($chat_id, $user_id, $text);
         return;
      }

      if (strpos($step, 'editing_reminder_title_') === 0) {
         $reminder_id = str_replace('editing_reminder_title_', '', $step);
         updateExistingReminderTitle($chat_id, $user_id, $reminder_id, $text);
         return;
      }

      // مدیریت ارسال فایل برای اسناد
      if ($step == 'waiting_document_file') {
         if (
            isset($message['photo']) || isset($message['document']) ||
            isset($message['video']) || isset($message['audio'])
         ) {
            require_once 'modules/documents.php';
            saveDocumentFile($chat_id, $user_id, $message);
            return;
         } else {
            sendMessage($chat_id, "❌ لطفاً یک فایل ارسال کنید.");
            return;
         }
      }

      if (strpos($step, 'waiting_document_') === 0) {
         require_once 'modules/documents.php';
         processDocumentForm($chat_id, $user_id, $text, $step);
         return;
      }

      // پیش‌فرض
      updateUser($user_id, ['step' => 'completed']);
      sendMessage($chat_id, "❌ مرحله نامشخص. لطفاً دوباره تلاش کنید.");
   }

   // مدیریت callback query
   function handleCallbackQuery($chat_id, $user_id, $data, $message_id)
   {
      try {

         $user = getUser($user_id);

         // مدیریت ذخیره یادداشت
         if ($data == 'save_note_yes') {
            if ($user['temp_note']) {
               require_once 'modules/notes.php';
               saveQuickNote($chat_id, $user_id, $user['temp_note'], true);
               updateUser($user_id, ['temp_note' => null]);
            }
            return;
         } elseif ($data == 'save_note_no') {
            updateUser($user_id, ['temp_note' => null]);
            deleteMessage($chat_id, $message_id);
            deleteMessage($chat_id, $message_id - 1);
            return;
         }

         switch ($data) {
            case 'reminders':
               require_once 'modules/reminder.php';
               showReminderMenu($chat_id, $user_id, $message_id);
               break;
            case 'notes':
               require_once 'modules/notes.php';
               showNotesMenu($chat_id, $user_id, $message_id);
               break;
            case 'finance':
               require_once 'modules/finance.php';
               showFinanceMenu($chat_id, $user_id, $message_id);
               break;
            case 'habits':
               require_once 'modules/habits.php';
               showHabitsMenu($chat_id, $user_id, $message_id);
               break;
            case 'documents':
               require_once 'modules/documents.php';
               showDocumentsMenu($chat_id, $user_id, $message_id);
               break;
            case 'tasks':
               require_once 'modules/tasks.php';
               showTasksMenu($chat_id, $user_id, $message_id);
               break;
            case 'goals':
               require_once 'modules/goals.php';
               showGoalsMenu($chat_id, $user_id, $message_id);
               break;
            case 'social':
               require_once 'modules/social.php';
               showSocialMenu($chat_id, $user_id, $message_id);
               break;
            case 'back_main':
               deleteMessage($chat_id, $message_id);
               showMainMenu($chat_id, $user_id);
               break;
            default:
               // بررسی نیاز به ثبت نام
               // if ($data != 'back_main' && !isUserRegistered($user)) {
               //    requestRegistration($chat_id, $user_id, $data);
               //    return;
               // }

               // ارسال به ماژول مربوطه
               if (strpos($data, 'reminder_') === 0) {
                  require_once 'modules/reminder.php';
                  handleReminderCallback($chat_id, $user_id, $data, $message_id);
               } elseif (strpos($data, 'note_') === 0) {
                  require_once 'modules/notes.php';
                  handleNoteCallback($chat_id, $user_id, $data, $message_id);
               } elseif (strpos($data, 'finance_') === 0) {
                  require_once 'modules/finance.php';
                  handleFinanceCallback($chat_id, $user_id, $data, $message_id);
               } elseif (strpos($data, 'habit_') === 0) {
                  require_once 'modules/habits.php';
                  handleHabitCallback($chat_id, $user_id, $data, $message_id);
               } elseif (strpos($data, 'task_') === 0) {
                  require_once 'modules/tasks.php';
                  handleTaskCallback($chat_id, $user_id, $data, $message_id);
               } elseif (strpos($data, 'goal_') === 0) {
                  require_once 'modules/goals.php';
                  handleGoalCallback($chat_id, $user_id, $data, $message_id);
               } elseif (strpos($data, 'social_') === 0) {
                  require_once 'modules/social.php';
                  handleSocialCallback($chat_id, $user_id, $data, $message_id);
               } elseif (strpos($data, 'doc_') === 0) {
                  require_once 'modules/documents.php';
                  handleDocumentCallback($chat_id, $user_id, $data, $message_id);
               }
               break;
         }
      } catch (\Throwable $th) {
         sendMessage($ADMINS[0], "BUG\n\n" . $th);
      }
   }

   //////////////////////////////////////////////////////////////////////////////////////////////////////////
   $content = file_get_contents("php://input");
   $update = json_decode($content, true);

   if (!$update) {
      exit;
   }

   $message = $update['message'] ?? null;
   $callback_query = $update['callback_query'] ?? null;

   if ($message) {
      $chat_id = $message['chat']['id'];
      $user_id = $message['from']['id'];
      $text = $message['text'] ?? '';
      $first_name = $message['from']['first_name'] ?? '';
      $last_name = $message['from']['last_name'] ?? '';
      $username = $message['from']['username'] ?? '';

      if (!isAdmin($user_id)) {
         exit;
      }

      $user = getUser($user_id);

      if ($text == '/start') {
         handleStart($chat_id, $user_id, $first_name, $last_name, $username);
      } elseif (strpos($text, '/') === 0) {
         showMainMenu($chat_id, $user_id);
      } elseif (!$user && $text != '/start') {
         sendMessage($chat_id, "لطفاً ابتدا /start را بزنید.");
      } else {
         handleMainCommands($chat_id, $user_id, $text, $user);
      }
   } elseif ($callback_query) {
      $chat_id = $callback_query['message']['chat']['id'];
      $user_id = $callback_query['from']['id'];
      $data = $callback_query['data'];
      $message_id = $callback_query['message']['message_id'];
      $callback_query_id = $callback_query['id'];

      if (!isAdmin($user_id)) {
         exit;
      }

      handleCallbackQuery($chat_id, $user_id, $data, $message_id);
   }
} catch (\Throwable $th) {
   sendMessage($ADMINS[0], "BUG\n\n" . $th);
}

