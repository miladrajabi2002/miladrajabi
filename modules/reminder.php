<?php

function showReminderMenu($chat_id, $user_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM reminders");
   $stmt->execute();
   $count = $stmt->fetchColumn();

   $text = "🕑 <b>مدیریت یادآورها</b>\n\n";

   $text .= "<b>نمونه‌های یادآور:</b>\n\n";

   $text .= "🕐 <b>زمانی:</b>\n";
   $text .= "• 5 دقیقه دیگه یادم بنداز برم پیوی یکی\n";
   $text .= "• نیم ساعت دیگه باید دارو بخورم\n";
   $text .= "• یک ساعت دیگر جلسه کاری دارم\n";
   $text .= "• فردا ساعت 9:30 ملاقات دکتر\n\n";

   $text .= "🔄 <b>تکراری:</b>\n";
   $text .= "• هر روز ساعت 8:15 یادم بنداز نون بگیرم\n";
   $text .= "• هر هفته دوشنبه ورزش کنم\n";
   $text .= "• هر ماه 23 تاریخ تمدید اشتراک فیلترشکن\n";
   $text .= "• هر سال 15 اردیبهشت تولد مامان\n\n";

   $text .= "📋 <b>کاری:</b>\n";
   $text .= "• باید تا پنج‌شنبه پروژه رو تحویل بدم\n";
   $text .= "• یادم بنداز فردا گزارش کار رو ارسال کنم\n";
   $text .= "• هفته آینده جلسه با مشتری مهم\n\n";

   $text .= "🏥 <b>پزشکی:</b>\n";
   $text .= "• هر 8 ساعت یک بار دارو بخورم\n";
   $text .= "• ماه آینده نوبت دندانپزشک\n";
   $text .= "• سه ماه دیگه آزمایش خون\n\n";

   $text .= "🚗 <b>خودرو:</b>\n";
   $text .= "• هر 6 ماه تعویض روغن ماشین\n";
   $text .= "• سال آینده تمدید بیمه خودرو\n";
   $text .= "• دو ماه دیگه معاینه فنی\n\n";

   $text .= "💡 <b>نکته:</b>\n";
   $text .= "• یادآورهای یکبار پس از انجام حذف می‌شوند\n";
   $text .= "• یادآورهای تکراری خودکار تجدید می‌شوند\n";
   $text .= "• کافیست متن خود را به صورت طبیعی ارسال کنید!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '📋 لیست یادآورها' . " ($count)", 'callback_data' => 'reminder_list']
         ],
         [
            ['text' => '❓ راهنما', 'callback_data' => 'reminder_help']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function handleReminderCallback($chat_id, $user_id, $data, $message_id)
{
   $parts = explode('_', $data);
   $action = $parts[1] ?? '';

   switch ($action) {
      case 'confirm':
         $confirm_action = $parts[2] ?? '';
         if ($confirm_action == 'yes') {
            confirmAndSaveReminder($chat_id, $user_id, $message_id);
         }
         break;
      case 'change':
         if ($parts[2] == 'title') {
            startTitleChange($chat_id, $user_id, $message_id);
         }
         break;
      case 'list':
         $page = $parts[2] ?? 1;
         showRemindersList($chat_id, $user_id, $message_id, $page);
         break;
      case 'show':
         $reminder_id = $parts[2] ?? 0;
         showReminderDetails($chat_id, $user_id, $reminder_id, $message_id);
         break;
      case 'edit':
         if ($parts[2] == 'title') {
            $reminder_id = $parts[3] ?? 0;
            startReminderTitleEdit($chat_id, $user_id, $reminder_id, $message_id);
         }
         break;
      case 'delete':
         $reminder_id = $parts[2] ?? 0;
         confirmDelete($chat_id, $user_id, $reminder_id, $message_id);
         break;
      case 'confirmdelete':
         $reminder_id = $parts[2] ?? 0;
         deleteReminder($chat_id, $user_id, $reminder_id, $message_id);
         break;
      case 'snooze':
         $reminder_id = $parts[2] ?? 0;
         snoozeReminder($chat_id, $user_id, $reminder_id, $message_id);
         break;
      case 'done':
         $reminder_id = $parts[2] ?? 0;
         markReminderDone($chat_id, $user_id, $reminder_id, $message_id);
         break;
      case 'activate':
         $reminder_id = $parts[2] ?? 0;
         activateReminder($chat_id, $user_id, $reminder_id, $message_id);
         break;
      case 'help':
         showReminderHelp($chat_id, $user_id, $message_id);
         break;
   }
}

function showRemindersList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $limit = 6;
   $offset = ($page - 1) * $limit;

   // نمایش همه یادآورها (فعال و غیرفعال)
   $stmt = $pdo->prepare("SELECT * FROM reminders ORDER BY reminder_time ASC LIMIT :limit OFFSET :offset");

   $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $reminders = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM reminders");

   $stmt->execute();
   $total = $stmt->fetchColumn();

   // شمارش فعال و غیرفعال
   $stmt = $pdo->prepare("SELECT COUNT(*) FROM reminders WHERE is_active = :is_active");

   $stmt->bindValue(':is_active', 1, PDO::PARAM_INT);
   $stmt->execute();
   $active_count = $stmt->fetchColumn();

   $inactive_count = $total - $active_count;

   if (empty($reminders)) {
      $text = "📋 <b>لیست یادآورها</b>\n\n";
      $text .= "❌ هیچ یادآوری ندارید.\n\n";
      $text .= "برای افزودن یادآور جدید، متن خود را ارسال کنید.\n\n";
      $text .= "<b>نمونه‌های یادآور:</b>\n";
      $text .= "• نیم ساعت دیگه یادم بنداز یچیزیو کامل کنم\n";
      $text .= "• فردا ساعت 9 جلسه مهم\n";
      $text .= "• هر روز ساعت 8:15 نون بگیرم";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '🔙 بازگشت', 'callback_data' => 'reminders']]
         ]
      ];
   } else {
      $text = "📋 <b>همه یادآورها</b>\n";
      $text .= "صفحه $page از " . ceil($total / $limit) . " • مجموع: $total یادآور\n\n";
      $text .= "🔘 فعال: $active_count | ☑️ غیرفعال: $inactive_count\n\n";
      $text .= "برای مشاهده جزئیات، روی یادآور کلیک کنید:";

      $keyboard = [
         'inline_keyboard' => []
      ];

      // نمایش یادآورها به صورت تک‌ردیفه
      foreach ($reminders as $reminder) {
         $persian_datetime = jdate('m/d H:i', strtotime($reminder['reminder_time']));
         $title = mb_strlen($reminder['title']) > 20 ? mb_substr($reminder['title'], 0, 20) . '...' : $reminder['title'];

         // آیکون وضعیت
         $status_icon = $reminder['is_active'] ? '🔘' : '☑️';


         $button_text = "$status_icon $title • $persian_datetime";

         // هر یادآور یک ردیف کامل
         $keyboard['inline_keyboard'][] = [
            [
               'text' => $button_text,
               'callback_data' => 'reminder_show_' . $reminder['id']
            ]
         ];
      }

      // صفحه‌بندی شیشه‌ای
      $total_pages = ceil($total / $limit);
      if ($total_pages > 1) {
         $pagination_row = [];

         // دکمه قبلی
         if ($page > 1) {
            $pagination_row[] = [
               'text' => '⬅️',
               'callback_data' => 'reminder_list_' . ($page - 1)
            ];
         }

         // دکمه‌های شماره صفحه (حداکثر 5 صفحه نمایش)
         $start_page = max(1, $page - 2);
         $end_page = min($total_pages, $start_page + 4);

         for ($i = $start_page; $i <= $end_page; $i++) {
            $page_text = ($i == $page) ? "[$i]" : "$i";
            $pagination_row[] = [
               'text' => $page_text,
               'callback_data' => 'reminder_list_' . $i
            ];
         }

         // دکمه بعدی
         if ($page < $total_pages) {
            $pagination_row[] = [
               'text' => '➡️',
               'callback_data' => 'reminder_list_' . ($page + 1)
            ];
         }

         $keyboard['inline_keyboard'][] = $pagination_row;
      }

      // دکمه بازگشت
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'reminders']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showReminderDetails($chat_id, $user_id, $reminder_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM reminders WHERE id = :id");
   $stmt->bindValue(':id', $reminder_id, PDO::PARAM_INT);

   $stmt->execute();
   $reminder = $stmt->fetch();

   if (!$reminder) {
      editMessage($chat_id, $message_id, "❌ یادآور یافت نشد.");
      return;
   }

   $persian_datetime = jdate('Y/m/d H:i', strtotime($reminder['reminder_time']));
   $created_date = jdate('Y/m/d', strtotime($reminder['created_at']));

   $status_icon = $reminder['is_active'] ? '🔘' : '☑️';
   $status_text = $reminder['is_active'] ? 'فعال' : 'غیرفعال';

   // محاسبه زمان باقی‌مانده فقط برای فعال‌ها
   $time_status = '';
   if ($reminder['is_active']) {
      $now = time();
      $reminder_time = strtotime($reminder['reminder_time']);
      $diff = $reminder_time - $now;

      if ($diff > 0) {
         $time_status = "⏳ " . formatTimeRemaining($diff);
      } else {
         $time_status = "⏰ زمان گذشته";
      }
   }

   $text = "📋 <b>جزئیات یادآور</b>\n\n";
   $text .= "📝 <b>عنوان:</b> " . htmlspecialchars($reminder['title']) . "\n";
   $text .= "📅 <b>زمان:</b> $persian_datetime\n";
   $text .= "$status_icon <b>وضعیت:</b> $status_text\n";

   if ($time_status) {
      $text .= "$time_status\n";
   }

   if ($reminder['repeat_type'] != 'once') {
      $repeat_text = getRepeatTypeText($reminder['repeat_type']);
      $text .= "🔄 <b>تکرار:</b> $repeat_text\n";
   }

   $text .= "📆 <b>ایجاد:</b> $created_date\n";

   if ($reminder['description']) {
      $text .= "💬 <b>توضیحات:</b> " . htmlspecialchars($reminder['description']) . "\n";
   }

   // دکمه‌های مختلف بر اساس وضعیت
   if ($reminder['is_active']) {
      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '✅ انجام شد', 'callback_data' => 'reminder_done_' . $reminder_id]
            ],
            [
               ['text' => '✏️ تغییر عنوان', 'callback_data' => 'reminder_edit_title_' . $reminder_id],
               ['text' => '🗑 حذف', 'callback_data' => 'reminder_delete_' . $reminder_id]
            ],
            [
               ['text' => '📋 بازگشت به لیست', 'callback_data' => 'reminder_list_1']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];
   } else {
      // برای غیرفعال‌ها فقط حذف و فعال کردن
      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '🔘 فعال کردن', 'callback_data' => 'reminder_activate_' . $reminder_id]
            ],
            [
               ['text' => '✏️ تغییر عنوان', 'callback_data' => 'reminder_edit_title_' . $reminder_id],
               ['text' => '🗑 حذف', 'callback_data' => 'reminder_delete_' . $reminder_id]
            ],
            [
               ['text' => '📋 بازگشت به لیست', 'callback_data' => 'reminder_list_1']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];
   }

   if ($message_id == 0) {
      sendMessage($chat_id, $text, $keyboard);
   } else {
      editMessage($chat_id, $message_id, $text, $keyboard);
   }
}

function confirmAndSaveReminder($chat_id, $user_id, $message_id)
{
   $user = getUser($user_id);

   if (!$user['temp_reminder']) {
      editMessage($chat_id, $message_id, "❌ اطلاعات یادآور یافت نشد.");
      return;
   }

   $reminder_data = json_decode($user['temp_reminder'], true);

   // ذخیره یادآور
   $reminder_id = saveReminder(
      $user_id,
      $reminder_data['title'],
      $reminder_data['datetime'],
      $reminder_data['repeat_type']
   );

   if ($reminder_id) {
      // پاک کردن داده موقت
      updateUser($user_id, ['temp_reminder' => null]);

      // تبدیل به تاریخ شمسی برای نمایش
      $persian_date = jdate('Y/m/d', strtotime($reminder_data['datetime']));
      $persian_time = jdate('H:i', strtotime($reminder_data['datetime']));

      $response = "✅ <b>یادآور با موفقیت ثبت شد!</b>\n\n";
      $response .= "📝 <b>عنوان:</b> " . htmlspecialchars($reminder_data['title']) . "\n";
      $response .= "📅 <b>تاریخ:</b> $persian_date\n";
      $response .= "⏰ <b>ساعت:</b> $persian_time\n";

      if ($reminder_data['repeat_type'] != 'once') {
         $repeat_text = getRepeatTypeText($reminder_data['repeat_type']);
         $response .= "🔄 <b>تکرار:</b> $repeat_text\n";
      }

      $response .= "\n💡 برای مدیریت یادآورها از منوی ربات استفاده کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📋 مشاهده یادآورها', 'callback_data' => 'reminder_list'],
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      editMessage($chat_id, $message_id, $response, $keyboard);
   } else {
      editMessage($chat_id, $message_id, "❌ خطا در ثبت یادآور. لطفاً دوباره تلاش کنید.");
   }
}

function cancelReminder($chat_id, $user_id, $message_id)
{
   // پاک کردن داده موقت
   updateUser($user_id, ['temp_reminder' => null]);

   $text = "❌ یادآور لغو شد.\n\n";
   $text .= "💡 برای ثبت یادآور جدید، متن خود را ارسال کنید.";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'back_main']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showAddReminderHelp($chat_id, $user_id, $message_id)
{
   $text = "➕ <b>افزودن یادآور جدید</b>\n\n";
   $text .= "برای افزودن یادآور، کافیست متن خود را ارسال کنید.\n\n";
   $text .= "<b>نمونه‌ها:</b>\n";
   $text .= "🔸 یادم بنداز فردا ساعت 8 صبح قرار دندانپزشک\n";
   $text .= "🔸 هر هفته یک‌شنبه ساعت 18 کلاس ورزش\n";
   $text .= "🔸 هر ماه 15ام پرداخت قبض برق\n";
   $text .= "🔸 هر 6 ماه یک بار سرویس ماشین\n\n";
   $text .= "ربات به صورت هوشمند تاریخ، ساعت و نوع تکرار را تشخیص می‌دهد.";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'reminders']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showReminderEditMenu($chat_id, $user_id, $message_id)
{
   $user = getUser($user_id);

   if (!$user['temp_reminder']) {
      editMessage($chat_id, $message_id, "❌ اطلاعات یادآور یافت نشد.");
      return;
   }

   $reminder_data = json_decode($user['temp_reminder'], true);

   $text = "✏️ <b>ویرایش یادآور</b>\n\n";
   $text .= "چه بخشی را می‌خواهید ویرایش کنید؟\n\n";
   $text .= "📝 عنوان فعلی: " . htmlspecialchars($reminder_data['title']) . "\n";
   $text .= "📅 تاریخ و ساعت: " . jdate('Y/m/d H:i', strtotime($reminder_data['datetime'])) . "\n";
   $text .= "🎯 اولویت: " . ($reminder_data['priority'] == 'high' ? 'بالا' : ($reminder_data['priority'] == 'low' ? 'پایین' : 'متوسط'));

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✏️ ویرایش عنوان', 'callback_data' => 'reminder_edittitle'],
            ['text' => '⏰ ویرایش زمان', 'callback_data' => 'reminder_edittime']
         ],
         [
            ['text' => '🎯 ویرایش اولویت', 'callback_data' => 'reminder_editpriority'],
            ['text' => '🔄 ویرایش تکرار', 'callback_data' => 'reminder_editrepeat']
         ],
         [
            ['text' => '✅ تایید نهایی', 'callback_data' => 'reminder_confirm_yes'],
            ['text' => '❌ لغو', 'callback_data' => 'reminder_confirm_no']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showEditTitleForm($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'editing_reminder_title']);

   $text = "✏️ <b>ویرایش عنوان یادآور</b>\n\n";
   $text .= "عنوان جدید یادآور را وارد کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'reminder_edit']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showEditTimeForm($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'editing_reminder_time']);

   $text = "⏰ <b>ویرایش زمان یادآور</b>\n\n";
   $text .= "زمان جدید را وارد کنید:\n\n";
   $text .= "<b>نمونه‌ها:</b>\n";
   $text .= "• 10 دقیقه دیگر\n";
   $text .= "• 2 ساعت دیگر\n";
   $text .= "• فردا ساعت 9\n";
   $text .= "• هفته آینده\n";
   $text .= "• 15:30";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'reminder_edit']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showEditPriorityForm($chat_id, $user_id, $message_id)
{
   $text = "🎯 <b>انتخاب اولویت یادآور</b>\n\n";
   $text .= "اولویت یادآور را انتخاب کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '🔴 بالا', 'callback_data' => 'reminder_setpriority_high'],
            ['text' => '🟡 متوسط', 'callback_data' => 'reminder_setpriority_medium']
         ],
         [
            ['text' => '🟢 پایین', 'callback_data' => 'reminder_setpriority_low'],
            ['text' => '🔙 بازگشت', 'callback_data' => 'reminder_edit']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showReminderStats($chat_id, $user_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        COUNT(DISTINCT repeat_type) as types
        FROM reminders");

   $stmt->execute();
   $stats = $stmt->fetch();

   $stmt = $pdo->prepare("SELECT 
        DATE(reminder_time) as reminder_date,
        COUNT(*) as count
        FROM reminders 
        WHERE is_active = :is_active AND reminder_time >= CURDATE()
        GROUP BY DATE(reminder_time)
        ORDER BY reminder_time ASC
        LIMIT 7");

   $stmt->bindValue(':is_active', 1, PDO::PARAM_INT);
   $stmt->execute();
   $upcoming = $stmt->fetchAll();

   $text = "📊 <b>آمار یادآورها</b>\n\n";
   $text .= "📈 کل یادآورها: " . $stats['total'] . "\n";
   $text .= "✅ فعال: " . $stats['active'] . "\n";
   $text .= "❌ غیرفعال: " . ($stats['total'] - $stats['active']) . "\n\n";

   if (!empty($upcoming)) {
      $text .= "📅 <b>یادآورهای آینده:</b>\n";
      foreach ($upcoming as $day) {
         $date = jdate('l Y/m/d', strtotime($day['reminder_date']));
         $text .= "• $date: " . $day['count'] . " یادآور\n";
      }
   }

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'reminders']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function processReminderText($chat_id, $user_id, $text)
{
   // استخراج اطلاعات یادآور
   $date_changed = false;
   $title = extractReminderTitle($text);
   $time = extractTimeFromText($text, $date_changed);
   $date = extractDateFromText($text, $date_changed);
   $repeat_type = extractRepeatType($text); // تشخیص تکرار

   // ترکیب تاریخ و ساعت
   $reminder_datetime = $date . ' ' . $time . ':00';

   // ذخیره موقت اطلاعات یادآور
   $temp_reminder = [
      'title' => $title,
      'datetime' => $reminder_datetime,
      'repeat_type' => $repeat_type,
      'original_text' => $text
   ];

   updateUser($user_id, ['temp_reminder' => json_encode($temp_reminder)]);

   // نمایش پیش‌نمایش و درخواست تایید
   showReminderPreview($chat_id, $user_id, $temp_reminder);
}

function showReminderPreview($chat_id, $user_id, $reminder_data)
{
   // تبدیل به تاریخ شمسی برای نمایش
   $persian_date = jdate('Y/m/d', strtotime($reminder_data['datetime']));
   $persian_time = jdate('H:i', strtotime($reminder_data['datetime']));

   $text = "🕑 <b>پیش‌نمایش یادآور</b>\n\n";
   $text .= "📝 <b>عنوان:</b> " . htmlspecialchars($reminder_data['title']) . "\n";
   $text .= "📅 <b>تاریخ:</b> $persian_date\n";
   $text .= "⏰ <b>ساعت:</b> $persian_time\n";

   if ($reminder_data['repeat_type'] != 'once') {
      $repeat_text = getRepeatTypeText($reminder_data['repeat_type']);
      $text .= "🔄 <b>تکرار:</b> $repeat_text\n";
   }

   $text .= "\n❓ آیا این یادآور را ثبت کنم؟";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، ثبت کن', 'callback_data' => 'reminder_confirm_yes'],
            ['text' => '✏️ تغییر عنوان', 'callback_data' => 'reminder_change_title']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   sendMessage($chat_id, $text, $keyboard);
}

function extractReminderTitle($text)
{
   // حذف کلمات اضافی از ابتدا
   $title = preg_replace('/^(یادم\s*بنداز|یادآوری\s*کن|یاد\s*بده|بگو|یادآور|یادآوری|تذکر\s*بده|خاطرم\s*بمونه)\s*/u', '', $text);

   // 🆕 حذف الگوهای تکرار (بهبود یافته)
   $title = preg_replace('/(هر\s*روز|روزانه|هرروز|روز\s*به\s*روز)\s*/u', '', $title);
   $title = preg_replace('/(هر\s*هفته|هفتگی|هرهفته|هفته\s*ای)\s*/u', '', $title);
   $title = preg_replace('/(هر\s*ماه|ماهانه|ماهیانه|هرماه|ماه\s*به\s*ماه)\s*/u', '', $title);
   $title = preg_replace('/(هر\s*سال|سالانه|سالیانه|هرسال|سال\s*به\s*سال)\s*/u', '', $title);

   // 🆕 حذف روزهای هفته
   $title = preg_replace('/\s*(شنبه|یکشنبه|دوشنبه|سه‌شنبه|سه\s*شنبه|چهارشنبه|چهار\s*شنبه|پنج‌شنبه|پنج\s*شنبه|جمعه)\s*/u', ' ', $title);

   // 🆕 حذف اطلاعات زمانی (بهبود یافته)
   // زمان‌های ترکیبی
   $title = preg_replace('/\s*(یک|بک|1)\s*ساعت\s*(و\s*)?نیم\s*(دیگه|دیگر|بعد)?\s*/u', ' ', $title);
   $title = preg_replace('/\s*(نیم\s*ساعت|یک\s*ساعت|ربع\s*ساعت|سه\s*ربع)\s*(دیگه|دیگر|بعد)?\s*/u', ' ', $title);

   // زمان‌های عمومی
   $title = preg_replace('/\s*(\d+|نیم|یک|بک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده)\s*(دقیقه|دقیقع|دیقه|دیقع|ساعت|سعت|ربع|روز)\s*(دیگه|دیگر|بعد)?\s*/u', ' ', $title);

   // 🆕 حذف تاریخ‌ها و زمان‌ها
   $title = preg_replace('/\s*(فردا|پسفردا|پس‌فردا|امروز|دیروز)\s*/u', ' ', $title);
   $title = preg_replace('/\s*(هفته|ماه|سال)\s*(آینده|دیگه|دیگر|بعد|گذشته|قبل)\s*/u', ' ', $title);

   // زمان‌های مشخص (ساعت 14:30)
   $title = preg_replace('/\s*ساعت\s*(\d{1,2}):?(\d{0,2})\s*/u', ' ', $title);
   $title = preg_replace('/\s*(\d{1,2}):(\d{2})\s*/u', ' ', $title);

   // 🆕 حذف تاریخ‌های شمسی
   $title = preg_replace('/\s*(\d{4})\/(\d{1,2})\/(\d{1,2})\s*/u', ' ', $title);
   $title = preg_replace('/\s*(\d{1,2})\/(\d{1,2})\s*/u', ' ', $title);

   // 🆕 حذف الگوهای ماهانه
   $title = preg_replace('/\s*(یکم|دوم|سوم|چهارم|پنجم|ششم|هفتم|هشتم|نهم|دهم|یازدهم|دوازدهم|سیزدهم|چهاردهم|پانزدهم|شانزدهم|هفدهم|هجدهم|نوزدهم|بیستم|بیست\s*و\s*یکم|بیست\s*و\s*دوم|بیست\s*و\s*سوم|بیست\s*و\s*چهارم|بیست\s*و\s*پنجم|بیست\s*و\s*ششم|بیست\s*و\s*هفتم|بیست\s*و\s*هشتم|بیست\s*و\s*نهم|سی‌ام|سی\s*و\s*یکم|\d+)\s*(ام|م|اُم)?\s*(ماه|هر\s*ماه|هرماه)\s*/u', ' ', $title);

   // 🆕 حذف الگوهای "آخر ماه"
   $title = preg_replace('/\s*(آخر|انتها|انتهای)\s*(ماه|هر\s*ماه|هرماه)\s*/u', ' ', $title);

   // 🆕 حذف کلمات اضافی در انتها
   $title = preg_replace('/\s*(رو|را|کن|کنم|بکن|بکنم|داشته\s*باشم|یادم\s*باشه|فراموش\s*نکنم)\s*$/u', '', $title);

   // 🆕 حذف حروف اضافه و کلمات پرکاربرد
   $title = preg_replace('/\s*(که|تا|برای|جهت|در|از|به|با|و|یا|اگر|اگه)\s*/u', ' ', $title);

   // 🆕 پاکسازی نقطه‌گذاری اضافی
   $title = preg_replace('/[،؛:.!؟]+/u', '', $title);

   // پاکسازی فضاهای اضافی
   $title = preg_replace('/\s+/u', ' ', $title);
   $title = trim($title);

   // 🆕 بررسی کیفیت عنوان و پیشنهاد بهتر
   if (empty($title) || strlen($title) < 2) {
      return 'یادآور جدید';
   }

   // 🆕 اگر عنوان خیلی کوتاه باشد، اضافه کردن کلمه مناسب
   if (strlen($title) < 4) {
      $common_words = ['خرید', 'کار', 'تماس', 'جلسه', 'قرار'];
      foreach ($common_words as $word) {
         if (strpos($text, $word) !== false) {
            return $word;
         }
      }
   }

   // 🆕 تبدیل به حروف کوچک برای کلمات خاص
   $title = preg_replace_callback('/\b(خرید|کار|تماس|جلسه|قرار|دارو|ورزش|مطالعه)\b/u', function ($matches) {
      return $matches[0];
   }, $title);

   return $title ?: 'یادآور جدید';
}

function extractRepeatType($text)
{
   $text_lower = mb_strtolower($text);

   // روزانه
   if (preg_match('/(هر\s*روز|روزانه|هرروز|هر\s*صبح|هرصبح|هر\s*ظهر|هرظهر|هر\s*عصر|هرعصر|هر\s*بعدازظهر|هر بعد از ظهر|هر\s*شب|شبانه|هرشب)/u', $text_lower)) {
      return 'daily';
   }

   // هفتگی
   if (preg_match('/(هر\s*هفته|هفتگی|هرهفته)/u', $text_lower)) {
      return 'weekly';
   }

   // ماهیانه
   if (preg_match('/(هر\s*ماه|ماهانه|ماهیانه|هرماه)/u', $text_lower)) {
      return 'monthly';
   }

   // سالیانه
   if (preg_match('/(هر\s*سال|سالانه|سالیانه|هرسال)/u', $text_lower)) {
      return 'yearly';
   }

   return 'once';
}


function saveReminder($user_id, $title, $reminder_datetime, $repeat_type = 'once', $description = '')
{
   global $pdo;

   $stmt = $pdo->prepare("INSERT INTO reminders (title, description, reminder_time, repeat_type, is_active, created_at) VALUES (:title, :description, :reminder_time, :repeat_type, 1, NOW())");
   $stmt->bindValue(':title', $title, PDO::PARAM_STR);
   $stmt->bindValue(':description', $description, PDO::PARAM_STR);
   $stmt->bindValue(':reminder_time', $reminder_datetime, PDO::PARAM_STR);
   $stmt->bindValue(':repeat_type', $repeat_type, PDO::PARAM_STR);

   if ($stmt->execute()) {
      return $pdo->lastInsertId();
   }

   return false;
}

function deleteReminder($chat_id, $user_id, $reminder_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("DELETE FROM reminders WHERE id = :id");
   $stmt->bindValue(':id', $reminder_id, PDO::PARAM_INT);


   if ($stmt->execute()) {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "🗑 یادآور حذف شد");
      showRemindersList($chat_id, $user_id, $message_id, 1);
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در حذف یادآور");
   }
}

function getRepeatTypeText($repeat_type)
{
   $types = [
      'daily' => 'روزانه',
      'weekly' => 'هفتگی',
      'monthly' => 'ماهیانه',
      'yearly' => 'سالیانه'
   ];

   return $types[$repeat_type] ?? 'یکبار';
}

function getPriorityIcon($priority)
{
   $icons = [
      'high' => '🔴',
      'medium' => '🟡',
      'low' => '🟢'
   ];

   return $icons[$priority] ?? '🟡';
}

function markReminderDone($chat_id, $user_id, $reminder_id, $message_id)
{
   global $pdo;

   // دریافت اطلاعات یادآور
   $stmt = $pdo->prepare("SELECT * FROM reminders WHERE id = :id");
   $stmt->bindValue(':id', $reminder_id, PDO::PARAM_INT);

   $stmt->execute();
   $reminder = $stmt->fetch();

   if (!$reminder) {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ یادآور یافت نشد");
      return;
   }

   if ($reminder['repeat_type'] == 'once') {
      // یادآور یکبار - حذف کامل
      $stmt = $pdo->prepare("DELETE FROM reminders WHERE id = :id");
      $stmt->bindValue(':id', $reminder_id, PDO::PARAM_INT);
   

      if ($stmt->execute()) {
         global $callback_query_id;
         answerCallbackQuery($callback_query_id, "✅ یادآور انجام شد و حذف گردید");

         // بازگشت به لیست
         showRemindersList($chat_id, $user_id, $message_id, 1);
      }
   } else {
      // یادآور تکراری - ایجاد یادآور بعدی
      $next_time = calculateNextReminderTime($reminder['reminder_time'], $reminder['repeat_type']);

      if ($next_time) {
         // بروزرسانی زمان یادآور فعلی
         $stmt = $pdo->prepare("UPDATE reminders SET reminder_time = :reminder_time WHERE id = :id");
         $stmt->bindValue(':reminder_time', $next_time, PDO::PARAM_STR);
         $stmt->bindValue(':id', $reminder_id, PDO::PARAM_INT);
      

         if ($stmt->execute()) {
            global $callback_query_id;
            $persian_next_time = jdate('m/d H:i', strtotime($next_time));
            answerCallbackQuery($callback_query_id, "✅ انجام شد! بعدی: $persian_next_time");

            showReminderDetails($chat_id, $user_id, $reminder_id, $message_id);
         }
      } else {
         global $callback_query_id;
         answerCallbackQuery($callback_query_id, "❌ خطا در محاسبه زمان بعدی");
      }
   }
}

function calculateNextReminderTime($current_time, $repeat_type)
{
   $timestamp = strtotime($current_time);

   switch ($repeat_type) {
      case 'daily':
         return date('Y-m-d H:i:s', strtotime('+1 day', $timestamp));

      case 'weekly':
         return date('Y-m-d H:i:s', strtotime('+1 week', $timestamp));

      case 'monthly':
         return date('Y-m-d H:i:s', strtotime('+1 month', $timestamp));

      case 'yearly':
         return date('Y-m-d H:i:s', strtotime('+1 year', $timestamp));

      default:
         return false;
   }
}

function snoozeReminder($chat_id, $user_id, $reminder_id, $message_id)
{
   global $pdo;

   // 1 ساعت به تعویق انداختن
   $stmt = $pdo->prepare("UPDATE reminders SET is_active = 1, reminder_time = DATE_ADD(reminder_time, INTERVAL 1 HOUR) WHERE id = :id");
   $stmt->bindValue(':id', $reminder_id, PDO::PARAM_INT);


   if ($stmt->execute()) {
      deleteMessage($chat_id, $message_id);
      sendMessage($chat_id, "⏰ یادآور به یک ساعت دیگر موکول شد.");
   } else {
      sendMessage($chat_id, "❌ خطا در به تعویق انداختن یادآور.");
   }
}

function showReminderHelp($chat_id, $user_id, $message_id)
{
   $help_text = "📚 <b>راهنمای یادآورها</b>\n\n";

   $help_text .= "🔹 <b>یادآور ساده:</b>\n";
   $help_text .= "• یادم بنداز فردا ساعت 10 نون بخرم\n";
   $help_text .= "• 5 دقیقه دیگه یادم بنداز برم پیوی یکی\n";
   $help_text .= "• 2 ساعت دیگر باید دارو بخورم\n\n";

   $help_text .= "🔄 <b>یادآور تکراری:</b>\n";
   $help_text .= "• هر روز ساعت 8:15 یادم بنداز نون بگیرم\n";
   $help_text .= "• هر هفته یکشنبه ورزش کنم\n";
   $help_text .= "• هر ماه 23 تاریخ تمدید اشتراک\n";
   $help_text .= "• هر سال تولد مامان\n\n";

   $help_text .= "⏰ <b>زمان‌های پشتیبانی شده:</b>\n";
   $help_text .= "• دقیقه دیگر: 5 دقیقه دیگه\n";
   $help_text .= "• ساعت دیگر: 2 ساعت دیگر\n";
   $help_text .= "• فردا: فردا ساعت 9\n";
   $help_text .= "• هفته آینده: هفته آینده\n";
   $help_text .= "• ماه آینده: ماه دیگه\n\n";

   $help_text .= "💡 <b>نکته:</b> کافیست متن یادآور خود را به صورت طبیعی ارسال کنید!";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'reminders']]
      ]
   ];

   editMessage($chat_id, $message_id, $help_text, $keyboard);
}


function startTitleChange($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'changing_preview_title']);

   $text = "✏️ <b>تغییر عنوان یادآور</b>\n\n";
   $text .= "عنوان جدید یادآور را وارد کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'back_main']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function startReminderTitleEdit($chat_id, $user_id, $reminder_id, $message_id)
{
   updateUser($user_id, ['step' => 'editing_reminder_title_' . $reminder_id]);

   $text = "✏️ <b>تغییر عنوان یادآور</b>\n\n";
   $text .= "عنوان جدید را وارد کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'reminder_show_' . $reminder_id]]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function confirmDelete($chat_id, $user_id, $reminder_id, $message_id)
{
   $text = "🗑 <b>حذف یادآور</b>\n\n";
   $text .= "آیا مطمئن هستید که می‌خواهید این یادآور را حذف کنید؟\n\n";
   $text .= "⚠️ این عمل قابل بازگشت نیست!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، حذف کن', 'callback_data' => 'reminder_confirmdelete_' . $reminder_id],
            ['text' => '❌ خیر', 'callback_data' => 'reminder_show_' . $reminder_id]
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function formatTimeRemaining($seconds)
{
   if ($seconds < 60) {
      return "کمتر از یک دقیقه";
   } elseif ($seconds < 3600) {
      $minutes = floor($seconds / 60);
      return "$minutes دقیقه";
   } elseif ($seconds < 86400) {
      $hours = floor($seconds / 3600);
      $minutes = floor(($seconds % 3600) / 60);
      return $minutes > 0 ? "$hours ساعت و $minutes دقیقه" : "$hours ساعت";
   } else {
      $days = floor($seconds / 86400);
      $hours = floor(($seconds % 86400) / 3600);
      return $hours > 0 ? "$days روز و $hours ساعت" : "$days روز";
   }
}

function activateReminder($chat_id, $user_id, $reminder_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("UPDATE reminders SET is_active = 1 WHERE id = :id");
   $stmt->bindValue(':id', $reminder_id, PDO::PARAM_INT);


   if ($stmt->execute()) {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "🔘 یادآور فعال شد");
      showReminderDetails($chat_id, $user_id, $reminder_id, $message_id);
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در فعال کردن یادآور");
   }
}

