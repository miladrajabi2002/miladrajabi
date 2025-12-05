<?php

function showTasksMenu($chat_id, $user_id, $message_id)
{
   global $pdo;

   try {
      $today = date('Y-m-d');
      $persian_today = jdate('l j F Y');

      // آمار امروز با error handling
      $stmt = $pdo->prepare(
         "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN priority = 'highh' THEN 1 ELSE 0 END) as `highh_priority`
            FROM tasks WHERE user_id = ? AND task_date = ?"
      );
      $stmt->execute([$user_id, $today]);
      $stats = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$stats) {
         $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'highh_priority' => 0
         ];
      }

      $text = "🗂 <b>مدیریت وظایف</b>\n";
      $text .= "📅 $persian_today\n\n";

      if ($stats['total'] > 0) {
         $progress = round(($stats['completed'] / $stats['total']) * 100);
         $text .= "📊 <b>پیشرفت امروز:</b>\n";
         $text .= "✅ تکمیل شده: {$stats['completed']}\n";
         $text .= "⏳ در انتظار: {$stats['pending']}\n";
         $text .= "🔄 در حال انجام: {$stats['in_progress']}\n";

         if ($stats['highh_priority'] > 0) {
            $text .= "🔥 اولویت بالا: {$stats['highh_priority']}\n";
         }

         $text .= "\n📈 درصد تکمیل: $progress%\n";

         // نوار پیشرفت
         $filled = round($progress / 10);
         $progress_bar = "";
         for ($i = 0; $i < 10; $i++) {
            $progress_bar .= ($i < $filled) ? "🟩" : "⬜";
         }
         $text .= "$progress_bar\n\n";
      } else {
         $text .= "📝 هنوز وظیفه‌ای برای امروز ندارید!\n\n";
      }

      $text .= "<b>امکانات:</b>\n";
      $text .= "• اضافه کردن وظیفه با اولویت‌بندی\n";
      $text .= "• مدیریت وضعیت وظایف\n";
      $text .= "• مرور وظایف روزهای مختلف\n";
      $text .= "• آمار و گزارش‌گیری";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📝 وظایف امروز', 'callback_data' => 'task_today'],
               ['text' => '➕ افزودن وظیفه', 'callback_data' => 'task_add']
            ],
            [
               ['text' => '📅 روزهای دیگر', 'callback_data' => 'task_calendar'],
               ['text' => '📊 آمار و گزارش', 'callback_data' => 'task_stats']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      editMessage($chat_id, $message_id, $text, $keyboard);
   } catch (Exception $e) {
      // لاگ خطا
      error_log("Error in showTasksMenu: " . $e->getMessage());

      $text = "❌ خطا در نمایش منوی وظایف!\n";
      $text .= "جزئیات: " . $e->getMessage();

      editMessage($chat_id, $message_id, $text);
   }
}

function handleTaskCallback($chat_id, $user_id, $data, $message_id)
{
   global $callback_query_id;

   $parts = explode('_', $data);
   $action = $parts[1] ?? '';

   switch ($action) {
      case 'today':
         showTodayTasks($chat_id, $user_id, $message_id);
         break;

      case 'add':
         startAddTask($chat_id, $user_id, $message_id);
         break;

      case 'calendar':
         showTaskCalendar($chat_id, $user_id, $message_id);
         break;

      case 'date':
         $date = $parts[2] ?? date('Y-m-d');
         showTasksByDate($chat_id, $user_id, $message_id, $date);
         break;

      case 'view':
         $task_id = $parts[2] ?? 0;
         viewTaskDetails($chat_id, $user_id, $message_id, $task_id);
         break;

      case 'status':
         $task_id = $parts[2] ?? 0;
         $status = $parts[3] ?? 'pending';
         changeTaskStatus($chat_id, $user_id, $task_id, $status, $message_id);
         break;

      case 'delete':
         $task_id = $parts[2] ?? 0;
         confirmDeleteTask($chat_id, $user_id, $message_id, $task_id);
         break;

      case 'confirmdelete':
         $task_id = $parts[2] ?? 0;
         deleteTask($chat_id, $user_id, $message_id, $task_id);
         break;

      case 'priority':
         $priority = $parts[2] ?? 'medium';
         handleTaskPriorityCallback($chat_id, $user_id, $priority, $message_id);
         break;

      case 'skip':
         if ($parts[2] == 'description') {
            handleSkipDescription($chat_id, $user_id, $message_id);
         }
         break;

      case 'stats':
         showTaskStats($chat_id, $user_id, $message_id);
         break;

      case 'weekly':
         if ($parts[2] == 'report') {
            showWeeklyTaskReport($chat_id, $user_id, $message_id);
         }
         break;

      default:
         answerCallbackQuery($callback_query_id, "❌ دستور نامعتبر");
         break;
   }
}

function showTodayTasks($chat_id, $user_id, $message_id)
{
   global $pdo;

   $today = date('Y-m-d');
   $persian_today = jdate('l j F Y');

   $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND task_date = ? ORDER BY 
        CASE priority WHEN 'highh' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
        CASE status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 END,
        created_at ASC");
   $stmt->execute([$user_id, $today]);
   $tasks = $stmt->fetchAll();

   if (empty($tasks)) {
      $text = "📝 <b>وظایف امروز</b>\n";
      $text .= "📅 $persian_today\n\n";
      $text .= "✨ هیچ وظیفه‌ای برای امروز ندارید!\n\n";
      $text .= "می‌توانید وظیفه جدید اضافه کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن وظیفه', 'callback_data' => 'task_add']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'tasks']]
         ]
      ];
   } else {
      $text = "📝 <b>وظایف امروز</b>\n";
      $text .= "📅 $persian_today\n\n";

      $keyboard = [
         'inline_keyboard' => []
      ];

      foreach ($tasks as $task) {
         // آیکون اولویت
         $priority_icon = match ($task['priority']) {
            'highh' => '🔥',
            'medium' => '🟡',
            'low' => '🔵'
         };

         // آیکون وضعیت
         $status_icon = match ($task['status']) {
            'pending' => '⏳',
            'in_progress' => '🔄',
            'completed' => '✅'
         };

         $title = mb_strlen($task['title']) > 25 ?
            mb_substr($task['title'], 0, 25) . '...' :
            $task['title'];

         $button_text = "$priority_icon $status_icon $title";

         $keyboard['inline_keyboard'][] = [
            ['text' => $button_text, 'callback_data' => 'task_view_' . $task['id']]
         ];
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '➕ افزودن وظیفه', 'callback_data' => 'task_add']
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'tasks']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showTaskCalendar($chat_id, $user_id, $message_id)
{
   global $pdo;

   $text = "📅 <b>تقویم وظایف</b>\n\n";
   $text .= "روز مورد نظر را انتخاب کنید:";

   $keyboard = [
      'inline_keyboard' => []
   ];

   // دیروز
   $yesterday = date('Y-m-d', strtotime('-1 day'));
   $persian_yesterday = jdate('j F', strtotime($yesterday));
   $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_date = ?");
   $stmt->execute([$user_id, $yesterday]);
   $yesterday_count = $stmt->fetchColumn();

   $keyboard['inline_keyboard'][] = [
      ['text' => "📋 دیروز ($persian_yesterday) - $yesterday_count وظیفه", 'callback_data' => 'task_date_' . $yesterday]
   ];

   // امروز
   $today = date('Y-m-d');
   $persian_today = jdate('j F');
   $stmt->execute([$user_id, $today]);
   $today_count = $stmt->fetchColumn();

   $keyboard['inline_keyboard'][] = [
      ['text' => "📝 امروز ($persian_today) - $today_count وظیفه", 'callback_data' => 'task_date_' . $today]
   ];

   // فردا
   $tomorrow = date('Y-m-d', strtotime('+1 day'));
   $persian_tomorrow = jdate('j F', strtotime($tomorrow));
   $stmt->execute([$user_id, $tomorrow]);
   $tomorrow_count = $stmt->fetchColumn();

   $keyboard['inline_keyboard'][] = [
      ['text' => "📅 فردا ($persian_tomorrow) - $tomorrow_count وظیفه", 'callback_data' => 'task_date_' . $tomorrow]
   ];

   // پس فردا
   $day_after = date('Y-m-d', strtotime('+2 days'));
   $persian_day_after = jdate('j F', strtotime($day_after));
   $stmt->execute([$user_id, $day_after]);
   $day_after_count = $stmt->fetchColumn();

   $keyboard['inline_keyboard'][] = [
      ['text' => "📆 پس‌فردا ($persian_day_after) - $day_after_count وظیفه", 'callback_data' => 'task_date_' . $day_after]
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🔙 بازگشت', 'callback_data' => 'tasks']
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showTasksByDate($chat_id, $user_id, $message_id, $date)
{
   global $pdo;

   $persian_date = jdate('l j F Y', strtotime($date));
   $today = date('Y-m-d');

   $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND task_date = ? ORDER BY 
        CASE priority WHEN 'highh' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
        CASE status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 END,
        created_at ASC");
   $stmt->execute([$user_id, $date]);
   $tasks = $stmt->fetchAll();

   if (empty($tasks)) {
      $text = "📅 <b>وظایف $persian_date</b>\n\n";
      $text .= "✨ هیچ وظیفه‌ای برای این روز ندارید!";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن وظیفه', 'callback_data' => 'task_add_date_' . $date]],
            [['text' => '📅 تقویم وظایف', 'callback_data' => 'task_calendar']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'tasks']]
         ]
      ];
   } else {
      $text = "📅 <b>وظایف $persian_date</b>\n\n";

      $completed = 0;
      $total = count($tasks);

      foreach ($tasks as $task) {
         if ($task['status'] == 'completed') $completed++;

         $priority_icon = match ($task['priority']) {
            'highh' => '🔥',
            'medium' => '🟡',
            'low' => '🔵'
         };

         $status_icon = match ($task['status']) {
            'pending' => '⏳',
            'in_progress' => '🔄',
            'completed' => '✅'
         };

         $text .= "$priority_icon $status_icon " . htmlspecialchars($task['title']) . "\n";
      }

      $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
      $text .= "\n📊 پیشرفت: $completed/$total ($progress%)";

      $keyboard = [
         'inline_keyboard' => []
      ];

      foreach ($tasks as $task) {
         $priority_icon = match ($task['priority']) {
            'highh' => '🔥',
            'medium' => '🟡',
            'low' => '🔵'
         };

         $status_icon = match ($task['status']) {
            'pending' => '⏳',
            'in_progress' => '🔄',
            'completed' => '✅'
         };

         $title = mb_strlen($task['title']) > 25 ?
            mb_substr($task['title'], 0, 25) . '...' :
            $task['title'];

         $button_text = "$priority_icon $status_icon $title";

         $keyboard['inline_keyboard'][] = [
            ['text' => $button_text, 'callback_data' => 'task_view_' . $task['id']]
         ];
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '➕ افزودن وظیفه', 'callback_data' => 'task_add_date_' . $date]
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '📅 تقویم وظایف', 'callback_data' => 'task_calendar']
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'tasks']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function startAddTask($chat_id, $user_id, $message_id, $selected_date = null)
{
   $date = $selected_date ?? date('Y-m-d');

   updateUser($user_id, [
      'step' => 'task_adding_title',
      'temp_data' => json_encode(['selected_date' => $date])
   ]);

   $persian_date = jdate('l j F Y', strtotime($date));

   $text = "➕ <b>افزودن وظیفه جدید</b>\n\n";
   $text .= "📅 تاریخ: $persian_date\n\n";
   $text .= "عنوان وظیفه را وارد کنید:\n\n";
   $text .= "مثال‌ها:\n";
   $text .= "• تماس با مشتری\n";
   $text .= "• تهیه گزارش ماهانه\n";
   $text .= "• خرید مواد غذایی\n";
   $text .= "• بررسی ایمیل‌ها";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'tasks']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function viewTaskDetails($chat_id, $user_id, $message_id, $task_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
   $stmt->execute([$task_id, $user_id]);
   $task = $stmt->fetch();

   if (!$task) {
      editMessage($chat_id, $message_id, "❌ وظیفه یافت نشد.");
      return;
   }

   $persian_date = jdate('l j F Y', strtotime($task['task_date']));
   $created_date = jdate('Y/m/d H:i', strtotime($task['created_at']));

   $priority_text = match ($task['priority']) {
      'highh' => '🔥 بالا',
      'medium' => '🟡 متوسط',
      'low' => '🔵 پایین'
   };

   $status_text = match ($task['status']) {
      'pending' => '⏳ در انتظار',
      'in_progress' => '🔄 در حال انجام',
      'completed' => '✅ تکمیل شده'
   };

   $text = "📋 <b>" . htmlspecialchars($task['title']) . "</b>\n\n";
   $text .= "📅 <b>تاریخ:</b> $persian_date\n";
   $text .= "⭐ <b>اولویت:</b> $priority_text\n";
   $text .= "📊 <b>وضعیت:</b> $status_text\n";

   if ($task['description']) {
      $text .= "\n💬 <b>توضیحات:</b>\n" . htmlspecialchars($task['description']) . "\n";
   }

   if ($task['completed_at']) {
      $completed_date = jdate('Y/m/d H:i', strtotime($task['completed_at']));
      $text .= "\n✅ <b>تکمیل شده در:</b> $completed_date\n";
   }

   $text .= "\n📆 <b>تاریخ ایجاد:</b> $created_date";

   $keyboard = [
      'inline_keyboard' => []
   ];

   // دکمه‌های تغییر وضعیت
   if ($task['status'] != 'completed') {
      if ($task['status'] == 'pending') {
         $keyboard['inline_keyboard'][] = [
            ['text' => '🔄 شروع کار', 'callback_data' => 'task_status_' . $task_id . '_in_progress'],
            ['text' => '✅ تکمیل', 'callback_data' => 'task_status_' . $task_id . '_completed']
         ];
      } else {
         $keyboard['inline_keyboard'][] = [
            ['text' => '⏳ برگشت به انتظار', 'callback_data' => 'task_status_' . $task_id . '_pending'],
            ['text' => '✅ تکمیل', 'callback_data' => 'task_status_' . $task_id . '_completed']
         ];
      }
   } else {
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔄 بازگشایی', 'callback_data' => 'task_status_' . $task_id . '_pending']
      ];
   }

   $keyboard['inline_keyboard'][] = [
      ['text' => '🗑 حذف', 'callback_data' => 'task_delete_' . $task_id]
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '📅 بازگشت به تاریخ', 'callback_data' => 'task_date_' . $task['task_date']]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function changeTaskStatus($chat_id, $user_id, $task_id, $status, $message_id)
{
   global $pdo, $callback_query_id;

   if ($status == 'completed') {
      $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NOW() WHERE id = ? AND user_id = ?");
   } else {
      $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NULL WHERE id = ? AND user_id = ?");
   }

   if ($stmt->execute([$status, $task_id, $user_id])) {
      $status_text = match ($status) {
         'pending' => 'در انتظار',
         'in_progress' => 'در حال انجام',
         'completed' => 'تکمیل شده'
      };

      answerCallbackQuery($callback_query_id, "✅ وضعیت به '$status_text' تغییر کرد");
      viewTaskDetails($chat_id, $user_id, $message_id, $task_id);
   } else {
      answerCallbackQuery($callback_query_id, "❌ خطا در تغییر وضعیت");
   }
}

function confirmDeleteTask($chat_id, $user_id, $message_id, $task_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ? AND user_id = ?");
   $stmt->execute([$task_id, $user_id]);
   $task = $stmt->fetch();

   if (!$task) {
      editMessage($chat_id, $message_id, "❌ وظیفه یافت نشد.");
      return;
   }

   $text = "🗑 <b>حذف وظیفه</b>\n\n";
   $text .= "آیا مطمئن هستید که می‌خواهید این وظیفه را حذف کنید؟\n\n";
   $text .= "📋 <b>عنوان:</b> " . htmlspecialchars($task['title']) . "\n\n";
   $text .= "⚠️ این عمل قابل بازگشت نیست!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، حذف کن', 'callback_data' => 'task_confirmdelete_' . $task_id],
            ['text' => '❌ خیر', 'callback_data' => 'task_view_' . $task_id]
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function deleteTask($chat_id, $user_id, $message_id, $task_id)
{
   global $pdo, $callback_query_id;

   $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");

   if ($stmt->execute([$task_id, $user_id])) {
      answerCallbackQuery($callback_query_id, "🗑 وظیفه حذف شد");
      showTodayTasks($chat_id, $user_id, $message_id);
   } else {
      answerCallbackQuery($callback_query_id, "❌ خطا در حذف");
   }
}

function showTaskStats($chat_id, $user_id, $message_id)
{
   global $pdo;

   // آمار کلی
   $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_tasks,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks
        FROM tasks WHERE user_id = ?");
   $stmt->execute([$user_id]);
   $stats = $stmt->fetch();

   // آمار هفته گذشته
   $stmt = $pdo->prepare("SELECT COUNT(*) as week_completed
        FROM tasks WHERE user_id = ? AND status = 'completed' 
        AND task_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
   $stmt->execute([$user_id]);
   $week_stats = $stmt->fetch();

   // آمار ماه گذشته  
   $stmt = $pdo->prepare("SELECT COUNT(*) as month_completed
        FROM tasks WHERE user_id = ? AND status = 'completed'
        AND task_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
   $stmt->execute([$user_id]);
   $month_stats = $stmt->fetch();

   // آمار امروز
   $stmt = $pdo->prepare("SELECT 
        COUNT(*) as today_total,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as today_completed
        FROM tasks WHERE user_id = ? AND task_date = CURDATE()");
   $stmt->execute([$user_id]);
   $today_stats = $stmt->fetch();

   $text = "📊 <b>آمار وظایف</b>\n";
   $text .= "تاریخ: " . jdate('Y/m/d') . "\n\n";

   // آمار کلی
   $total = $stats['total_tasks'] ?? 0;
   $completed = $stats['completed_tasks'] ?? 0;
   $pending = $stats['pending_tasks'] ?? 0;
   $in_progress = $stats['in_progress_tasks'] ?? 0;

   $text .= "📈 <b>آمار کلی:</b>\n";
   $text .= "• کل وظایف: $total\n";
   $text .= "• تکمیل شده: $completed\n";
   $text .= "• در انتظار: $pending\n";
   $text .= "• در حال انجام: $in_progress\n\n";

   if ($total > 0) {
      $completion_rate = round(($completed / $total) * 100);
      $text .= "🎯 <b>نرخ تکمیل کلی:</b> $completion_rate%\n";

      // نوار پیشرفت کلی
      $filled = round($completion_rate / 10);
      $progress_bar = str_repeat('🟩', $filled) . str_repeat('⬜', 10 - $filled);
      $text .= "$progress_bar\n\n";
   }

   // آمار امروز
   $today_total = $today_stats['today_total'] ?? 0;
   $today_completed = $today_stats['today_completed'] ?? 0;

   $text .= "📅 <b>آمار امروز:</b>\n";
   $text .= "• وظایف امروز: $today_total\n";
   $text .= "• تکمیل شده: $today_completed\n";

   if ($today_total > 0) {
      $today_rate = round(($today_completed / $today_total) * 100);
      $text .= "• پیشرفت: $today_rate%\n\n";
   } else {
      $text .= "• هیچ وظیفه‌ای برای امروز ندارید\n\n";
   }

   // آمار دوره‌ای
   $week_completed = $week_stats['week_completed'] ?? 0;
   $month_completed = $month_stats['month_completed'] ?? 0;

   $text .= "⏰ <b>آمار دوره‌ای:</b>\n";
   $text .= "• هفته گذشته: $week_completed وظیفه تکمیل\n";
   $text .= "• ماه گذشته: $month_completed وظیفه تکمیل\n\n";

   // تحلیل عملکرد
   $text .= "🔍 <b>تحلیل عملکرد:</b>\n";

   if ($total == 0) {
      $text .= "• هنوز شروع نکرده‌اید! وظیفه اول را اضافه کنید.\n";
   } elseif ($completion_rate >= 80) {
      $text .= "• 🏆 عملکرد فوق‌العاده! ادامه دهید!\n";
   } elseif ($completion_rate >= 60) {
      $text .= "• 👍 عملکرد خوب! می‌توانید بهتر شوید.\n";
   } elseif ($completion_rate >= 40) {
      $text .= "• 📈 در مسیر درست هستید، بیشتر تلاش کنید.\n";
   } else {
      $text .= "• 💪 نیاز به تمرکز بیشتر! برنامه‌ریزی کنید.\n";
   }

   // پیشنهادات
   if ($pending > $in_progress * 2) {
      $text .= "• 💡 پیشنهاد: چند وظیفه را شروع کنید.\n";
   }

   if ($today_total == 0) {
      $text .= "• 📝 پیشنهاد: برای امروز وظیفه تعریف کنید.\n";
   }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '🔄 بروزرسانی', 'callback_data' => 'task_stats'],
            ['text' => '📊 گزارش هفتگی', 'callback_data' => 'task_weekly_report']
         ],
         [
            ['text' => '📅 تقویم وظایف', 'callback_data' => 'task_calendar']
         ],
         [
            ['text' => '🔙 بازگشت', 'callback_data' => 'tasks']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showWeeklyTaskReport($chat_id, $user_id, $message_id)
{
   global $pdo;

   $text = "📊 <b>گزارش هفتگی وظایف</b>\n";
   $text .= "📅 " . jdate('Y/m/d') . "\n\n";

   // آمار 7 روز گذشته
   for ($i = 6; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days"));
      $persian_date = jdate('l j F', strtotime($date));

      $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
            FROM tasks WHERE user_id = ? AND task_date = ?");
      $stmt->execute([$user_id, $date]);
      $day_stats = $stmt->fetch();

      $total = $day_stats['total'] ?? 0;
      $completed = $day_stats['completed'] ?? 0;

      if ($total > 0) {
         $rate = round(($completed / $total) * 100);
         $text .= "📅 $persian_date: $completed/$total ($rate%)\n";
      } else {
         $text .= "📅 $persian_date: بدون وظیفه\n";
      }
   }

   // آمار کلی هفته
   $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_week,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_week,
        COUNT(CASE WHEN priority = 'highh' AND status = 'completed' THEN 1 END) as highh_completed
        FROM tasks WHERE user_id = ? AND task_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
   $stmt->execute([$user_id]);
   $week_stats = $stmt->fetch();

   $total_week = $week_stats['total_week'] ?? 0;
   $completed_week = $week_stats['completed_week'] ?? 0;
   $highh_completed = $week_stats['highh_completed'] ?? 0;

   $text .= "\n📈 <b>خلاصه هفته:</b>\n";
   $text .= "• کل وظایف: $total_week\n";
   $text .= "• تکمیل شده: $completed_week\n";
   $text .= "• اولویت بالا انجام شده: $highh_completed\n";

   if ($total_week > 0) {
      $week_rate = round(($completed_week / $total_week) * 100);
      $text .= "• نرخ موفقیت: $week_rate%\n\n";

      // نوار پیشرفت
      $filled = round($week_rate / 10);
      $progress_bar = str_repeat('🟩', $filled) . str_repeat('⬜', 10 - $filled);
      $text .= "$progress_bar $week_rate%\n\n";

      // ارزیابی عملکرد
      if ($week_rate >= 80) {
         $text .= "🏆 <b>عملکرد فوق‌العاده!</b> این هفته عالی بود!";
      } elseif ($week_rate >= 60) {
         $text .= "👍 <b>عملکرد خوب!</b> در مسیر درستی هستید.";
      } elseif ($week_rate >= 40) {
         $text .= "📈 <b>قابل بهبود!</b> هفته آینده بهتر خواهد بود.";
      } else {
         $text .= "💪 <b>نیاز به تمرکز بیشتر!</b> برنامه‌ریزی دقیق‌تر کنید.";
      }
   } else {
      $text .= "\n📝 این هفته هیچ وظیفه‌ای ثبت نکرده‌اید.";
   }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '🔄 بروزرسانی', 'callback_data' => 'task_weekly_report'],
            ['text' => '📊 آمار کلی', 'callback_data' => 'task_stats']
         ],
         [
            ['text' => '🔙 بازگشت', 'callback_data' => 'tasks']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function processTaskForm($chat_id, $user_id, $text, $step)
{
   global $pdo;

   switch ($step) {
      case 'task_adding_title':
         $title = trim($text);
         if (empty($title)) {
            sendMessage($chat_id, "❌ عنوان وظیفه نمی‌تواند خالی باشد. لطفاً دوباره وارد کنید:");
            return;
         }

         if (mb_strlen($title) > 255) {
            sendMessage($chat_id, "❌ عنوان وظیفه نمی‌تواند بیش از 255 کاراکتر باشد.");
            return;
         }

         // گرفتن تاریخ انتخابی از temp_data
         $user = getUser($user_id);
         $temp_data = json_decode($user['temp_data'] ?? '{}', true);
         $selected_date = $temp_data['selected_date'] ?? date('Y-m-d');

         // ذخیره عنوان و درخواست اولویت
         updateUser($user_id, [
            'temp_data' => json_encode([
               'title' => $title,
               'selected_date' => $selected_date
            ]),
            'step' => 'task_selecting_priority'
         ]);

         $persian_date = jdate('l j F Y', strtotime($selected_date));

         $response = "📝 <b>انتخاب اولویت</b>\n\n";
         $response .= "📋 عنوان: " . htmlspecialchars($title) . "\n";
         $response .= "📅 تاریخ: $persian_date\n\n";
         $response .= "اولویت این وظیفه را انتخاب کنید:";

         $keyboard = [
            'inline_keyboard' => [
               [
                  ['text' => '🔥 بالا', 'callback_data' => 'task_priority_highh'],
                  ['text' => '🟡 متوسط', 'callback_data' => 'task_priority_medium'],
                  ['text' => '🔵 پایین', 'callback_data' => 'task_priority_low']
               ],
               [
                  ['text' => '🔙 انصراف', 'callback_data' => 'tasks']
               ]
            ]
         ];

         sendMessage($chat_id, $response, $keyboard);
         break;

      case 'task_adding_description':
         $user = getUser($user_id);
         $temp_data = json_decode($user['temp_data'] ?? '{}', true);

         $description = trim($text);
         if (mb_strlen($description) > 1000) {
            sendMessage($chat_id, "❌ توضیحات نمی‌تواند بیش از 1000 کاراکتر باشد.");
            return;
         }

         $selected_date = $temp_data['selected_date'] ?? date('Y-m-d');

         // ذخیره وظیفه
         $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, priority, task_date, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())");

         if ($stmt->execute([
            $user_id,
            $temp_data['title'],
            $description,
            $temp_data['priority'],
            $selected_date
         ])) {
            updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

            $priority_text = match ($temp_data['priority']) {
               'highh' => '🔥 بالا',
               'medium' => '🟡 متوسط',
               'low' => '🔵 پایین'
            };

            $persian_date = jdate('l j F Y', strtotime($selected_date));

            $response = "✅ <b>وظیفه با موفقیت اضافه شد!</b>\n\n";
            $response .= "📋 <b>عنوان:</b> " . htmlspecialchars($temp_data['title']) . "\n";
            $response .= "⭐ <b>اولویت:</b> $priority_text\n";
            $response .= "💬 <b>توضیحات:</b> " . htmlspecialchars($description) . "\n";
            $response .= "📅 <b>تاریخ:</b> $persian_date\n\n";
            $response .= "می‌توانید از منوی وظایف آن را مدیریت کنید.";

            $keyboard = [
               'inline_keyboard' => [
                  [
                     ['text' => '📝 مشاهده وظایف', 'callback_data' => 'task_date_' . $selected_date],
                     ['text' => '➕ افزودن وظیفه دیگر', 'callback_data' => 'task_add']
                  ],
                  [
                     ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
                  ]
               ]
            ];

            sendMessage($chat_id, $response, $keyboard);
         } else {
            sendMessage($chat_id, "❌ خطا در ثبت وظیفه. لطفاً دوباره تلاش کنید.");
            updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);
         }
         break;

      default:
         updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);
         sendMessage($chat_id, "❌ مرحله نامعتبر. لطفاً دوباره تلاش کنید.");
         break;
   }
}

function handleTaskPriorityCallback($chat_id, $user_id, $priority, $message_id)
{
   $user = getUser($user_id);
   $temp_data = json_decode($user['temp_data'] ?? '{}', true);
   $temp_data['priority'] = $priority;

   updateUser($user_id, [
      'temp_data' => json_encode($temp_data),
      'step' => 'task_adding_description'
   ]);

   $priority_text = match ($priority) {
      'highh' => '🔥 بالا',
      'medium' => '🟡 متوسط',
      'low' => '🔵 پایین'
   };

   $persian_date = jdate('l j F Y', strtotime($temp_data['selected_date']));

   $text = "📝 <b>توضیحات وظیفه</b>\n\n";
   $text .= "📋 <b>عنوان:</b> " . htmlspecialchars($temp_data['title']) . "\n";
   $text .= "⭐ <b>اولویت:</b> $priority_text\n";
   $text .= "📅 <b>تاریخ:</b> $persian_date\n\n";
   $text .= "توضیحات اضافی وارد کنید یا روی 'رد کردن' کلیک کنید:\n\n";
   $text .= "<i>توضیحات اختیاری است و می‌تواند شامل جزئیات بیشتر درباره وظیفه باشد.</i>";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '⏭ رد کردن', 'callback_data' => 'task_skip_description']],
         [['text' => '🔙 انصراف', 'callback_data' => 'tasks']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function handleSkipDescription($chat_id, $user_id, $message_id)
{
   global $pdo;

   $user = getUser($user_id);
   $temp_data = json_decode($user['temp_data'] ?? '{}', true);

   $selected_date = $temp_data['selected_date'] ?? date('Y-m-d');

   // ذخیره وظیفه بدون توضیحات
   $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, priority, task_date, created_at) 
        VALUES (?, ?, ?, ?, NOW())");

   if ($stmt->execute([
      $user_id,
      $temp_data['title'],
      $temp_data['priority'],
      $selected_date
   ])) {
      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $priority_text = match ($temp_data['priority']) {
         'highh' => '🔥 بالا',
         'medium' => '🟡 متوسط',
         'low' => '🔵 پایین'
      };

      $persian_date = jdate('l j F Y', strtotime($selected_date));

      $response = "✅ <b>وظیفه با موفقیت اضافه شد!</b>\n\n";
      $response .= "📋 <b>عنوان:</b> " . htmlspecialchars($temp_data['title']) . "\n";
      $response .= "⭐ <b>اولویت:</b> $priority_text\n";
      $response .= "📅 <b>تاریخ:</b> $persian_date\n\n";
      $response .= "می‌توانید از منوی وظایف آن را مدیریت کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📝 مشاهده وظایف', 'callback_data' => 'task_date_' . $selected_date],
               ['text' => '➕ افزودن وظیفه دیگر', 'callback_data' => 'task_add']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      editMessage($chat_id, $message_id, $response, $keyboard);
   } else {
      editMessage($chat_id, $message_id, "❌ خطا در ثبت وظیفه. لطفاً دوباره تلاش کنید.");
      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);
   }
}
