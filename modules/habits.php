<?php

function showHabitsMenu($chat_id, $user_id, $message_id)
{
   global $pdo;

   // آمار کلی عادت‌ها
   $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_habits,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_habits,
        SUM(total_completed) as total_completions
        FROM habits WHERE user_id = :user_id");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $stats = $stmt->fetch();

   // عادت‌های امروز (فعال)
   $stmt = $pdo->prepare("SELECT h.*, 
        CASE WHEN hl.completed_date = CURDATE() THEN 1 ELSE 0 END as completed_today
        FROM habits h
        LEFT JOIN habit_logs hl ON h.id = hl.habit_id AND hl.completed_date = CURDATE()
        WHERE h.user_id = :user_id AND h.is_active = 1
        ORDER BY completed_today ASC, h.name ASC");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $today_habits = $stmt->fetchAll();

   $text = "✅ <b>مدیریت عادت‌ها</b>\n\n";

   // نمایش عادت‌های امروز
   if (!empty($today_habits)) {
      $completed_count = 0;
      $pending_habits = [];
      $completed_habits = [];

      foreach ($today_habits as $habit) {
         if ($habit['completed_today']) {
            $completed_count++;
            $completed_habits[] = "✅ " . htmlspecialchars($habit['name']);
         } else {
            $pending_habits[] = "⏳ " . htmlspecialchars($habit['name']);
         }
      }

      $total_today = count($today_habits);
      $progress_percent = $total_today > 0 ? round(($completed_count / $total_today) * 100) : 0;

      $text .= "📅 <b>عادت‌های امروز:</b>\n";
      $text .= "📊 پیشرفت: $completed_count/$total_today ($progress_percent%)\n\n";

      // نمایش نوار پیشرفت
      $progress_bar = "";
      $filled = round($progress_percent / 10);
      for ($i = 0; $i < 10; $i++) {
         $progress_bar .= ($i < $filled) ? "🟩" : "⬜";
      }
      $text .= "$progress_bar\n\n";

      // نمایش عادت‌های انجام نشده
      if (!empty($pending_habits)) {
         $text .= "⏳ <b>در انتظار انجام:</b>\n";
         foreach (array_slice($pending_habits, 0, 3) as $habit) {
            $text .= "$habit\n";
         }
         if (count($pending_habits) > 3) {
            $text .= "... و " . (count($pending_habits) - 3) . " عادت دیگر\n";
         }
         $text .= "\n";
      }

      // نمایش عادت‌های انجام شده
      if (!empty($completed_habits)) {
         $text .= "✅ <b>انجام شده:</b>\n";
         foreach (array_slice($completed_habits, 0, 2) as $habit) {
            $text .= "$habit\n";
         }
         if (count($completed_habits) > 2) {
            $text .= "... و " . (count($completed_habits) - 2) . " عادت دیگر\n";
         }
         $text .= "\n";
      }
   } else {
      $text .= "❌ هیچ عادت فعالی ندارید!\n\n";
   }

   // آمار کلی
   $total_habits = $stats['total_habits'] ?? 0;
   $active_habits = $stats['active_habits'] ?? 0;
   $total_completions = $stats['total_completions'] ?? 0;

   $text .= "📊 <b>آمار کلی:</b>\n";
   $text .= "🎯 عادت‌های فعال: $active_habits از $total_habits\n";
   if ($total_completions > 0) {
      $text .= "📈 کل انجام شده: $total_completions بار\n";
   }

   $text .= "\n<b>امکانات:</b>\n";
   $text .= "• ثبت و پیگیری عادت‌های روزانه\n";
   $text .= "• آمار هفتگی و ماهیانه\n";
   $text .= "• یادآوری هوشمند\n";
   $text .= "• ریست خودکار شبانه";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ تیک زدن امروز', 'callback_data' => 'habit_today']
         ],
         [
            ['text' => "📋 لیست عادت‌ها ($total_habits)", 'callback_data' => 'habit_list'],
            ['text' => '➕ افزودن عادت', 'callback_data' => 'habit_add']
         ],
         [
            ['text' => '📊 آمار و گزارش', 'callback_data' => 'habit_stats']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function handleHabitCallback($chat_id, $user_id, $data, $message_id)
{
   global $callback_query_id;

   $parts = explode('_', $data);
   $action = $parts[1] ?? '';

   switch ($action) {
      case 'list':
         $page = $parts[2] ?? 1;
         showHabitsList($chat_id, $user_id, $message_id, $page);
         break;

      case 'add':
         startAddHabit($chat_id, $user_id, $message_id);
         break;

      case 'today':
         showTodayHabits($chat_id, $user_id, $message_id);
         break;

      case 'stats':
         showHabitsStats($chat_id, $user_id, $message_id);
         break;

      case 'view':
         $habit_id = $parts[2] ?? 0;
         viewHabitDetails($chat_id, $user_id, $message_id, $habit_id);
         break;

      case 'complete':
         $habit_id = $parts[2] ?? 0;
         completeHabit($chat_id, $user_id, $habit_id, $message_id);
         break;

      case 'uncomplete':
         $habit_id = $parts[2] ?? 0;
         uncompleteHabit($chat_id, $user_id, $habit_id, $message_id);
         break;

      case 'toggle':
         $habit_id = $parts[2] ?? 0;
         toggleHabitStatus($chat_id, $user_id, $habit_id, $message_id);
         break;

      case 'delete':
         $habit_id = $parts[2] ?? 0;
         confirmDeleteHabit($chat_id, $user_id, $message_id, $habit_id);
         break;

      case 'confirmdelete':
         $habit_id = $parts[2] ?? 0;
         deleteHabit($chat_id, $user_id, $message_id, $habit_id);
         break;

      default:
         answerCallbackQuery($callback_query_id, "❌ دستور نامعتبر");
         break;
   }
}

function showHabitsList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $limit = 6;
   $offset = ($page - 1) * $limit;

   $stmt = $pdo->prepare("SELECT * FROM habits WHERE user_id = :user_id ORDER BY is_active DESC, name ASC LIMIT :limit OFFSET :offset");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $habits = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = :user_id");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $total = $stmt->fetchColumn();

   if (empty($habits)) {
      $text = "📋 <b>لیست عادت‌ها</b>\n\n";
      $text .= "❌ هیچ عادتی ثبت نشده!\n\n";
      $text .= "برای شروع، عادت جدیدی اضافه کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن عادت', 'callback_data' => 'habit_add']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'habits']]
         ]
      ];
   } else {
      $text = "📋 <b>لیست عادت‌ها</b>\n";
      $text .= "صفحه $page از " . ceil($total / $limit) . " • مجموع: $total عادت";

      $keyboard = [
         'inline_keyboard' => []
      ];

      foreach ($habits as $habit) {
         $status_icon = $habit['is_active'] ? '🟢' : '⭕';
         $name = mb_strlen($habit['name']) > 25 ? mb_substr($habit['name'], 0, 25) . '...' : $habit['name'];
         $button_text = "$status_icon $name";

         $keyboard['inline_keyboard'][] = [
            [
               'text' => $button_text,
               'callback_data' => 'habit_view_' . $habit['id']
            ]
         ];
      }

      // صفحه‌بندی
      $total_pages = ceil($total / $limit);
      if ($total_pages > 1) {
         $pagination_row = [];

         if ($page > 1) {
            $pagination_row[] = ['text' => '⬅️', 'callback_data' => 'habit_list_' . ($page - 1)];
         }

         $start_page = max(1, $page - 2);
         $end_page = min($total_pages, $start_page + 4);

         for ($i = $start_page; $i <= $end_page; $i++) {
            $page_text = ($i == $page) ? "[$i]" : "$i";
            $pagination_row[] = ['text' => $page_text, 'callback_data' => 'habit_list_' . $i];
         }

         if ($page < $total_pages) {
            $pagination_row[] = ['text' => '➡️', 'callback_data' => 'habit_list_' . ($page + 1)];
         }

         $keyboard['inline_keyboard'][] = $pagination_row;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '➕ افزودن عادت', 'callback_data' => 'habit_add']
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'habits']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showTodayHabits($chat_id, $user_id, $message_id)
{
   global $pdo;

   // عادت‌های فعال امروز
   $stmt = $pdo->prepare("SELECT h.*, 
        CASE WHEN hl.completed_date = CURDATE() THEN 1 ELSE 0 END as completed_today
        FROM habits h
        LEFT JOIN habit_logs hl ON h.id = hl.habit_id AND hl.completed_date = CURDATE()
        WHERE h.user_id = :user_id AND h.is_active = 1
        ORDER BY completed_today ASC, h.name ASC");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $habits = $stmt->fetchAll();

   if (empty($habits)) {
      $text = "✅ <b>عادت‌های امروز</b>\n";
      $text .= "📅 " . jdate('l j F Y') . "\n\n";
      $text .= "❌ هیچ عادت فعالی ندارید!\n\n";
      $text .= "ابتدا عادت‌های خود را اضافه کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن عادت', 'callback_data' => 'habit_add']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'habits']]
         ]
      ];
   } else {
      $completed_count = 0;
      $total_count = count($habits);

      foreach ($habits as $habit) {
         if ($habit['completed_today']) {
            $completed_count++;
         }
      }

      $progress_percent = round(($completed_count / $total_count) * 100);


      $text = "✅ <b>عادت‌های امروز</b>\n";
      $text .= "📅 " . jdate('l j F Y') . "\n";
      $text .= "📊 پیشرفت: $completed_count/$total_count ($progress_percent%)\n\n";

      // نمایش نوار پیشرفت
      $progress_bar = "";
      $filled = round($progress_percent / 10);
      for ($i = 0; $i < 10; $i++) {
         $progress_bar .= ($i < $filled) ? "🟩" : "⬜";
      }
      $text .= "$progress_bar\n\n";

      $keyboard = [
         'inline_keyboard' => []
      ];

      // جداسازی عادت‌های انجام شده و نشده
      $pending_habits = [];
      $completed_habits = [];

      foreach ($habits as $habit) {
         if ($habit['completed_today']) {
            $completed_habits[] = $habit;
         } else {
            $pending_habits[] = $habit;
         }
      }

      // نمایش عادت‌های انجام نشده
      if (!empty($pending_habits)) {
         foreach ($pending_habits as $habit) {
            $name = mb_strlen($habit['name']) > 25 ? mb_substr($habit['name'], 0, 25) . '...' : $habit['name'];
            $button_text = "⏳ $name";

            $keyboard['inline_keyboard'][] = [
               [
                  'text' => $button_text,
                  'callback_data' => "habit_complete_" . $habit['id']
               ]
            ];
         }
      }

      // نمایش عادت‌های انجام شده
      if (!empty($completed_habits)) {
         $text .= "✅ <b>انجام شده:</b>\n";
         foreach ($completed_habits as $habit) {
            $name = mb_strlen($habit['name']) > 25 ? mb_substr($habit['name'], 0, 25) . '...' : $habit['name'];
            $button_text = "✅ $name";

            $keyboard['inline_keyboard'][] = [
               [
                  'text' => $button_text,
                  'callback_data' => "habit_uncomplete_" . $habit['id']
               ]
            ];
         }
      }

      // پیام تشویقی یا هشدار
      if ($completed_count == $total_count) {
         $text .= "\n🎉 <b>عالی! تمام عادت‌های امروز را انجام دادید!</b>";
      } elseif ($current_hour >= 20 && $completed_count < $total_count) {
         $remaining = $total_count - $completed_count;
         $text .= "\n⚠️ <b>هنوز $remaining عادت باقی مانده!</b>";
         $text .= "\n⏰ تا نیمه‌شب فرصت دارید.";
      } elseif ($completed_count > 0) {
         $text .= "\n💪 <b>خوب پیش می‌روید! ادامه دهید.</b>";
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'habits']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function viewHabitDetails($chat_id, $user_id, $message_id, $habit_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM habits WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $habit_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $habit = $stmt->fetch();

   if (!$habit) {
      editMessage($chat_id, $message_id, "❌ عادت یافت نشد.");
      return;
   }

   // بررسی انجام امروز
   $stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE habit_id = :habit_id AND completed_date = CURDATE()");
   $stmt->bindValue(':habit_id', $habit_id, PDO::PARAM_INT);
   $stmt->execute();
   $completed_today = $stmt->fetchColumn() > 0;

   // آمار هفته گذشته
   $stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs 
        WHERE habit_id = :habit_id AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
   $stmt->bindValue(':habit_id', $habit_id, PDO::PARAM_INT);
   $stmt->execute();
   $week_completions = $stmt->fetchColumn();

   // آمار ماه گذشته
   $stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs 
        WHERE habit_id = :habit_id AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
   $stmt->bindValue(':habit_id', $habit_id, PDO::PARAM_INT);
   $stmt->execute();
   $month_completions = $stmt->fetchColumn();

   $text = "📋 <b>" . htmlspecialchars($habit['name']) . "</b>\n\n";

   $status_text = $habit['is_active'] ? '🟢 فعال' : '⭕ غیرفعال';
   $text .= "📊 <b>وضعیت:</b> $status_text\n";

   $today_status = $completed_today ? '✅ انجام شده' : '⏳ در انتظار';
   $text .= "📅 <b>امروز:</b> $today_status\n\n";

   $text .= "📈 <b>کل انجام شده:</b> {$habit['total_completed']} بار\n";
   $text .= "📊 <b>این هفته:</b> $week_completions از 7 روز\n";
   $text .= "📈 <b>این ماه:</b> $month_completions از 30 روز\n\n";

   $created_date = jdate('Y/m/d', strtotime($habit['created_at']));
   $text .= "📆 <b>تاریخ ایجاد:</b> $created_date";

   $keyboard = [
      'inline_keyboard' => []
   ];

   // دکمه تیک زدن/برداشتن
   if ($habit['is_active']) {
      if ($completed_today) {
         $keyboard['inline_keyboard'][] = [
            ['text' => '❌ لغو انجام امروز', 'callback_data' => 'habit_uncomplete_' . $habit_id]
         ];
      } else {
         $keyboard['inline_keyboard'][] = [
            ['text' => '✅ انجام دادم', 'callback_data' => 'habit_complete_' . $habit_id]
         ];
      }
   }

   // دکمه‌های مدیریت
   $toggle_text = $habit['is_active'] ? '⭕ غیرفعال کردن' : '🟢 فعال کردن';
   $keyboard['inline_keyboard'][] = [
      ['text' => $toggle_text, 'callback_data' => 'habit_toggle_' . $habit_id],
      ['text' => '🗑 حذف', 'callback_data' => 'habit_delete_' . $habit_id]
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '📋 بازگشت به لیست', 'callback_data' => 'habit_list_1']
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showHabitsStats($chat_id, $user_id, $message_id)
{
   global $pdo;

   // آمار کلی
   $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_habits,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_habits,
        SUM(total_completed) as total_completions
        FROM habits WHERE user_id = :user_id");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $stats = $stmt->fetch();

   // آمار هفته گذشته
   $stmt = $pdo->prepare("SELECT COUNT(*) as week_completions
        FROM habit_logs hl
        JOIN habits h ON hl.habit_id = h.id
        WHERE h.user_id = :user_id AND hl.completed_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $week_stats = $stmt->fetch();

   // آمار ماه گذشته
   $stmt = $pdo->prepare("SELECT COUNT(*) as month_completions
        FROM habit_logs hl
        JOIN habits h ON hl.habit_id = h.id
        WHERE h.user_id = :user_id AND hl.completed_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $month_stats = $stmt->fetch();

   // بهترین عادت‌ها (بیشترین انجام)
   $stmt = $pdo->prepare("SELECT name, total_completed FROM habits 
        WHERE user_id = :user_id AND is_active = 1 AND total_completed > 0
        ORDER BY total_completed DESC LIMIT 3");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $top_habits = $stmt->fetchAll();

   // عادت‌های امروز
   $stmt = $pdo->prepare("SELECT COUNT(*) as today_completed
        FROM habit_logs hl
        JOIN habits h ON hl.habit_id = h.id
        WHERE h.user_id = :user_id AND hl.completed_date = CURDATE()");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $today_stats = $stmt->fetch();

   $text = "📊 <b>آمار عادت‌ها</b>\n";
   $text .= "تاریخ: " . jdate('Y/m/d') . "\n\n";

   // آمار کلی
   $total_habits = $stats['total_habits'] ?? 0;
   $active_habits = $stats['active_habits'] ?? 0;
   $total_completions = $stats['total_completions'] ?? 0;

   $text .= "🎯 <b>آمار کلی:</b>\n";
   $text .= "📋 کل عادت‌ها: $total_habits\n";
   $text .= "🟢 عادت‌های فعال: $active_habits\n";
   $text .= "📈 کل انجام شده: $total_completions بار\n\n";

   // آمار زمانی
   $week_completions = $week_stats['week_completions'] ?? 0;
   $month_completions = $month_stats['month_completions'] ?? 0;
   $today_completed = $today_stats['today_completed'] ?? 0;

   $text .= "📅 <b>آمار زمانی:</b>\n";
   $text .= "📊 امروز: $today_completed انجام\n";
   $text .= "📊 این هفته: $week_completions انجام\n";
   $text .= "📈 این ماه: $month_completions انجام\n\n";

   // بهترین عادت‌ها
   if (!empty($top_habits)) {
      $text .= "🏆 <b>بهترین عادت‌ها:</b>\n";
      foreach ($top_habits as $index => $habit) {
         $rank = $index + 1;
         $text .= "$rank. " . htmlspecialchars($habit['name']) . " - {$habit['total_completed']} بار\n";
      }
      $text .= "\n";
   }

   // توصیه‌ها
   $text .= "💡 <b>توصیه‌ها:</b>\n";
   if ($active_habits == 0) {
      $text .= "• ابتدا چند عادت ساده اضافه کنید\n";
   } elseif ($today_completed == 0 && date('H') > 10) {
      $text .= "• امروز هنوز شروع نکرده‌اید!\n";
   } elseif ($week_completions < $active_habits * 5) {
      $text .= "• این هفته بیشتر تلاش کنید\n";
   } else {
      $text .= "• عملکرد شما عالی است! ادامه دهید\n";
   }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ عادت‌های امروز', 'callback_data' => 'habit_today']
         ],
         [
            ['text' => '🔄 بروزرسانی', 'callback_data' => 'habit_stats'],
         ],
         [
            ['text' => '🔙 بازگشت', 'callback_data' => 'habits']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function startAddHabit($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'habit_adding_name']);

   $text = "➕ <b>افزودن عادت جدید</b>\n\n";
   $text .= "نام عادت را وارد کنید:\n\n";
   $text .= "مثال‌ها:\n";
   $text .= "• ورزش روزانه\n";
   $text .= "• مطالعه کتاب\n";
   $text .= "• نوشیدن آب\n";
   $text .= "• مدیتیشن";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'habits']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function completeHabit($chat_id, $user_id, $habit_id, $message_id)
{
   global $pdo, $callback_query_id;

   // بررسی وجود عادت
   $stmt = $pdo->prepare("SELECT * FROM habits WHERE id = :id AND user_id = :user_id AND is_active = 1");
   $stmt->bindValue(':id', $habit_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $habit = $stmt->fetch();

   if (!$habit) {
      answerCallbackQuery($callback_query_id, "❌ عادت یافت نشد");
      return;
   }

   // بررسی انجام قبلی امروز
   $stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE habit_id = :habit_id AND completed_date = CURDATE()");
   $stmt->bindValue(':habit_id', $habit_id, PDO::PARAM_INT);
   $stmt->execute();
   $already_completed = $stmt->fetchColumn() > 0;

   if ($already_completed) {
      answerCallbackQuery($callback_query_id, "✅ قبلاً انجام داده‌اید");
      return;
   }

   try {
      $pdo->beginTransaction();

      // ثبت لاگ
      $stmt = $pdo->prepare("INSERT INTO habit_logs (habit_id, user_id, completed_date, created_at) VALUES (:habit_id, :user_id, CURDATE(), NOW())");
      $stmt->bindValue(':habit_id', $habit_id, PDO::PARAM_INT);
      $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
      $stmt->execute();

      // بروزرسانی شمارنده
      $stmt = $pdo->prepare("UPDATE habits SET total_completed = total_completed + 1 WHERE id = :id");
      $stmt->bindValue(':id', $habit_id, PDO::PARAM_INT);
      $stmt->execute();

      $pdo->commit();

      answerCallbackQuery($callback_query_id, "✅ عادت انجام شد!");

      // بازگشت به صفحه عادت‌های امروز
      showTodayHabits($chat_id, $user_id, $message_id);
   } catch (Exception $e) {
      $pdo->rollBack();
      answerCallbackQuery($callback_query_id, "❌ خطا در ثبت");
   }
}

function uncompleteHabit($chat_id, $user_id, $habit_id, $message_id)
{
   global $pdo, $callback_query_id;

   try {
      $pdo->beginTransaction();

      // حذف لاگ امروز
      $stmt = $pdo->prepare("DELETE FROM habit_logs WHERE habit_id = :habit_id AND user_id = :user_id AND completed_date = CURDATE()");
      $stmt->bindValue(':habit_id', $habit_id, PDO::PARAM_INT);
      $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
      $deleted = $stmt->execute() && $stmt->rowCount() > 0;

      if ($deleted) {
         // کاهش شمارنده
         $stmt = $pdo->prepare("UPDATE habits SET total_completed = GREATEST(0, total_completed - 1) WHERE id = :id");
         $stmt->bindValue(':id', $habit_id, PDO::PARAM_INT);
         $stmt->execute();

         $pdo->commit();
         answerCallbackQuery($callback_query_id, "❌ انجام لغو شد");
         showTodayHabits($chat_id, $user_id, $message_id);
      } else {
         $pdo->rollBack();
         answerCallbackQuery($callback_query_id, "❌ خطا در لغو");
      }
   } catch (Exception $e) {
      $pdo->rollBack();
      answerCallbackQuery($callback_query_id, "❌ خطا در لغو");
   }
}

function toggleHabitStatus($chat_id, $user_id, $habit_id, $message_id)
{
   global $pdo, $callback_query_id;

   $stmt = $pdo->prepare("UPDATE habits SET is_active = NOT is_active WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $habit_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

   if ($stmt->execute()) {
      answerCallbackQuery($callback_query_id, "✅ وضعیت تغییر کرد");
      viewHabitDetails($chat_id, $user_id, $message_id, $habit_id);
   } else {
      answerCallbackQuery($callback_query_id, "❌ خطا در تغییر وضعیت");
   }
}

function confirmDeleteHabit($chat_id, $user_id, $message_id, $habit_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT name FROM habits WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $habit_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $habit = $stmt->fetch();

   if (!$habit) {
      editMessage($chat_id, $message_id, "❌ عادت یافت نشد.");
      return;
   }

   $text = "🗑 <b>حذف عادت</b>\n\n";
   $text .= "آیا مطمئن هستید که می‌خواهید این عادت را حذف کنید؟\n\n";
   $text .= "📋 <b>نام:</b> " . htmlspecialchars($habit['name']) . "\n\n";
   $text .= "⚠️ این عمل قابل بازگشت نیست و تمام آمار مربوطه حذف خواهد شد!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، حذف کن', 'callback_data' => 'habit_confirmdelete_' . $habit_id],
            ['text' => '❌ خیر', 'callback_data' => 'habit_view_' . $habit_id]
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function deleteHabit($chat_id, $user_id, $message_id, $habit_id)
{
   global $pdo, $callback_query_id;

   try {
      $pdo->beginTransaction();

      // حذف لاگ‌ها
      $stmt = $pdo->prepare("DELETE FROM habit_logs WHERE habit_id = :habit_id");
      $stmt->bindValue(':habit_id', $habit_id, PDO::PARAM_INT);
      $stmt->execute();

      // حذف عادت
      $stmt = $pdo->prepare("DELETE FROM habits WHERE id = :id AND user_id = :user_id");
      $stmt->bindValue(':id', $habit_id, PDO::PARAM_INT);
      $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
      $stmt->execute();

      $pdo->commit();

      answerCallbackQuery($callback_query_id, "🗑 عادت حذف شد");
      showHabitsList($chat_id, $user_id, $message_id, 1);
   } catch (Exception $e) {
      $pdo->rollBack();
      answerCallbackQuery($callback_query_id, "❌ خطا در حذف");
   }
}

function processHabitForm($chat_id, $user_id, $text, $step)
{
   switch ($step) {
      case 'habit_adding_name':
         if (empty(trim($text))) {
            sendMessage($chat_id, "❌ نام عادت نمی‌تواند خالی باشد.");
            return;
         }

         saveHabit($chat_id, $user_id, ['name' => trim($text)]);
         break;

      default:
         updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);
         sendMessage($chat_id, "❌ مرحله نامعتبر. لطفاً دوباره تلاش کنید.");
         break;
   }
}

function saveHabit($chat_id, $user_id, $data)
{
   global $pdo;

   $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, created_at) VALUES (:user_id, :name, NOW())");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);

   if ($stmt->execute()) {
      $habit_id = $pdo->lastInsertId();
      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $response = "✅ <b>عادت با موفقیت اضافه شد!</b>\n\n";
      $response .= "📋 نام: " . htmlspecialchars($data['name']) . "\n";
      $response .= "\n💡 از امروز می‌توانید این عادت را انجام دهید!";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '👁 مشاهده عادت', 'callback_data' => 'habit_view_' . $habit_id],
               ['text' => '✅ انجام دادم', 'callback_data' => 'habit_complete_' . $habit_id]
            ],
            [
               ['text' => '📋 لیست عادت‌ها', 'callback_data' => 'habit_list_1'],
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در ثبت عادت. لطفاً دوباره تلاش کنید.");
   }
}
