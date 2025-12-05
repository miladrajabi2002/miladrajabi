<?php

function showGoalsMenu($chat_id, $user_id, $message_id = null)
{
   global $pdo;

   // دریافت آمار کلی
   $stats = getGoalStats($user_id);

   $text = "🎯 <b>هدف‌گذاری و انگیزه</b>\n\n";

   // نمایش آمار هوشمند
   if ($stats['total_goals'] > 0) {
      $text .= "📊 <b>وضعیت کلی شما:</b>\n";
      $text .= generateProgressBar($stats['avg_progress']) . " " . $stats['avg_progress'] . "%\n\n";

      $text .= "📈 <b>آمار شما:</b>\n";
      $text .= "• اهداف فعال: " . $stats['active_goals'] . "\n";
      $text .= "• اهداف تکمیل شده: " . $stats['completed_goals'] . "\n";

      // پیام انگیزشی بر اساس عملکرد
      $text .= "\n" . getSmartMotivation($stats) . "\n";
   } else {
      $text .= "💡 هنوز هدفی ثبت نکرده‌اید.\n";
      $text .= "با تعیین اهداف، مسیر موفقیت خود را روشن کنید!\n";
   }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '➕ هدف جدید', 'callback_data' => 'goal_new'],
            ['text' => '📋 اهداف من', 'callback_data' => 'goal_list_1']
         ],
         [
            ['text' => '📈 گزارش پیشرفت', 'callback_data' => 'goal_progress'],
            ['text' => '💭 الهام روزانه', 'callback_data' => 'goal_inspiration']
         ],
         [
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'goal_settings']
         ],
         [
            ['text' => '🔙 بازگشت', 'callback_data' => 'back_main']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

// هندل کردن callback ها
function handleGoalCallback($chat_id, $user_id, $data, $message_id)
{
   global $pdo;

   if ($data == 'goal_new') {
      createNewGoal($chat_id, $user_id, $message_id);
   } elseif (strpos($data, 'goal_list_') === 0) {
      $page = intval(str_replace('goal_list_', '', $data));
      showGoalsList($chat_id, $user_id, $message_id, $page);
   } elseif (strpos($data, 'goal_view_') === 0) {
      $goal_id = str_replace('goal_view_', '', $data);
      viewGoal($chat_id, $user_id, $goal_id, $message_id);
   } elseif ($data == 'goal_progress') {
      showGoalProgress($chat_id, $user_id, $message_id);
   } elseif ($data == 'goal_inspiration') {
      showDailyInspiration($chat_id, $user_id, $message_id);
   } elseif ($data == 'goal_settings') {
      showGoalSettings($chat_id, $user_id, $message_id);
   } elseif (strpos($data, 'goal_update_') === 0) {
      $goal_id = str_replace('goal_update_', '', $data);
      updateGoalProgress($chat_id, $user_id, $goal_id, $message_id);
   } elseif (strpos($data, 'goal_complete_') === 0) {
      $goal_id = str_replace('goal_complete_', '', $data);

      $stmt = $pdo->prepare("UPDATE goals SET is_completed = 1, progress = 100, updated_at = NOW() WHERE id = :id");
      $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
      
      $stmt->execute();

      showGoalsList($chat_id, $user_id, $message_id);
   } elseif (strpos($data, 'goal_delete_') === 0) {
      $goal_id = str_replace('goal_delete_', '', $data);

      $text = "⚠️ آیا از حذف این هدف مطمئن هستید؟";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '✅ بله', 'callback_data' => 'goal_confirmdelete_' . $goal_id],
               ['text' => '❌ خیر', 'callback_data' => 'goal_view_' . $goal_id]
            ]
         ]
      ];

      editMessage($chat_id, $message_id, $text, $keyboard);
   } elseif (strpos($data, 'goal_confirmdelete_') === 0) {
      $goal_id = str_replace('goal_confirmdelete_', '', $data);

      $stmt = $pdo->prepare("DELETE FROM goals WHERE id = :id");
      $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
      
      $stmt->execute();

      showGoalsList($chat_id, $user_id, $message_id);
   } elseif ($data == 'goal_toggle_motivation') {
      $stmt = $pdo->prepare("UPDATE user_settings SET daily_motivation = NOT daily_motivation");
      
      $stmt->execute();

      showGoalSettings($chat_id, $user_id, $message_id);
   } elseif ($data == 'goal_skip_description') {
      $stmt = $pdo->prepare("SELECT temp_data FROM users");
      
      $stmt->execute();
      $temp_data = json_decode($stmt->fetchColumn(), true);

      updateUser($user_id, [
         'step' => 'goal_date',
         'temp_data' => json_encode($temp_data)
      ]);

      $response = "📅 <b>تاریخ هدف:</b>\n";
      $response .= "مثال: 3 ماه، 6 ماه، 1 سال\n";
      $response .= "یا تاریخ دقیق: 1404/12/29";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '1 ماه', 'callback_data' => 'goal_date_1month'],
               ['text' => '3 ماه', 'callback_data' => 'goal_date_3months']
            ],
            [
               ['text' => '6 ماه', 'callback_data' => 'goal_date_6months'],
               ['text' => '1 سال', 'callback_data' => 'goal_date_1year']
            ],
            [['text' => '⏩ بدون تاریخ', 'callback_data' => 'goal_skip_date']],
            [['text' => '🔙 انصراف', 'callback_data' => 'goals']]
         ]
      ];

      editMessage($chat_id, $message_id, $response, $keyboard);
   } elseif ($data == 'goal_skip_date') {
      $stmt = $pdo->prepare("SELECT temp_data FROM users");
      
      $stmt->execute();
      $temp_data = json_decode($stmt->fetchColumn(), true);

      // ذخیره هدف بدون تاریخ
      $stmt = $pdo->prepare("
            INSERT INTO goals (title, description, created_at)
            VALUES (:title, :description, NOW())
        ");
      
      $stmt->bindValue(':title', $temp_data['title'], PDO::PARAM_STR);
      $stmt->bindValue(':description', $temp_data['description'] ?? null, PDO::PARAM_STR);
      $stmt->execute();

      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $text = "✅ هدف با موفقیت ایجاد شد!\n\n";
      $text .= "🎯 " . htmlspecialchars($temp_data['title']);

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📋 مشاهده اهداف', 'callback_data' => 'goal_list_1'],
               ['text' => '➕ هدف جدید', 'callback_data' => 'goal_new']
            ],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      editMessage($chat_id, $message_id, $text, $keyboard);
   } elseif (strpos($data, 'goal_date_') === 0) {
      $period = str_replace('goal_date_', '', $data);

      $stmt = $pdo->prepare("SELECT temp_data FROM users");
      
      $stmt->execute();
      $temp_data = json_decode($stmt->fetchColumn(), true);

      // تعیین تاریخ بر اساس انتخاب
      $target_date = null;
      switch ($period) {
         case '1month':
            $target_date = date('Y-m-d', strtotime('+1 month'));
            break;
         case '3months':
            $target_date = date('Y-m-d', strtotime('+3 months'));
            break;
         case '6months':
            $target_date = date('Y-m-d', strtotime('+6 months'));
            break;
         case '1year':
            $target_date = date('Y-m-d', strtotime('+1 year'));
            break;
      }

      // ذخیره هدف
      $stmt = $pdo->prepare("
            INSERT INTO goals (title, description, target_date, created_at)
            VALUES (:title, :description, :target_date, NOW())
        ");
      
      $stmt->bindValue(':title',  $temp_data['title'], PDO::PARAM_STR);
      $stmt->bindValue(':description', $temp_data['description'] ?? null, PDO::PARAM_STR);
      $stmt->bindValue(':target_date', $target_date, PDO::PARAM_STR);
      $stmt->execute();

      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $text = "✅ هدف با موفقیت ایجاد شد!\n\n";
      $text .= "🎯 " . htmlspecialchars($temp_data['title']);

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📋 مشاهده اهداف', 'callback_data' => 'goal_list_1'],
               ['text' => '➕ هدف جدید', 'callback_data' => 'goal_new']
            ],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      editMessage($chat_id, $message_id, $text, $keyboard);
   } elseif (strpos($data, 'goal_set_progress_') === 0) {
      // goal_set_progress_GOALID_PERCENTAGE
      $parts = explode('_', $data);
      $goal_id = $parts[3];
      $progress = $parts[4];

      $stmt = $pdo->prepare("UPDATE goals SET progress = :progress, updated_at = NOW() WHERE id = :id");
      $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
      
      $stmt->bindValue(':progress', $progress, PDO::PARAM_INT);
      $stmt->execute();

      if ($percentage == 100) {
         $stmt = $pdo->prepare("UPDATE goals SET is_completed = 1 WHERE id = :id");
         $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
         
         $stmt->execute();
      }

      viewGoal($chat_id, $user_id, $goal_id, $message_id);
   } elseif (strpos($data, 'goal_quick_progress_') === 0) {
      // goal_quick_progress_GOALID_AMOUNT
      $parts = explode('_', $data);
      $goal_id = $parts[3];
      $amount = $parts[4];

      // دریافت پیشرفت فعلی
      $stmt = $pdo->prepare("SELECT progress FROM goals WHERE id = :id");
      $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
      
      $stmt->execute();
      $current_progress = $stmt->fetchColumn();

      $new_progress = min(100, $current_progress + $amount);

      $stmt = $pdo->prepare("UPDATE goals SET progress = :progress, updated_at = NOW() WHERE id = :id");
      $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
      
      $stmt->bindValue(':progress', $new_progress, PDO::PARAM_INT);
      $stmt->execute();

      if ($new_progress == 100) {
         $stmt = $pdo->prepare("UPDATE goals SET is_completed = 1 WHERE id = :id");
         $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
         
         $stmt->execute();
      }

      viewGoal($chat_id, $user_id, $goal_id, $message_id);

      updateUser($user_id, ['step' => 'completed']);
   }
}

function createNewGoal($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'goal_title']);

   $text = "🎯 <b>ایجاد هدف جدید</b>\n\n";
   $text .= "لطفاً عنوان هدف خود را بنویسید:";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '🔙 انصراف', 'callback_data' => 'goals']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showGoalsList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $per_page = 5;
   $offset = ($page - 1) * $per_page;

   // دریافت اهداف - حل مشکل SQL
   $stmt = $pdo->prepare("
        SELECT * FROM goals 
       
        ORDER BY is_completed ASC, created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
   
   $stmt->execute();
   $goals = $stmt->fetchAll();

   // تعداد کل
   $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals");
   
   $stmt->execute();
   $total = $stmt->fetchColumn();
   $total_pages = ceil($total / $per_page);

   if (empty($goals)) {
      $text = "📋 <b>لیست اهداف</b>\n\n";
      $text .= "شما هنوز هدفی ثبت نکرده‌اید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ ایجاد اولین هدف', 'callback_data' => 'goal_new']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'goals']]
         ]
      ];
   } else {
      $text = "📋 <b>لیست اهداف شما</b>\n\n";

      foreach ($goals as $goal) {
         $icon = $goal['is_completed'] ? '✅' : '🎯';
         $progress_bar = generateProgressBar($goal['progress']);

         $text .= "$icon <b>" . htmlspecialchars($goal['title']) . "</b>\n";
         $text .= $progress_bar . " " . $goal['progress'] . "%\n";

         if ($goal['target_date']) {
            $days_left = ceil((strtotime($goal['target_date']) - time()) / 86400);
            if ($days_left > 0 && !$goal['is_completed']) {
               $text .= "⏰ " . $days_left . " روز مانده\n";
            } elseif (!$goal['is_completed']) {
               $text .= "⚠️ سررسید گذشته\n";
            }
         }
         $text .= "\n";
      }

      $keyboard = ['inline_keyboard' => []];

      // دکمه‌های هدف
      foreach ($goals as $goal) {
         $keyboard['inline_keyboard'][] = [
            ['text' => '👁 ' . mb_substr($goal['title'], 0, 20) . '...', 'callback_data' => 'goal_view_' . $goal['id']]
         ];
      }

      // صفحه‌بندی
      $nav_buttons = [];
      if ($page > 1) {
         $nav_buttons[] = ['text' => '◀️ قبلی', 'callback_data' => 'goal_list_' . ($page - 1)];
      }
      if ($page < $total_pages) {
         $nav_buttons[] = ['text' => 'بعدی ▶️', 'callback_data' => 'goal_list_' . ($page + 1)];
      }
      if (!empty($nav_buttons)) {
         $keyboard['inline_keyboard'][] = $nav_buttons;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '➕ هدف جدید', 'callback_data' => 'goal_new']
      ];

      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'goals']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function viewGoal($chat_id, $user_id, $goal_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM goals WHERE id = :id");
   $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
   
   $stmt->execute();
   $goal = $stmt->fetch();

   if (!$goal) {
      editMessage($chat_id, $message_id, "❌ هدف یافت نشد.");
      return;
   }

   $text = "🎯 <b>" . htmlspecialchars($goal['title']) . "</b>\n\n";

   if ($goal['description']) {
      $text .= "📝 <b>توضیحات:</b>\n" . htmlspecialchars($goal['description']) . "\n\n";
   }

   // نمودار پیشرفت
   $text .= "📈 <b>پیشرفت:</b>\n";
   $text .= generateProgressBar($goal['progress']) . " " . $goal['progress'] . "%\n\n";

   // اطلاعات زمانی
   $text .= "📅 <b>تاریخ ایجاد:</b> " . jdate('Y/m/d', strtotime($goal['created_at'])) . "\n";

   if ($goal['target_date']) {
      $text .= "🎯 <b>تاریخ هدف:</b> " . jdate('Y/m/d', strtotime($goal['target_date'])) . "\n";

      if (!$goal['is_completed']) {
         $days_left = ceil((strtotime($goal['target_date']) - time()) / 86400);
         if ($days_left > 0) {
            $text .= "⏰ <b>زمان باقیمانده:</b> " . $days_left . " روز\n";
         } else {
            $text .= "⚠️ <b>سررسید گذشته!</b>\n";
         }
      }
   }

   if ($goal['is_completed']) {
      $text .= "\n✅ <b>این هدف تکمیل شده است!</b>";
   }

   $keyboard = ['inline_keyboard' => []];

   if (!$goal['is_completed']) {
      $keyboard['inline_keyboard'][] = [
         ['text' => '📈 بروزرسانی پیشرفت', 'callback_data' => 'goal_update_' . $goal_id],
         ['text' => '✅ تکمیل هدف', 'callback_data' => 'goal_complete_' . $goal_id]
      ];
   }

   $keyboard['inline_keyboard'][] = [
      ['text' => '🗑 حذف', 'callback_data' => 'goal_delete_' . $goal_id],
      ['text' => '📋 لیست اهداف', 'callback_data' => 'goal_list_1']
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🔙 بازگشت', 'callback_data' => 'goals']
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function updateGoalProgress($chat_id, $user_id, $goal_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT title, progress FROM goals WHERE id = :id");
   $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
   
   $stmt->execute();
   $goal = $stmt->fetch();

   if (!$goal) {
      editMessage($chat_id, $message_id, "❌ هدف یافت نشد.");
      return;
   }

   updateUser($user_id, ['step' => 'goal_update_progress_' . $goal_id]);

   $text = "📈 <b>بروزرسانی پیشرفت</b>\n\n";
   $text .= "🎯 هدف: <b>" . htmlspecialchars($goal['title']) . "</b>\n";
   $text .= "📊 پیشرفت فعلی: " . generateProgressBar($goal['progress']) . " " . $goal['progress'] . "%\n\n";
   $text .= "درصد جدید پیشرفت را وارد کنید (0-100):";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '+10%', 'callback_data' => 'goal_quick_progress_' . $goal_id . '_10'],
            ['text' => '+25%', 'callback_data' => 'goal_quick_progress_' . $goal_id . '_25'],
            ['text' => '+50%', 'callback_data' => 'goal_quick_progress_' . $goal_id . '_50']
         ],
         [
            ['text' => '25%', 'callback_data' => 'goal_set_progress_' . $goal_id . '_25'],
            ['text' => '50%', 'callback_data' => 'goal_set_progress_' . $goal_id . '_50'],
            ['text' => '75%', 'callback_data' => 'goal_set_progress_' . $goal_id . '_75'],
            ['text' => '100%', 'callback_data' => 'goal_set_progress_' . $goal_id . '_100']
         ],
         [['text' => '🔙 انصراف', 'callback_data' => 'goal_view_' . $goal_id]]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showGoalProgress($chat_id, $user_id, $message_id)
{
   global $pdo;

   $stats = getGoalStats($user_id);

   $text = "📈 <b>گزارش پیشرفت اهداف</b>\n\n";

   if ($stats['total_goals'] == 0) {
      $text .= "شما هنوز هدفی ثبت نکرده‌اید.";
      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ ایجاد اولین هدف', 'callback_data' => 'goal_new']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'goals']]
         ]
      ];
   } else {
      // نمودار کلی
      $text .= "📊 <b>پیشرفت کلی:</b>\n";
      $text .= generateProgressBar($stats['avg_progress']) . " " . $stats['avg_progress'] . "%\n\n";

      // آمار تفصیلی
      $text .= "📋 <b>آمار کلی:</b>\n";
      $text .= "• کل اهداف: " . $stats['total_goals'] . "\n";
      $text .= "• تکمیل شده: " . $stats['completed_goals'] . "\n";
      $text .= "• در حال انجام: " . $stats['active_goals'] . "\n";
      $text .= "• نرخ موفقیت: " . $stats['success_rate'] . "%\n\n";

      // بهترین عملکرد
      if ($stats['best_goal']) {
         $text .= "🌟 <b>بهترین پیشرفت:</b>\n";
         $text .= htmlspecialchars($stats['best_goal']['title']) . " (" . $stats['best_goal']['progress'] . "%)\n\n";
      }

      // توصیه هوشمند
      $text .= "💡 <b>توصیه:</b>\n";
      $text .= getSmartRecommendation($stats);

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '📋 مشاهده اهداف', 'callback_data' => 'goal_list_1']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'goals']]
         ]
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showDailyInspiration($chat_id, $user_id, $message_id)
{
   $quotes = [
      [
         'quote' => "موفقیت یعنی از شکست به شکست رفتن بدون از دست دادن اشتیاق.",
         'author' => "وینستون چرچیل"
      ],
      [
         'quote' => "تنها راه انجام کار بزرگ، عشق به کاری است که انجام می‌دهید.",
         'author' => "استیو جابز"
      ],
      [
         'quote' => "آینده به کسانی تعلق دارد که به زیبایی رؤیاهایشان ایمان دارند.",
         'author' => "النور روزولت"
      ],
      [
         'quote' => "بزرگترین افتخار در زندگی، هرگز نیفتادن نیست، بلکه برخاستن پس از هر سقوط است.",
         'author' => "نلسون ماندلا"
      ],
      [
         'quote' => "تفاوت بین ممکن و غیرممکن در عزم و اراده شماست.",
         'author' => "تامی لاسوردا"
      ]
   ];

   $tips = [
      "💡 هر روز 1% بهتر شوید. بعد از یک سال، 37 برابر بهتر خواهید بود!",
      "💡 قانون 2 دقیقه: اگر کاری کمتر از 2 دقیقه طول می‌کشد، همین الان انجامش دهید.",
      "💡 اهداف خود را بنویسید. نوشتن اهداف احتمال دستیابی را 42% افزایش می‌دهد.",
      "💡 هر شب 3 دستاورد روزتان را یادداشت کنید، هر چند کوچک.",
      "💡 استراحت هم بخشی از پیشرفت است. به خودتان سخت نگیرید."
   ];

   $selected_quote = $quotes[array_rand($quotes)];
   $selected_tip = $tips[array_rand($tips)];

   $text = "💭 <b>الهام روزانه</b>\n\n";
   $text .= "📜 «" . $selected_quote['quote'] . "»\n";
   $text .= "— " . $selected_quote['author'] . "\n\n";
   $text .= $selected_tip;

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '🔄 پیام جدید', 'callback_data' => 'goal_inspiration']
         ],
         [
            ['text' => '📤 اشتراک', 'callback_data' => 'goal_share_inspiration']
         ],
         [
            ['text' => '🔙 بازگشت', 'callback_data' => 'goals']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showGoalSettings($chat_id, $user_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM user_settings");
   
   $stmt->execute();
   $settings = $stmt->fetch();

   $text = "⚙️ <b>تنظیمات هدف‌گذاری</b>\n\n";

   $status = $settings['daily_motivation'] ? '✅ فعال' : '❌ غیرفعال';
   $text .= "📬 <b>پیام انگیزشی روزانه:</b> $status\n";

   if ($settings['daily_motivation']) {
      $time = substr($settings['motivation_time'], 0, 5);
      $text .= "⏰ <b>زمان ارسال:</b> $time\n";
   }

   $text .= "\n💡 پیام‌های انگیزشی به شما کمک می‌کنند تا:\n";
   $text .= "• انگیزه خود را حفظ کنید\n";
   $text .= "• روی اهدافتان متمرکز بمانید\n";
   $text .= "• با انرژی مثبت روز را شروع کنید";

   $keyboard = ['inline_keyboard' => []];

   if ($settings['daily_motivation']) {
      $keyboard['inline_keyboard'][] = [
         ['text' => '❌ غیرفعال کردن', 'callback_data' => 'goal_toggle_motivation']
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '⏰ تغییر زمان', 'callback_data' => 'goal_motivation_time']
      ];
   } else {
      $keyboard['inline_keyboard'][] = [
         ['text' => '✅ فعال کردن', 'callback_data' => 'goal_toggle_motivation']
      ];
   }

   $keyboard['inline_keyboard'][] = [
      ['text' => '💬 نمونه پیام انگیزشی', 'callback_data' => 'goal_motivation_sample']
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🔙 بازگشت', 'callback_data' => 'goals']
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

// توابع کمکی
function getGoalStats($user_id)
{
   global $pdo;

   $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_goals,
            COUNT(CASE WHEN is_completed = 1 THEN 1 END) as completed_goals,
            COUNT(CASE WHEN is_completed = 0 THEN 1 END) as active_goals,
            COALESCE(AVG(progress), 0) as avg_progress
        FROM goals 
       
    ");
   
   $stmt->execute();
   $stats = $stmt->fetch();

   // محاسبه نرخ موفقیت
   $stats['success_rate'] = $stats['total_goals'] > 0
      ? round(($stats['completed_goals'] / $stats['total_goals']) * 100)
      : 0;

   $stats['avg_progress'] = round($stats['avg_progress']);

   // بهترین هدف
   $stmt = $pdo->prepare("
        SELECT title, progress 
        FROM goals 
        WHERE is_completed = 0 
        ORDER BY progress DESC 
        LIMIT 1
    ");
   
   $stmt->execute();
   $stats['best_goal'] = $stmt->fetch();

   return $stats;
}

function generateProgressBar($progress)
{
   $filled = round($progress / 10);
   $empty = 10 - $filled;

   $bar = '';
   for ($i = 0; $i < $filled; $i++) {
      $bar .= '🟩';
   }
   for ($i = 0; $i < $empty; $i++) {
      $bar .= '⬜';
   }

   return $bar;
}

function getSmartMotivation($stats)
{
   $motivations = [];

   if ($stats['avg_progress'] >= 80) {
      $motivations[] = "🔥 عالی! تقریباً به همه اهدافتان رسیده‌اید!";
      $motivations[] = "⭐ شما در آستانه موفقیت بزرگی هستید!";
      $motivations[] = "🏆 چند قدم تا قله! ادامه دهید!";
   } elseif ($stats['avg_progress'] >= 60) {
      $motivations[] = "💪 بیش از نیمی از راه را طی کرده‌اید!";
      $motivations[] = "🌟 پیشرفت شما چشمگیر است!";
      $motivations[] = "🎯 در مسیر درست قرار دارید!";
   } elseif ($stats['avg_progress'] >= 40) {
      $motivations[] = "👍 شروع خوبی داشته‌اید، ادامه دهید!";
      $motivations[] = "🌱 هر قدم کوچک، یک پیروزی است!";
      $motivations[] = "💫 مسیر موفقیت از همین‌جا شروع می‌شود!";
   } elseif ($stats['avg_progress'] >= 20) {
      $motivations[] = "🚀 هر سفر بزرگ با یک قدم آغاز می‌شود!";
      $motivations[] = "🌈 تمرکز روی پیشرفت، نه کمال!";
      $motivations[] = "⚡ شما قوی‌تر از آن هستید که فکر می‌کنید!";
   } else {
      $motivations[] = "🌟 امروز بهترین روز برای شروع است!";
      $motivations[] = "💎 پتانسیل شما بی‌نهایت است!";
      $motivations[] = "🔑 کلید موفقیت در دستان شماست!";
   }

   if ($stats['success_rate'] > 70) {
      $motivations[] = "🏅 با نرخ موفقیت " . $stats['success_rate'] . "% شما یک قهرمان واقعی هستید!";
   }

   return "💭 " . $motivations[array_rand($motivations)];
}

function getSmartRecommendation($stats)
{
   if ($stats['active_goals'] == 0 && $stats['completed_goals'] > 0) {
      return "زمان تعیین اهداف جدید است!";
   } elseif ($stats['avg_progress'] < 30) {
      return "روی اهداف کوچک‌تر تمرکز کنید تا انگیزه بگیرید.";
   } elseif ($stats['active_goals'] > 5) {
      return "تعداد اهداف فعال زیاد است، اولویت‌بندی کنید.";
   } elseif ($stats['success_rate'] > 80) {
      return "عملکرد عالی! می‌توانید اهداف چالشی‌تر تعیین کنید.";
   } else {
      return "هر روز حداقل یک قدم کوچک برای اهدافتان بردارید.";
   }
}

// پردازش ورودی‌ها
function processGoalForm($chat_id, $user_id, $text, $step)
{
   global $pdo;

   if ($step == 'goal_title') {
      $temp_data = json_encode([
         'title' => $text
      ]);

      updateUser($user_id, [
         'step' => 'goal_description',
         'temp_data' => $temp_data
      ]);

      $response = "✅ عنوان ذخیره شد.\n\n";
      $response .= "📝 <b>توضیحات هدف:</b>\n";
      $response .= "می‌توانید توضیحات اضافی بنویسید:";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '⏩ رد کردن', 'callback_data' => 'goal_skip_description']],
            [['text' => '🔙 انصراف', 'callback_data' => 'goals']]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } elseif ($step == 'goal_description') {
      $stmt = $pdo->prepare("SELECT temp_data FROM users");
      
      $stmt->execute();
      $temp_data = json_decode($stmt->fetchColumn(), true);

      $temp_data['description'] = $text;

      updateUser($user_id, [
         'step' => 'goal_date',
         'temp_data' => json_encode($temp_data)
      ]);

      $response = "📅 <b>تاریخ هدف:</b>\n";
      $response .= "مثال: 3 ماه، 6 ماه، 1 سال\n";
      $response .= "یا تاریخ دقیق: 1404/12/29";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '1 ماه', 'callback_data' => 'goal_date_1month'],
               ['text' => '3 ماه', 'callback_data' => 'goal_date_3months']
            ],
            [
               ['text' => '6 ماه', 'callback_data' => 'goal_date_6months'],
               ['text' => '1 سال', 'callback_data' => 'goal_date_1year']
            ],
            [['text' => '⏩ بدون تاریخ', 'callback_data' => 'goal_skip_date']],
            [['text' => '🔙 انصراف', 'callback_data' => 'goals']]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } elseif ($step == 'goal_date') {
      $stmt = $pdo->prepare("SELECT temp_data FROM users");
      
      $stmt->execute();
      $temp_data = json_decode($stmt->fetchColumn(), true);

      $target_date = parseGoalDate($text);

      // ذخیره هدف
      $stmt = $pdo->prepare("
            INSERT INTO goals (title, description, target_date, created_at)
            VALUES (:title, :description, :target_date, NOW())
        ");
      
      $stmt->bindValue(':title', $temp_data['title'], PDO::PARAM_STR);
      $stmt->bindValue(':description', $temp_data['description'] ?? null, PDO::PARAM_STR);
      $stmt->bindValue(':target_date', $target_date, PDO::PARAM_STR);
      $stmt->execute();

      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $text = "✅ هدف با موفقیت ایجاد شد!\n\n";
      $text .= "🎯 " . htmlspecialchars($temp_data['title']);

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📋 مشاهده اهداف', 'callback_data' => 'goal_list_1'],
               ['text' => '➕ هدف جدید', 'callback_data' => 'goal_new']
            ],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage($chat_id, $text, $keyboard);
   } elseif (strpos($step, 'goal_update_progress_') === 0) {
      $goal_id = str_replace('goal_update_progress_', '', $step);

      $text = cleanNumber($text);
      if (!is_numeric($text) || $text < 0 || $text > 100) {
         sendMessage($chat_id, "❌ لطفاً عددی بین 0 تا 100 وارد کنید.");
         return;
      }

      $stmt = $pdo->prepare("UPDATE goals SET progress = :progress, updated_at = NOW() WHERE id = :id");
      $stmt->bindValue(':progress', $text, PDO::PARAM_STR);
      
      $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
      $stmt->execute();

      updateUser($user_id, ['step' => 'completed']);

      $response = "✅ پیشرفت به $text% بروزرسانی شد!";

      if ($text == 100) {
         $stmt = $pdo->prepare("UPDATE goals SET is_completed = 1 WHERE id = :id");
         $stmt->bindValue(':id', $goal_id, PDO::PARAM_INT);
         
         $stmt->execute();
         $response .= "\n\n🎉 تبریک! شما به هدف خود رسیدید!";
      }

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '👁 مشاهده هدف', 'callback_data' => 'goal_view_' . $goal_id]],
            [['text' => '📋 لیست اهداف', 'callback_data' => 'goal_list_1']],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   }
}

function parseGoalDate($input)
{
   $input = convertPersianToEnglish($input);

   if (preg_match('/(\d+)\s*(روز|هفته|ماه|سال)/', $input, $matches)) {
      $number = $matches[1];
      $unit = $matches[2];

      switch ($unit) {
         case 'روز':
            return date('Y-m-d', strtotime("+$number days"));
         case 'هفته':
            return date('Y-m-d', strtotime("+$number weeks"));
         case 'ماه':
            return date('Y-m-d', strtotime("+$number months"));
         case 'سال':
            return date('Y-m-d', strtotime("+$number years"));
      }
   }

   return date('Y-m-d', strtotime('+3 months'));
}

